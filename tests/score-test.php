<?php

declare(strict_types=1);

/** Scoring is deterministic, so it can be pinned exactly. php tests/score-test.php */

require __DIR__ . '/../app/bootstrap.php';

use App\Support\ReviewScore;
use App\Support\SafeFetch;

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) { $pass++; printf("  PASS  %s\n", $label); }
    else     { $fail++; printf("  FAIL  %s  %s\n", $label, $detail); }
}
function throws(string $label, callable $fn, string $needle = ''): void {
    global $pass, $fail;
    try { $fn(); $fail++; printf("  FAIL  %s  (no exception)\n", $label); }
    catch (Throwable $e) {
        $ok = $needle === '' || str_contains($e->getMessage(), $needle);
        if ($ok) { $pass++; printf("  PASS  %s\n", $label); }
        else { $fail++; printf("  FAIL  %s  got: %s\n", $label, $e->getMessage()); }
    }
}

echo "\nScoring\n";
$empty = ReviewScore::analyse('<html><body><h1>Plumbing</h1></body></html>', 'http://x.test/');
check('bare page over http scores 0', $empty['score'] === 0, (string) $empty['score']);
check('band is "Needs work"', $empty['band'] === 'Needs work', $empty['band']);
check('every failure is explained', count($empty['failed']) === 7, (string) count($empty['failed']));

$https = ReviewScore::analyse('<html><head><meta name="viewport" content="width=device-width"></head><body></body></html>', 'https://x.test/');
check('https + viewport = 10', $https['score'] === 10, (string) $https['score']);

$full = ReviewScore::analyse(
    '<html><head><meta name="viewport" content="width=device-width">'
  . '<script type="application/ld+json">{"@type":"AggregateRating","ratingValue":"4.8"}</script></head>'
  . '<body><a href="https://g.page/acme-pools/review">Leave us a Google review</a>'
  . '<section class="testimonials">Great service</section>'
  . '<div class="star-rating">★★★★★</div>'
  . '<script src="https://widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js"></script>'
  . '</body></html>',
    'https://x.test/'
);
check('fully equipped page scores 100', $full['score'] === 100, (string) $full['score']);
check('band is "Strong"', $full['band'] === 'Strong', $full['band']);
check('platform identified', $full['platform'] === 'Trustpilot', (string) $full['platform']);
check('nothing left failing', $full['failed'] === []);

echo "\nIndividual signals\n";
$cases = [
    ['google via g.page',      '<a href="https://g.page/x/review">x</a>', 'google_link'],
    ['google via maps link',   '<a href="https://maps.app.goo.gl/abc">x</a>', 'google_link'],
    ['google via writereview', '<a href="/writereview?placeid=1">x</a>', 'google_link'],
    ['JSON-LD aggregate',      '<script>{"@type":"AggregateRating"}</script>', 'structured_data'],
    ['microdata aggregate',    '<div itemprop="aggregateRating">4.5</div>', 'structured_data'],
    ['testimonials word',      '<h2>Testimonials</h2>', 'reviews_shown'],
    ['two review classes',     '<div class="review-card"></div><div class="reviews-grid"></div>', 'reviews_shown'],
    ['star glyph',             '<span>★★★★★</span>', 'stars_visible'],
    ['star class',             '<div class="star-rating"></div>', 'stars_visible'],
    ['birdeye widget',         '<script src="//birdeye.com/w.js"></script>', 'review_widget'],
];
foreach ($cases as [$label, $html, $key]) {
    $r = ReviewScore::analyse('<html><body>' . $html . '</body></html>', 'http://x.test/');
    $hit = false;
    foreach ($r['checks'] as $c) { if ($c['key'] === $key) { $hit = $c['passed']; } }
    check($label . ' -> ' . $key, $hit);
}

echo "\nNo false positives\n";
$nav = ReviewScore::analyse('<html><body><a class="review-link" href="/reviews">Reviews</a></body></html>', 'http://x.test/');
$shown = false;
foreach ($nav['checks'] as $c) { if ($c['key'] === 'reviews_shown') { $shown = $c['passed']; } }
check('a single nav link is not "reviews on page"', !$shown);

$notGoogle = ReviewScore::analyse('<html><body><a href="https://facebook.com/x/reviews">fb</a></body></html>', 'http://x.test/');
$g = false;
foreach ($notGoogle['checks'] as $c) { if ($c['key'] === 'google_link') { $g = $c['passed']; } }
check('a Facebook reviews link is not a Google one', !$g);

echo "\nBands\n";
check('0 -> Needs work',  ReviewScore::band(0) === 'Needs work');
check('44 -> Needs work', ReviewScore::band(44) === 'Needs work');
check('45 -> Getting there', ReviewScore::band(45) === 'Getting there');
check('74 -> Getting there', ReviewScore::band(74) === 'Getting there');
check('75 -> Strong', ReviewScore::band(75) === 'Strong');
check('100 -> Strong', ReviewScore::band(100) === 'Strong');

echo "\nURL safety\n";
foreach ([
    ['loopback',        'http://127.0.0.1/'],
    ['cloud metadata',  'http://169.254.169.254/'],
    ['private 10/8',    'http://10.0.0.1/'],
    ['private 192.168', 'http://192.168.0.1/'],
    ['file scheme',     'file:///etc/passwd'],
    ['credentials',     'http://u:p@example.com/'],
    ['ssh port',        'http://example.com:22/'],
    ['octal loopback',  'http://0177.0.0.1/'],
    ['all-zeros',       'http://0.0.0.0/'],
] as [$label, $url]) {
    throws('refuses ' . $label, static fn () => SafeFetch::get($url));
}
check('a bare domain gains https://', SafeFetch::normalise('acme.com') === 'https://acme.com');
check('an existing scheme is kept', SafeFetch::normalise('http://acme.com') === 'http://acme.com');
throws('empty address is rejected', static fn () => SafeFetch::normalise('  '), 'Enter your website');

echo "\nRendering\n";
// Score a real page served locally, then render the partial exactly as the site
// does. SafeFetch is not involved — it is tested separately above, and a test is
// no reason to put a bypass inside it.
$html = @file_get_contents('http://127.0.0.1:8140/index.html');
if ($html === false) {
    echo "  SKIP  local fixture server not running\n";
} else {
    $result = ReviewScore::analyse($html, 'https://acmepools.test/');
    check('fixture page scores 100', $result['score'] === 100, (string) $result['score']);

    $out = App\Support\View::render('partials/score-result', ['result' => $result]);
    check('dial prints the number', str_contains($out, '>100</span>'));
    check('dial is labelled for screen readers',
        str_contains($out, 'aria-label="Review score 100 out of 100"'));
    check('band is written out, not just coloured', str_contains($out, 'Strong'));
    check('every check is listed', substr_count($out, 'class="score-check') >= 7);
    check('limits are stated', str_contains($out, 'could not see'));

    $bare = ReviewScore::analyse((string) @file_get_contents('http://127.0.0.1:8140/bare.html'), 'http://bare.test/');
    check('bare page scores 0', $bare['score'] === 0, (string) $bare['score']);
    $out2 = App\Support\View::render('partials/score-result', ['result' => $bare]);
    check('zero score still renders a dial', str_contains($out2, '>0</span>'));
    check('zero score shows no filled checks', !str_contains($out2, 'score-check is-on'));
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);

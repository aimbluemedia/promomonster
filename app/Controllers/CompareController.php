<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Claude;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\ReviewComparison;
use App\Support\Validator;
use App\Support\View;
use Throwable;

/**
 * Public one-time review comparison.
 *
 * Every submission spends real money on the Claude API, on a page anyone on the
 * internet can reach. That single fact drives the design, so the limits are
 * collected here rather than scattered through the method:
 *
 *   - One free comparison per email address, ever. That is the offer.
 *   - Per-IP and per-email rate limits, so nobody walks through it with
 *     throwaway addresses at speed.
 *   - A global daily ceiling. If everything else is somehow defeated, the most
 *     a single day can cost is DAILY_CAP x roughly $0.15.
 *   - Hard caps on how much text is sent, because tokens are the bill and a
 *     pasted novel is an expensive way to learn that.
 *
 * The checks run cheapest-first: CSRF and honeypot cost nothing, the database
 * counts are one query each, and the API call happens last.
 */
final class CompareController
{
    /** Self-serve comparisons allowed across the whole site per rolling day. */
    private const DAILY_CAP = 40;

    /** Per business: reviews kept, and characters per review. */
    private const MAX_REVIEWS = 8;
    private const MAX_REVIEW_CHARS = 900;

    /** Competitors accepted. Two keeps the prompt — and the bill — bounded. */
    private const MAX_COMPETITORS = 2;

    public function show(): void
    {
        $error  = $_SESSION['compare_error'] ?? null;
        $results = $_SESSION['compare_result'] ?? null;
        $old    = $_SESSION['compare_old'] ?? [];
        unset($_SESSION['compare_error'], $_SESSION['compare_result'], $_SESSION['compare_old']);

        echo View::page('compare', [
            'title'       => 'Free AI review comparison · PromoMonster',
            'description' => 'See how your Google rating and reviews compare with two competitors. '
                           . 'One free comparison, no card.',
            'error'       => $error,
            'results'     => $results,
            'old'         => $old,
            'available'   => Claude::isConfigured(),
        ]);
    }

    public function run(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail('Your session expired. Please try again.');
        }

        // Honeypot: look like success so a bot learns nothing.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            Request::redirect('/compare');
        }

        // The form asks for the business name once, as subject_name. Validating
        // a separate mirrored field would mean a hidden input kept in step by
        // JavaScript, and the form has to work without it.
        $_POST['business'] = $_POST['subject_name'] ?? '';

        $validator = new Validator($_POST);
        $email    = $validator->email('email', 'Please enter a valid email address.');
        $business = $validator->required('business', 'Tell us your business name.', 160);

        if ($validator->fails()) {
            $this->fail($validator->firstError() ?? 'Please check the form and try again.');
        }

        $email = mb_strtolower((string) $email);

        $competitors = [];
        foreach (range(1, self::MAX_COMPETITORS) as $n) {
            $competitor = $this->readBusiness('c' . $n);
            if ($competitor['name'] !== '') {
                $competitors[] = $competitor;
            }
        }
        if ($competitors === []) {
            $this->fail('Add at least one competitor to compare against.');
        }

        if (!Claude::isConfigured()) {
            $this->fail('The comparison tool is briefly unavailable. Please try again later.');
        }

        // --- Spend guards --------------------------------------------------
        // The one-time check comes first deliberately. It is a definitive no,
        // and putting a counter ahead of it would spend a visitor's daily
        // allowance telling them something we already knew — so an honest
        // second attempt would lock them out of a page they can never use
        // anyway.
        $used = Database::first(
            "SELECT id FROM audits WHERE email = :email AND source = 'self_serve' LIMIT 1",
            ['email' => $email],
        );
        if ($used !== null) {
            $this->fail('That address has already had its free comparison. '
                . 'Create a free account and you can run them whenever you like.');
        }

        if (RateLimiter::tooManyAttempts('compare:ip:' . Request::ip(), 3, 86400)) {
            $this->fail('That is the limit for one connection today. Email hello@promomonster.com '
                . 'and a person will run one for you.');
        }
        if (RateLimiter::tooManyAttempts('compare:email:' . $email, 2, 86400)) {
            $this->fail('That address has already been used today.');
        }

        $today = Database::first(
            "SELECT COUNT(*) AS n FROM audits
              WHERE source = 'self_serve' AND created_at > NOW() - INTERVAL 1 DAY"
        );
        if ((int) ($today['n'] ?? 0) >= self::DAILY_CAP) {
            $this->fail("We have hit today's limit on free comparisons. "
                . 'Try tomorrow, or create a free account.');
        }

        // --- Run it --------------------------------------------------------
        $subject = $this->readBusiness('subject');
        $subject['name'] = (string) $business;

        try {
            $results = ReviewComparison::run($subject, $competitors);
        } catch (Throwable $e) {
            // The visitor gets a plain apology; the detail goes to the log via
            // ErrorHandler, where the operator can find it by reference.
            error_log('compare: ' . $e->getMessage());
            $this->fail('Something went wrong producing your comparison. Nothing was charged to you '
                . '— please try again, or email hello@promomonster.com.');
        }

        Database::run(
            "INSERT INTO audits (business_name, email, vertical, source, status, results,
                                 results_generated_at, ip)
             VALUES (:business, :email, :vertical, 'self_serve', 'new', :results, NOW(), :ip)",
            [
                'business' => $subject['name'],
                'email'    => $email,
                'vertical' => (new Validator($_POST))->value('vertical', 60),
                'results'  => json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'ip'       => Request::ip(),
            ],
        );

        $_SESSION['compare_result'] = $results;
        Request::redirect('/compare#result');
    }

    /**
     * @return array{name:string,rating:?float,review_count:?int,reviews:array<int,string>}
     */
    private function readBusiness(string $prefix): array
    {
        $rating = trim((string) ($_POST[$prefix . '_rating'] ?? ''));
        $count  = trim((string) ($_POST[$prefix . '_count'] ?? ''));

        $raw = trim((string) ($_POST[$prefix . '_reviews'] ?? ''));
        $reviews = $raw === '' ? [] : (preg_split('/\n\s*\n/', $raw) ?: []);
        $reviews = array_values(array_filter(array_map('trim', $reviews)));

        // Truncate rather than reject: someone pasting a long review should get
        // an answer, not a scolding. The cap is what keeps the bill predictable.
        $reviews = array_map(
            static fn (string $r): string => mb_substr($r, 0, self::MAX_REVIEW_CHARS),
            array_slice($reviews, 0, self::MAX_REVIEWS),
        );

        return [
            'name'         => trim(mb_substr((string) ($_POST[$prefix . '_name'] ?? ''), 0, 160)),
            'rating'       => is_numeric($rating) ? max(1.0, min(5.0, (float) $rating)) : null,
            'review_count' => is_numeric($count) ? max(0, min(100000, (int) $count)) : null,
            'reviews'      => $reviews,
        ];
    }

    private function fail(string $message): never
    {
        $_SESSION['compare_error'] = $message;
        $_SESSION['compare_old'] = [
            'business' => (string) ($_POST['subject_name'] ?? ''),
            'email'    => (string) ($_POST['email'] ?? ''),
        ];
        Request::redirect('/compare#start');
    }
}

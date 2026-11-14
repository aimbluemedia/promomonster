<?php

declare(strict_types=1);

/**
 * Drafted replies: the rules, and the meter.
 *
 * Everything this feature produces is about to be posted in public under a
 * member's own business name. The model is instructed carefully and obeys,
 * which is exactly why the output is checked as well -- a rule enforced only
 * by a prompt holds until the day a model update changes its mind, and the
 * failure is silent and lands on somebody's Google listing.
 *
 * Three refusals matter more than anything else here:
 *   asking a customer to change or remove their review (against Google's
 *   policy, and it is the member's listing at risk, not ours),
 *   promising a refund or a discount on the member's behalf,
 *   inventing a fact, a URL or an email address.
 *
 * No network. ReplyDrafts::clean() is the whole of the output path, so it can
 * be handed exactly what a model might return.
 */

define('APP_ROOT', dirname(__DIR__) . '/app');
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_ROOT . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Support\Plans;
use App\Support\ReplyDrafts;

$passed = 0;
$failed = 0;

function check(string $what, mixed $got, mixed $want): void
{
    global $passed, $failed;
    if ($got === $want) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL  {$what}\n      got:  " . var_export($got, true)
        . "\n      want: " . var_export($want, true) . "\n";
}

function ok(string $what, bool $got): void
{
    check($what, $got, true);
}

/** Did clean() refuse this? */
function refused(string $text): bool
{
    try {
        ReplyDrafts::clean($text);
        return false;
    } catch (Throwable) {
        return true;
    }
}

/* ---- What must never reach a member's clipboard ----------------------- */

$mustRefuse = [
    'asking for the review to come down' =>
        'Sorry about that, Dave. Would you consider taking down the review now it is sorted?',
    'asking for the review to be changed' =>
        'Thanks Dave. If you are happy now, please change your review to five stars.',
    'the gentle version of the same thing' =>
        'We hope you will reconsider your review once we have put this right.',
    'asking for an update' =>
        'Glad we sorted it. Please update your review when you get a minute.',
    'offering a refund' =>
        'Sorry Dave. We will issue a full refund for the callout.',
    'offering a discount' =>
        'Apologies. Your next service will be at a 20 per cent discount.',
    'offering free work' =>
        'We will come back and do it free of charge.',
    'offering compensation' =>
        'We would like to offer compensation for the inconvenience.',
    'naming the tool' =>
        'Thanks Dave! Sent with PromoMonster.',
    'putting a web address in' =>
        'Sorry about that. Please see https://example.com/complaints and we will sort it.',
    'putting an email in' =>
        'Sorry about that. Email mike@example.com and we will sort it.',
    'putting a bare www address in' =>
        'Thanks Dave. More at www.example.com.',
    'an empty answer' => '   ',
];

foreach ($mustRefuse as $what => $text) {
    ok("refused: {$what}", refused($text));
}

/* ---- What must get through -------------------------------------------- */

$mustPass = [
    'a plain thank you' =>
        'Thanks Dave, really glad the boiler is behaving itself again. Appreciate you taking the time.',
    'a short apology with an invitation to make contact' =>
        'I am sorry, Dave. Turning up two hours late is not how we work and I do not blame you '
        . 'for being annoyed. Give us a ring and I will put it right myself.',
    'no name given' =>
        'Thank you for this. Glad the job went smoothly and that the lads left the place tidy.',
    'mentions a specific thing' =>
        'Appreciate it, Sam. The leak under that sink had been patched twice before we got there, '
        . 'so it is good to hear it has stayed dry.',
];

foreach ($mustPass as $what => $text) {
    ok("allowed: {$what}", !refused($text));
}

/* ---- Tidying ----------------------------------------------------------- */

check('wrapping quotes are stripped',
    ReplyDrafts::clean('"Thanks Dave, glad it is sorted."'),
    'Thanks Dave, glad it is sorted.');
check('curly wrapping quotes too',
    ReplyDrafts::clean("\u{201c}Thanks Dave.\u{201d}"),
    'Thanks Dave.');
check('a quote inside the sentence is left alone',
    ReplyDrafts::clean('Dave said "spot on" and that made our day.'),
    'Dave said "spot on" and that made our day.');
check('markdown emphasis goes',
    ReplyDrafts::clean('Thanks **Dave** for the `kind` words.'),
    'Thanks Dave for the kind words.');
check('a run of blank lines collapses',
    ReplyDrafts::clean("Thanks Dave.\n\n\n\nMuch appreciated."),
    "Thanks Dave.\n\nMuch appreciated.");
check('surrounding whitespace goes',
    ReplyDrafts::clean("  Thanks Dave.  \n"), 'Thanks Dave.');

/* ---- The first name ---------------------------------------------------- */

check('first word of a full name', ReplyDrafts::firstName('Dave Patterson'), 'Dave');
check('a single name', ReplyDrafts::firstName('Dave'), 'Dave');
check('spacing does not matter', ReplyDrafts::firstName('   Sam   Blake '), 'Sam');
// An initial is not a name, and "Thanks D," reads as a mail merge that broke.
check('an initial is not a name', ReplyDrafts::firstName('D Patterson'), null);
check('nothing is nothing', ReplyDrafts::firstName(''), null);
check('spaces are nothing', ReplyDrafts::firstName('   '), null);

/* ---- The prompt still carries the rules -------------------------------- */

// The checks above are the net. The prompt is what stops the model producing
// these in the first place, and a rule deleted from it would never show up as
// a failing assertion anywhere else.
$system = (new ReflectionClass(ReplyDrafts::class))->getConstant('SYSTEM');
ok('the prompt exists', is_string($system) && $system !== '');

// Whitespace flattened before searching. The prompt is hard-wrapped, so
// "in their voice, as them" has a newline inside it on the page and a check
// for the literal phrase failed on a rule that was present and correct.
$flat = (string) preg_replace('/\s+/', ' ', (string) $system);

foreach ([
    'never ask for the review to change' => 'change, update, take down, remove or reconsider',
    'never offer money back'             => 'NEVER offer a refund',
    'never invent a fact'                => 'NEVER state a fact you were not given',
    'never dispute the reviewer'         => 'NEVER dispute',
    'never name the tool'                => 'NEVER mention PromoMonster',
    'never add contact details'          => 'NEVER include a phone number',
    'plain text only'                    => 'No markdown',
    'writes as the owner'                => 'in their voice, as them',
] as $what => $phrase) {
    ok("the prompt says: {$what}", str_contains($flat, $phrase));
}

/* ---- The two guards, checked for being able to fire -------------------- */

$forbidden = (new ReflectionClass(ReplyDrafts::class))->getConstant('FORBIDDEN');
ok('there is a forbidden list', is_array($forbidden) && $forbidden !== []);
foreach ($forbidden as $phrase) {
    ok("forbidden phrase is lowercase: {$phrase}", $phrase === mb_strtolower($phrase));
    // Matching is done on a lowercased haystack, so an uppercase entry would
    // silently never match -- a guard that is present and does nothing.
    ok("and it is caught in any case: {$phrase}",
        refused('Hello. ' . mb_strtoupper($phrase) . ' and thanks.'));
}

// The review-removal guard is a pattern, not a list, because a list of fixed
// phrases let "taking down the review" through: it held "take down" and the
// model wrote "taking". Every tense of every verb, with up to a few words in
// between, has to be caught.
foreach ([
    'remove that review', 'removing that review', 'removed the review',
    'delete your review', 'deleting your review',
    'change your review', 'changing the review', 'changed that review',
    'update your review', 'updating your review',
    'revise your review', 'amend your review', 'edit the review',
    'retract your review', 'withdraw the review',
    'reconsider your review', 'reconsidering your review',
    'take down the review', 'taking down the review', 'took down the review',
    'remove your five star review', 'change your very fair review',
] as $wording) {
    ok("caught, any tense: {$wording}", refused("Thanks. Would you {$wording}?"));
}

// And it does not fire on the word used honestly. A reply that cannot say
// "we reviewed the job" is a guard that has started eating real sentences.
foreach ([
    'We reviewed the job again and found the fault straight away.',
    'Thanks for the review, Dave. Glad it is sorted.',
    'I have reviewed what happened with the lad who came out.',
    'Your review of the work is fair and I am sorry about it.',
] as $honest) {
    ok("left alone: {$honest}", !refused($honest));
}

/* ---- Who may ask for one ----------------------------------------------- */

check('free cannot',    Plans::can(Plans::FREE, Plans::REPLY_DRAFTS), false);
check('pro cannot',     Plans::can(Plans::PRO, Plans::REPLY_DRAFTS), false);
check('premium can',    Plans::can(Plans::PREMIUM, Plans::REPLY_DRAFTS), true);
check('partner can',    Plans::can(Plans::PARTNER, Plans::REPLY_DRAFTS), true);
check('premium is the cheapest that can',
    Plans::cheapestWith(Plans::REPLY_DRAFTS), Plans::PREMIUM);

// The cap is what makes this affordable on a $49 plan and unaffordable on a
// $19 one, so a plan that CAN draft has to have a number, and one that cannot
// has to have nothing to spend.
foreach (Plans::SELECTABLE as $plan) {
    $limit = Plans::limit($plan, 'reply_drafts_per_month');
    if (Plans::can($plan, Plans::REPLY_DRAFTS)) {
        ok("{$plan}: has an allowance", $limit === null || (int) $limit > 0);
    } else {
        check("{$plan}: has nothing to spend", (int) $limit, 0);
    }
}

check('the card names the real allowance',
    Plans::draftLimit(Plans::PREMIUM),
    Plans::limit(Plans::PREMIUM, 'reply_drafts_per_month') . ' review replies');
ok('an uncapped plan says so', str_contains(Plans::draftLimit(Plans::PARTNER), 'uncapped'));

/* ---- Nothing in this class can publish --------------------------------- */

// The one structural guarantee. A draft goes into a box the member then saves
// themselves; if this class could write to hosted_reviews, a bug in it would
// be a reply posted in somebody's name that they never read.
// Comments stripped first. The class documents what it is handed -- "a
// hosted_reviews row" -- and a check that reads its own documentation fails
// on a rule it is keeping. The same mistake has been made twice in this suite.
$source = (string) file_get_contents(APP_ROOT . '/Support/ReplyDrafts.php');
$source = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $source);
foreach (['UPDATE ', 'INSERT ', 'Database::', 'hosted_reviews'] as $forbiddenCall) {
    ok("ReplyDrafts cannot touch the database: {$forbiddenCall}",
        !str_contains($source, $forbiddenCall));
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

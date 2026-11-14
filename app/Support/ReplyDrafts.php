<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * A suggested reply to a review, written by Claude, for the owner to edit.
 *
 * Replying is the thing every local business knows it should do and does not.
 * It is also the thing that is genuinely hard to do well at nine at night
 * after a twelve-hour day, particularly for the one-star that is unfair.
 *
 * Three rules shape everything here, and all three are in the system prompt
 * because a model that is merely asked nicely will drift:
 *
 *   Nothing is ever posted. This produces text into a box the owner then
 *   reads, changes and saves themselves. There is no path from this class to
 *   a published reply, and the draft and the saved reply are different
 *   columns in different tables so one cannot be mistaken for the other.
 *
 *   It never asks a customer to change or remove their review. That is what
 *   the reputation industry does and it is against Google's policy; a draft
 *   that said it would put the member at risk, not us. It is forbidden in the
 *   prompt and checked in the output.
 *
 *   It promises nothing on the member's behalf. No refund, no discount, no
 *   free visit, no "we have fired him". The owner can offer any of those, in
 *   their own words, once they have decided to -- a model guessing at it is
 *   writing cheques somebody else has to honour.
 *
 * The draft is stored. A member who presses the button, reads it, and reloads
 * the page gets the same draft back rather than a second API call and a
 * different answer, and is not charged twice against their month for asking
 * once.
 */
final class ReplyDrafts
{
    /** Longest draft we will ask for. A reply over this is not a reply. */
    public const MAX_WORDS = 90;

    private const SYSTEM = <<<'TXT'
You write short replies that the OWNER of a small local business will post
underneath a review of their business. You are writing in their voice, as
them. You are not writing to the owner.

Write ONE reply. Plain text. No markdown, no headings, no bullet points, no
quotation marks around the whole thing, no subject line, no signature block.
At most 90 words, usually far fewer. Two or three sentences is normal.

Voice: an ordinary person who runs a trade business, typing on a phone.
Warm, specific, unfussy. No marketing language. Never "we strive to", "we
value your feedback", "at [business] we pride ourselves". Those phrases are
why most review replies are worthless.

Use the reviewer's first name if you were given one. Mention the specific
thing they talked about -- that is the entire difference between a reply
that reads as human and one that reads as a template.

For a positive review: thank them, name the specific thing, and stop. Do not
ask them to come back, do not ask them to tell their friends, do not
advertise anything.

For a critical review: acknowledge what went wrong in their words, say
plainly that it is not the standard, and invite them to make contact so it
can be put right. One short apology, not three. No excuses, no explanation
of why it happened, no blaming a supplier or an employee or the weather.

ABSOLUTE RULES. Breaking any of these causes real harm to the business:

- NEVER ask them to change, update, take down, remove or reconsider their
  review, in any wording, however gently. It is against Google's policy and
  it puts the business's listing at risk. Not even "we hope you will give us
  another chance to earn five stars".
- NEVER offer a refund, a discount, a credit, a free visit, a replacement or
  compensation of any kind. You do not know what the business can afford or
  has already done. The owner can offer that themselves if they decide to.
- NEVER state a fact you were not given. Not a date, not a price, not an
  employee's name, not what the job was, not what was said on the phone. If
  you were not told it, it does not go in.
- NEVER dispute the reviewer's account or suggest they are mistaken, lying
  or confused, even when the review is obviously unfair.
- NEVER mention PromoMonster, this tool, or that the reply was drafted.
- NEVER include a phone number, an email address or a URL. The owner adds
  their own if they want one there.

Return only the reply text.
TXT;

    /**
     * Phrases that must never reach a member's clipboard.
     *
     * The prompt forbids these and the model obeys it, which is exactly why
     * this exists: a rule enforced only by a prompt is a rule that holds until
     * the day a model update changes its mind, and the failure is silent and
     * lands on the member's public listing. Cheap to check, so it is checked.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'refund', 'discount', 'free of charge', 'no charge', 'compensation',
        'voucher', 'credit note', 'money back',
        'promomonster',
    ];

    /**
     * Asking a customer to do something to their review, in any wording.
     *
     * A fixed list of phrases was the first attempt and it let "taking down
     * the review" straight through, because the list had "take down" and the
     * model wrote "taking". The verb stem plus the word review is the thing
     * that is actually forbidden, so that is what is matched -- every tense,
     * and with or without a word in between.
     */
    private const ASKS_ABOUT_REVIEW = '~\b(remov|delet|chang|updat|revis|amend|edit|retract|'
        . 'withdraw|reconsider|take?\s*down|taking\s*down|took\s*down)\w*\b'
        . '(?:\W+\w+){0,4}?\W+reviews?\b~i';

    /**
     * Draft a reply to one hosted review.
     *
     * @param  array<string,mixed> $review   a hosted_reviews row
     * @throws RuntimeException when Claude is not configured, or the answer
     *                          breaks one of the rules above
     */
    public static function write(array $review, string $business, ?string $trade = null): string
    {
        $rating = (int) ($review['rating'] ?? 0);
        $body   = trim((string) ($review['body'] ?? ''));
        $name   = trim((string) ($review['author_name'] ?? ''));

        if ($body === '' && $rating === 0) {
            throw new RuntimeException('There is nothing in this review to reply to.');
        }

        $prompt  = 'BUSINESS: ' . $business . "\n";
        if ($trade !== null && $trade !== '') {
            $prompt .= 'TRADE: ' . $trade . "\n";
        }
        $prompt .= 'REVIEWER FIRST NAME: ' . (self::firstName($name) ?? 'not given') . "\n";
        $prompt .= 'RATING: ' . ($rating > 0 ? $rating . ' out of 5' : 'not given') . "\n";
        $prompt .= "WHAT THEY WROTE:\n" . ($body !== '' ? $body : '(they left a rating and no words)') . "\n\n";
        $prompt .= 'Write the reply.';

        // Low effort and a small ceiling: this is a two-sentence job, and the
        // ceiling is what keeps the per-draft cost a known number rather than
        // whatever the model felt like writing.
        $text = Claude::ask(self::SYSTEM, $prompt, null, 'low', 400);
        if (!is_string($text)) {
            throw new RuntimeException('Claude returned something other than a reply.');
        }

        return self::clean($text);
    }

    /**
     * Tidy the answer, and refuse it outright if it broke a rule.
     *
     * Refusing is the right failure. A draft that asks a customer to take
     * their review down, shown to a member who trusts it enough to paste it,
     * is worse than a button that says it could not do it this time.
     */
    public static function clean(string $text): string
    {
        $text = trim($text);

        // Models like to wrap a single returned string in quotes. The member
        // would paste them.
        if (mb_strlen($text) > 1
            && in_array(mb_substr($text, 0, 1), ['"', "'", "\u{201c}"], true)
            && in_array(mb_substr($text, -1), ['"', "'", "\u{201d}"], true)) {
            $text = trim(mb_substr($text, 1, mb_strlen($text) - 2));
        }

        // Markdown emphasis, which arrives occasionally and reads as noise on
        // a Google listing where nothing renders it.
        $text = (string) preg_replace('/(\*\*|__|\*|`)/', '', $text);
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", $text));

        if ($text === '') {
            throw new RuntimeException('Claude returned an empty reply.');
        }

        if (preg_match(self::ASKS_ABOUT_REVIEW, $text) === 1) {
            throw new RuntimeException(
                'That draft asked the customer to change their review, which we never do -- '
                . 'it is against Google\'s policy and it is your listing at risk. '
                . 'Try again, or write this one yourself.'
            );
        }

        $haystack = mb_strtolower($text);
        foreach (self::FORBIDDEN as $phrase) {
            if (str_contains($haystack, $phrase)) {
                throw new RuntimeException(
                    'That draft said something it should not have, so we have thrown it away. '
                    . 'Try again, or write this one yourself.'
                );
            }
        }

        // A URL or an address is the owner's to add, and a model inventing one
        // is inventing a fact.
        if (preg_match('~https?://|www\.|\S+@\S+\.\S+~i', $text) === 1) {
            throw new RuntimeException(
                'That draft put a web address or an email in the reply, which we do not do. '
                . 'Try again, or write this one yourself.'
            );
        }

        return $text;
    }

    /** The first word of a name, or null. Nothing is guessed from an initial. */
    public static function firstName(string $name): ?string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');

        return mb_strlen($first) >= 2 ? $first : null;
    }
}

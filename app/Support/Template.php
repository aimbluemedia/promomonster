<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Merge fields for the review-request emails.
 *
 * {{first_name}} and friends. Deliberately tiny: this renders one short plain
 * text email, and every feature a template language grows — conditionals,
 * loops, includes — is another way for a business to put something in front of
 * a customer that we cannot check.
 *
 * Plain text, no HTML. A review request should read as though a person typed
 * it, because on the customer's end that is the difference between a favour
 * and a marketing email. It also sidesteps every HTML-email rendering problem
 * at once, and text-only mail is not the deliverability risk people assume: a
 * bare HTML template with one tracked link is far more likely to be filtered.
 */
final class Template
{
    /**
     * Substitution is single-pass, via strtr.
     *
     * str_replace in a loop would re-scan text it had already written, so a
     * customer called "{{review_url}}" — or a business that named itself that
     * to see what happened — would get their own name expanded into the link.
     * strtr never looks at what it has just substituted.
     *
     * @param array<string,string|null> $fields
     */
    public static function render(string $text, array $fields): string
    {
        $map = [];
        foreach ($fields as $key => $value) {
            $map['{{' . $key . '}}'] = self::clean((string) ($value ?? ''));
        }

        $out = strtr($text, $map);

        // Anything still in braces was never supplied. Drop it: a customer
        // reading "Hi {{first_name}}," knows exactly how little thought went
        // into the message.
        $out = preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $out) ?? $out;

        return self::tidy($out);
    }

    /**
     * The merge values, from a location and a contact.
     *
     * first_name falls back to "there", so a contact imported with only an
     * address still gets a sentence that reads properly rather than "Hi ,".
     *
     * @param array<string,mixed> $location
     * @param array<string,mixed> $contact
     * @return array<string,string>
     */
    public static function fields(array $location, array $contact, string $reviewUrl): array
    {
        $first = trim((string) ($contact['first_name'] ?? ''));

        return [
            'first_name'    => $first !== '' ? $first : 'there',
            'last_name'     => trim((string) ($contact['last_name'] ?? '')),
            'business_name' => trim((string) ($location['name'] ?? '')),
            'review_url'    => $reviewUrl,
            'city'          => trim((string) ($location['city'] ?? '')),
        ];
    }

    /**
     * The footer every request carries.
     *
     * Three things, and none of them optional. The postal address and a working
     * opt-out are what CAN-SPAM requires of a commercial email, and this is one
     * — it is sent on a business's behalf to promote that business. Saying who
     * the email is about closes the gap left by sending from our domain.
     *
     * The PromoMonster line only appears on Free. It is the price of the plan,
     * and it is also the only marketing this product does.
     *
     * @param array<string,mixed> $location
     */
    public static function footer(
        array $location,
        string $unsubscribeUrl,
        bool $showBranding,
        string $appUrl = 'https://promomonster.com',
    ): string {
        $lines = [str_repeat('-', 46)];

        $business = trim((string) ($location['name'] ?? ''));
        if ($business !== '') {
            $lines[] = 'This email is from ' . $business . '.';
        }

        $address = self::postalAddress($location);
        if ($address !== '') {
            $lines[] = $address;
        }

        $lines[] = 'No more emails: ' . $unsubscribeUrl;

        if ($showBranding) {
            $lines[] = '';
            $lines[] = 'Sent with PromoMonster, free review requests for local';
            $lines[] = 'businesses. See your own review score: ' . rtrim($appUrl, '/') . '/score';
        }

        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $location */
    public static function postalAddress(array $location): string
    {
        $parts = array_filter([
            trim((string) ($location['address_line1'] ?? '')),
            trim((string) ($location['city'] ?? '')),
            trim((string) ($location['region'] ?? '')),
            trim((string) ($location['postal_code'] ?? '')),
        ], static fn (string $p) => $p !== '');

        return implode(', ', $parts);
    }

    /**
     * A merge value, made safe to drop into a header or a body.
     *
     * The subject line is a header, so a newline in a contact's first name
     * would end it and let whatever followed be read as header syntax. Contact
     * names come from CSV uploads, so this is not hypothetical.
     */
    private static function clean(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /** Removing a field usually leaves a hole; close it up. */
    private static function tidy(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}

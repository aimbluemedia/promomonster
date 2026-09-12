<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal Claude API client, spoken over raw HTTP.
 *
 * Anthropic ships an official PHP SDK and it would normally be the right
 * choice — but it installs through Composer, and this project runs on shared
 * hosting with no shell, no Composer and no build step. Everything deploys by
 * file upload. So this speaks the Messages API directly through ext-curl,
 * which is the documented fallback when the SDK cannot be installed.
 *
 * Kept deliberately small: one endpoint, one method. It is not a
 * reimplementation of the SDK.
 */
final class Claude
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** Overridable so the flow can be exercised against a stub, and so a
     *  self-hosted gateway can be pointed at without touching this class. */
    private static function endpoint(): string
    {
        $base = trim((string) Config::get('anthropic.base_url', ''));

        return $base !== '' ? rtrim($base, '/') . '/v1/messages' : self::ENDPOINT;
    }
    private const VERSION  = '2023-06-01';

    /** Opus 5: 1M context, $5/MTok in, $25/MTok out. */
    public const MODEL = 'claude-opus-5';

    public static function isConfigured(): bool
    {
        return self::key() !== '';
    }

    private static function key(): string
    {
        return trim((string) Config::get('anthropic.api_key', ''));
    }

    /**
     * Sends one message and returns the first text block.
     *
     * @param  array<string,mixed>|null $schema  JSON Schema; when given, the
     *         reply is constrained to it and returned decoded.
     * @return array<string,mixed>|string  Decoded array with a schema, raw text without.
     */
    public static function ask(
        string $system,
        string $prompt,
        ?array $schema = null,
        string $effort = 'high',
        int $maxTokens = 16000,
    ): array|string {
        if (!self::isConfigured()) {
            throw new RuntimeException(
                'No Anthropic API key configured. Add an "anthropic" => ["api_key" => "..."] '
                . 'entry to app/config.php.'
            );
        }
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The curl PHP extension is required to reach the Claude API.');
        }

        [$status, $response] = self::post(self::body($system, $prompt, $schema, $effort, $maxTokens));

        return self::interpret($status, $response, $schema);
    }

    /**
     * The request payload. Split out so it can be asserted without a network
     * call — the shape of this object is the part most likely to drift.
     *
     * @param  array<string,mixed>|null $schema
     * @return array<string,mixed>
     */
    public static function body(
        string $system,
        string $prompt,
        ?array $schema = null,
        string $effort = 'high',
        int $maxTokens = 16000,
    ): array {
        $body = [
            'model'      => self::MODEL,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
            // Adaptive thinking is the current API. budget_tokens is rejected
            // with a 400 on this model.
            'thinking'      => ['type' => 'adaptive'],
            'output_config' => ['effort' => $effort],
        ];

        if ($schema !== null) {
            $body['output_config']['format'] = ['type' => 'json_schema', 'schema' => $schema];
        }

        return $body;
    }

    /**
     * Turns a raw API response into a result or a useful exception. Separate
     * from the transport so every failure mode can be exercised in a test.
     *
     * @param  array<string,mixed>      $response
     * @param  array<string,mixed>|null $schema
     * @return array<string,mixed>|string
     */
    public static function interpret(int $status, array $response, ?array $schema): array|string
    {
        if ($status === 401) {
            throw new RuntimeException('Claude rejected the API key (401). Check app/config.php.');
        }
        if ($status === 429) {
            throw new RuntimeException('Claude is rate limiting this key (429). Try again shortly.');
        }
        if ($status >= 500) {
            throw new RuntimeException("Claude returned a {$status}. This is usually transient — try again.");
        }
        if ($status !== 200) {
            $detail = $response['error']['message'] ?? 'no detail given';
            throw new RuntimeException("Claude returned {$status}: {$detail}");
        }

        // Safety classifiers can decline a request with HTTP 200. Check before
        // reading content, or you read an empty array and report nothing.
        if (($response['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException(
                'Claude declined this request'
                . (isset($response['stop_details']['category'])
                    ? ' (' . $response['stop_details']['category'] . ')' : '')
                . '. Check the review text for anything that reads as abusive.'
            );
        }

        // content is a list of blocks and a thinking block can come first, so
        // never index [0] blindly.
        $text = null;
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text = (string) $block['text'];
                break;
            }
        }

        if ($text === null) {
            throw new RuntimeException('Claude returned no text block.');
        }

        if ($schema === null) {
            return $text;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Claude returned malformed JSON despite a schema.');
        }

        return $decoded;
    }

    /**
     * @param  array<string,mixed> $body
     * @return array{0:int,1:array<string,mixed>}
     */
    private static function post(array $body): array
    {
        $ch = curl_init(self::endpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,   // Thinking on a long prompt is not fast.
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . self::key(),
                'anthropic-version: ' . self::VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            // Never interpolate the key into a message that gets logged.
            throw new RuntimeException('Could not reach the Claude API: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);

        return [$status, is_array($decoded) ? $decoded : []];
    }
}

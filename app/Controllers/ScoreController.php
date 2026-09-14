<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Claude;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\ReviewScore;
use App\Support\SafeFetch;
use App\Support\Validator;
use App\Support\View;
use Throwable;
use RuntimeException;

/**
 * Free Review Score: a URL and an email in, a score out, immediately.
 *
 * The number is computed from the page we fetched, not guessed by a model — see
 * ReviewScore. Claude only writes the paragraph that explains it, and if Claude
 * is unavailable the score still works and the page simply shows the findings
 * without the narrative. That ordering is deliberate: the headline number is the
 * promise on the button, so it must not depend on a third party being up.
 */
final class ScoreController
{
    private const DAILY_CAP = 60;

    public function show(): void
    {
        $error  = $_SESSION['score_error'] ?? null;
        $result = $_SESSION['score_result'] ?? null;
        $old    = $_SESSION['score_old'] ?? [];
        unset($_SESSION['score_error'], $_SESSION['score_result'], $_SESSION['score_old']);

        echo View::page('score', [
            'title'       => 'Free Review Score · PromoMonster',
            'description' => 'Enter your website and get a review score in seconds. '
                           . 'No credit card.',
            'error'       => $error,
            'result'      => $result,
            'old'         => $old,
        ]);
    }

    public function run(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail('Your session expired. Please try again.');
        }

        // Honeypot: behave as though it worked.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            Request::redirect('/score');
        }

        $validator = new Validator($_POST);
        $email = $validator->email('email', 'Please enter a valid email address.');
        $url   = $validator->required('url', 'Enter your website address.', 255);

        if ($validator->fails()) {
            $this->fail($validator->firstError() ?? 'Please check the form and try again.');
        }

        $email = mb_strtolower((string) $email);

        // Checked here, recorded only once a score actually exists. Counting the
        // attempt instead would charge a visitor for a mistyped address or for
        // our own crash, and there would be no row in superadmin to delete to
        // give it back.
        $ipKey    = 'score:ip:' . Request::ip();
        $emailKey = 'score:email:' . $email;

        if (RateLimiter::atLimit($ipKey, 5, 86400)) {
            $this->fail('That is the limit for one connection today.');
        }
        if (RateLimiter::atLimit($emailKey, 3, 86400)) {
            $this->fail('That address has been used a few times today already.');
        }

        $today = Database::first(
            "SELECT COUNT(*) AS n FROM audits
              WHERE source = 'score' AND created_at > NOW() - INTERVAL 1 DAY"
        );
        if ((int) ($today['n'] ?? 0) >= self::DAILY_CAP) {
            $this->fail("We have hit today's limit on free scores. Please try tomorrow.");
        }

        // --- Fetch and score ------------------------------------------------
        try {
            $page = SafeFetch::get((string) $url);
        } catch (RuntimeException $e) {
            // SafeFetch's messages are written to be shown to a visitor.
            $this->fail($e->getMessage());
        }

        $result = ReviewScore::analyse($page['html'], $page['url']);

        // --- Narrative, best-effort ----------------------------------------
        $result['summary'] = null;
        if (Claude::isConfigured()) {
            try {
                $result['summary'] = $this->narrate($result);
            } catch (Throwable $e) {
                // A score without a paragraph is still a score.
                error_log('score narrative: ' . $e->getMessage());
            }
        }

        Database::run(
            "INSERT INTO audits (business_name, website, email, source, status, results,
                                 results_generated_at, score, ip)
             VALUES (:business, :website, :email, 'score', 'new', :results, NOW(), :score, :ip)",
            [
                'business' => $this->businessFromUrl($page['url']),
                'website'  => mb_substr($page['url'], 0, 255),
                'email'    => $email,
                'results'  => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'score'    => $result['score'],
                'ip'       => Request::ip(),
            ],
        );

        // The work is done and the row exists: now it counts.
        RateLimiter::record($ipKey);
        RateLimiter::record($emailKey);

        $_SESSION['score_result'] = $result;
        Request::redirect('/score#result');
    }

    /**
     * Turns the findings into two or three sentences. The findings are already
     * decided — the model is told the score, not asked for one.
     *
     * @param array<string,mixed> $result
     */
    private function narrate(array $result): string
    {
        $system = <<<'TXT'
You write a short, plain summary of a website's review setup for the owner of a
local business. You are given a score that has ALREADY been calculated from
checks against their page. Your job is to explain it and name the single most
important fix.

Rules:
- Never change, dispute or recalculate the score. State it as given.
- Only refer to the findings you are given. Invent nothing about the business,
  its rating, its customers or its revenue.
- Never suggest screening or filtering customers before asking for a review,
  offering an incentive, or getting a review removed. Those break Google policy
  and the FTC treats gating as deceptive.
- Two or three sentences. Plain and calm. No hype, no exclamation marks.
- Second person: "your site", "you".
TXT;

        $prompt = 'Score: ' . $result['score'] . " out of 100 (" . $result['band'] . ").\n"
                . 'Page checked: ' . $result['url'] . "\n\n"
                . "What is in place:\n"
                . ($result['passed'] === [] ? "  nothing\n" : '  - ' . implode("\n  - ", $result['passed']) . "\n")
                . "\nWhat is missing:\n"
                . ($result['failed'] === [] ? "  nothing\n" : '  - ' . implode("\n  - ", $result['failed']) . "\n")
                . "\nWrite the summary.";

        $text = Claude::ask($system, $prompt, null, 'low', 400);

        return is_string($text) ? trim($text) : '';
    }

    /** A readable name for the operator's queue, from the host. */
    private function businessFromUrl(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? $url);

        return mb_substr(preg_replace('/^www\./', '', $host) ?: $host, 0, 200);
    }

    private function fail(string $message): never
    {
        $_SESSION['score_error'] = $message;
        $_SESSION['score_old'] = [
            'url'   => (string) ($_POST['url'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
        ];
        Request::redirect('/score#start');
    }
}

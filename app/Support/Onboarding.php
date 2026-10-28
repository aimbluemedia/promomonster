<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What a member still has to do before this product works for them.
 *
 * The dashboard counted things and never once said what to do next, which is
 * the question somebody has on their first visit and their fifth. Four numbers
 * reading zero is a diagnosis without a prescription.
 *
 * A pure function of state passed in, deliberately: no queries, no Auth, no
 * session. The controller gathers, this decides, the view draws. That split is
 * what makes the interesting part -- which step is next, what counts as done,
 * what a plan hides -- testable without a database, and this file is nothing
 * but judgements about that.
 *
 * NOTHING HERE TICKS WHAT IT CANNOT SEE. The last step, pasting the code into
 * a website, has no signal: the snippet is served from here but we do not
 * record who fetched it, and a checklist that guessed would be telling
 * somebody their site is live when it is not. It is shown as the finish line
 * and says plainly that we cannot check it from here.
 */
final class Onboarding
{
    /**
     * Works out the steps, in order, from state the caller has already read.
     *
     * @param array{
     *     plan?:string, hasPage?:bool, hasGoogleLink?:bool,
     *     requestsSent?:int, reviewsCollected?:int
     * } $state
     * @return list<array{
     *     key:string, title:string, what:string, done:bool, locked:bool,
     *     checkable:bool, href:string, cta:string, needs:?string, ability:?string
     * }>
     */
    public static function steps(array $state): array
    {
        $plan     = (string) ($state['plan'] ?? Plans::FREE);
        $requests = max(0, (int) ($state['requestsSent'] ?? 0));
        $reviews  = max(0, (int) ($state['reviewsCollected'] ?? 0));

        // A plan that cannot do a thing must not be nagged about it. The step
        // still shows -- hiding it would leave somebody wondering why the
        // Google page in the nav does nothing -- but it is marked as needing a
        // plan rather than as something they have failed to finish.
        $canGoogle = Plans::can($plan, Plans::GOOGLE_PAGE);

        return [
            [
                'key'       => 'page',
                'title'     => 'Your review page is ready',
                'what'      => 'Every account gets a page customers can leave a review on, '
                             . 'at your own address. Nothing to set up — open it and see '
                             . 'what they will see.',
                'done'      => (bool) ($state['hasPage'] ?? false),
                'locked'    => false,
                'checkable' => true,
                'href'      => '/members/promomonster-reviews',
                'cta'       => 'Open your review page',
                'needs'     => null,
                'ability'   => null,
            ],
            [
                'key'       => 'google',
                'title'     => 'Save your Google review link',
                'what'      => 'The one link that opens the review box on your Google '
                             . 'listing. With it saved, a request drops the customer '
                             . 'straight in — no searching, one tap.',
                'done'      => $canGoogle && (bool) ($state['hasGoogleLink'] ?? false),
                'locked'    => !$canGoogle,
                'checkable' => $canGoogle,
                'href'      => '/members/reviews',
                'cta'       => 'Add the link',
                'needs'     => $canGoogle ? null : Plans::cheapestWith(Plans::GOOGLE_PAGE),
                // The ability, not just the cheapest plan that grants it. The
                // screen names every plan it is on -- somebody weighing Premium
                // should not be told about Pro alone and left wondering whether
                // the dearer plan covers it too.
                'ability'   => $canGoogle ? null : Plans::GOOGLE_PAGE,
            ],
            [
                'key'       => 'send',
                'title'     => 'Send your first request',
                'what'      => 'One customer, on the day you did the work. That timing '
                             . 'matters more than anything else you will do here.',
                'done'      => $requests > 0,
                'locked'    => false,
                'checkable' => true,
                'href'      => '/members/requests',
                'cta'       => 'Ask a customer',
                'needs'     => null,
                'ability'   => null,
            ],
            [
                'key'       => 'collect',
                'title'     => 'Collect your first review',
                'what'      => 'This one is theirs, not yours. It arrives when somebody '
                             . 'you asked writes one — usually within a day or two of '
                             . 'the ask.',
                'done'      => $reviews > 0,
                'locked'    => false,
                'checkable' => true,
                'href'      => '/members/promomonster-reviews',
                'cta'       => 'See what has come in',
                'needs'     => null,
                'ability'   => null,
            ],
            [
                'key'       => 'display',
                'title'     => 'Put them on your website',
                'what'      => 'One line of code, pasted where you want the reviews to '
                             . 'show. We cannot see your website from here, so this is '
                             . 'the one step we will never tick for you.',
                'done'      => false,
                'locked'    => false,
                // The honest part. See the note at the top of this class.
                'checkable' => false,
                'href'      => '/members/promomonster-reviews',
                'cta'       => 'Get your code',
                'needs'     => null,
                'ability'   => null,
            ],
        ];
    }

    /**
     * A small number as a word, for a sentence rather than a count.
     *
     * "Three things and you are asking customers for reviews" reads; "3
     * things" reads like a form. Only goes as far as the list can, and falls
     * back to the digit rather than inventing a word it does not have.
     */
    public static function inWords(int $n): string
    {
        return ['zero', 'one', 'two', 'three', 'four', 'five', 'six'][$n] ?? (string) $n;
    }

    /**
     * How far along, counting only what can actually be finished.
     *
     * A step the plan forbids is out of the denominator, not marked undone: a
     * Free account would otherwise be stuck at "3 of 5" forever, reading as a
     * job half done rather than a plan working as sold. The unverifiable last
     * step is out for the same reason -- a bar that can never fill is a bar
     * that teaches people to ignore bars.
     *
     * @param list<array<string,mixed>> $steps
     * @return array{done:int, total:int, percent:int, complete:bool, next:?string}
     */
    public static function progress(array $steps): array
    {
        $counted = array_values(array_filter(
            $steps,
            static fn (array $s): bool => ($s['checkable'] ?? false) && !($s['locked'] ?? false),
        ));

        $done  = count(array_filter($counted, static fn (array $s): bool => (bool) $s['done']));
        $total = count($counted);

        // The next thing to actually do. Skips what is finished and what the
        // plan forbids, but NOT the unverifiable last step -- once everything
        // else is done, pasting the code genuinely is the next thing.
        $next = null;
        foreach ($steps as $step) {
            if (!$step['done'] && !$step['locked']) {
                $next = (string) $step['key'];
                break;
            }
        }

        return [
            'done'     => $done,
            'total'    => $total,
            'percent'  => $total === 0 ? 100 : (int) round($done / $total * 100),
            'complete' => $total > 0 && $done === $total,
            'next'     => $next,
        ];
    }
}

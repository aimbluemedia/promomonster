<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\HostedReviews;
use App\Support\Request;
use App\Support\Tokens;
use App\Support\View;

/**
 * The public side of hosted reviews: a business's page, its form, and the
 * widget that puts the result on the business's own website.
 *
 * All three show the same reviews in the same order with nothing filtered out.
 * That is deliberate and is the only reason the widget is worth embedding -- a
 * feed a business could curate would be an advert, and readers treat it as one.
 */
final class ReviewPageController
{
    /** GET /reviews/{slug} */
    public function show(string $slug): void
    {
        $account = HostedReviews::bySlug($slug);
        if ($account === null) {
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'Page not found']);
            return;
        }

        $id = (int) $account['id'];

        // A personal link carries a signed contact id, which is what lets the
        // review be called verified. Unsigned, missing or forged, and it is an
        // ordinary public review -- never an error, just a weaker claim.
        $contact = $this->invitedContact($id);

        echo View::render('reviews/page', [
            'title'   => trim((string) $account['name']) . ' reviews',
            'account' => $account,
            'summary' => HostedReviews::summary($id),
            'reviews' => HostedReviews::forAccount($id),
            'contact' => $contact,
            'error'   => $this->takeFlash('review_error'),
            'thanks'  => $this->takeFlash('review_thanks') !== null,
        ]);
    }

    /** POST /reviews/{slug} */
    public function submit(string $slug): void
    {
        $account = HostedReviews::bySlug($slug);
        if ($account === null) {
            http_response_code(404);
            echo View::page('errors/404', ['title' => 'Page not found']);
            return;
        }

        $back = '/reviews/' . rawurlencode($slug);

        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->fail($back, 'Your session expired. Please try again.');
        }

        // Hidden from people, irresistible to bots. Answered as success so a
        // bot learns nothing from being caught.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            $_SESSION['review_thanks'] = '1';
            Request::redirect($back);
        }

        if (HostedReviews::throttled()) {
            $this->fail($back, 'Too many reviews from this connection. Please try again later.');
        }

        $contact = $this->invitedContact((int) $account['id']);

        $result = HostedReviews::add([
            'account'  => (int) $account['id'],
            'contact'  => $contact === null ? null : (int) $contact['id'],
            'source'   => $contact === null ? 'public_link' : 'invited',
            'name'     => (string) ($_POST['author_name'] ?? ''),
            'email'    => (string) ($_POST['author_email'] ?? ''),
            'rating'   => (int) ($_POST['rating'] ?? 0),
            'body'     => (string) ($_POST['body'] ?? ''),
        ]);

        if (!$result['ok']) {
            $this->fail($back, (string) $result['error']);
        }

        $_SESSION['review_thanks'] = '1';
        Request::redirect($back);
    }

    /**
     * GET /widget/{slug}.js
     *
     * Plain JavaScript that writes the reviews into the host page. No build
     * step and no framework, because the people embedding this are pasting one
     * line into a website builder.
     *
     * It renders server-side into a string of HTML and hands that over, rather
     * than shipping a template and a JSON feed: one request, nothing to go
     * wrong on a slow connection, and no second endpoint to keep in step.
     */
    public function widget(string $file): void
    {
        $slug = preg_replace('/\.js$/', '', $file) ?? $file;
        $account = HostedReviews::bySlug($slug);

        header('Content-Type: application/javascript; charset=utf-8');
        // Cheap to serve and slightly stale is fine; a review that appears five
        // minutes late costs nobody anything.
        header('Cache-Control: public, max-age=300');

        if ($account === null) {
            echo "/* PromoMonster: no such review page. */\n";
            return;
        }

        $id = (int) $account['id'];
        $html = View::render('reviews/widget', [
            'account' => $account,
            'summary' => HostedReviews::summary($id),
            'reviews' => array_slice(HostedReviews::forAccount($id, 12), 0, 12),
            'pageUrl' => rtrim((string) Config::get('app_url', 'https://promomonster.com'), '/')
                . '/reviews/' . (string) $account['public_slug'],
        ]);

        // json_encode does the escaping, including the </script> sequence that
        // would otherwise end the host page's script block early.
        echo "(function(){\n"
            . "  var html = " . json_encode($html, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ";\n"
            . "  var target = document.getElementById('promomonster-reviews');\n"
            . "  if (!target) {\n"
            . "    target = document.createElement('div');\n"
            . "    target.id = 'promomonster-reviews';\n"
            . "    var s = document.currentScript;\n"
            . "    if (s && s.parentNode) { s.parentNode.insertBefore(target, s); }\n"
            . "    else { document.body.appendChild(target); }\n"
            . "  }\n"
            // A shadow root, because inline styles are not enough.
            //
            // Measured on a deliberately hostile host page: a theme rule of
            // div{background:none!important} strips the white card backgrounds
            // straight out of the inline styles, because !important in an
            // author stylesheet beats an inline declaration. Every
            // website-builder theme has rules like that.
            //
            // Nothing outside a shadow root can reach inside it, !important
            // included, so the reviews look the same on every site. Inherited
            // properties still cross the boundary, which is wanted: the font
            // comes from the host page so this reads as part of it.
            . "  var mount = target;\n"
            . "  if (target.attachShadow && !target.shadowRoot) {\n"
            . "    try { mount = target.attachShadow({ mode: 'open' }); } catch (e) { mount = target; }\n"
            . "  } else if (target.shadowRoot) {\n"
            . "    mount = target.shadowRoot;\n"
            . "  }\n"
            . "  mount.innerHTML = html;\n"
            . "})();\n";
    }

    // -- Internals ---------------------------------------------------------

    /**
     * The contact a personal link names, or null.
     *
     * Reuses the unsubscribe signer: same problem, same solution. A value in a
     * URL that has to be unforgeable without a session behind it.
     *
     * @return array<string,mixed>|null
     */
    private function invitedContact(int $accountId): ?array
    {
        $token = trim((string) ($_GET['c'] ?? $_POST['c'] ?? ''));
        if ($token === '' || !Tokens::configured()) {
            return null;
        }

        $contactId = Tokens::readUnsubscribe($token);
        if ($contactId === null) {
            return null;
        }

        // And it has to be one of THIS business's customers, or a valid token
        // for anybody would mark a review verified on anybody's page.
        return Database::first(
            'SELECT c.id, c.first_name, c.last_name, c.email
               FROM contacts c
               JOIN locations l ON l.id = c.location_id
              WHERE c.id = :id AND l.account_id = :account
              LIMIT 1',
            ['id' => $contactId, 'account' => $accountId],
        );
    }

    private function takeFlash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return is_string($value) ? $value : null;
    }

    private function fail(string $back, string $message): never
    {
        $_SESSION['review_error'] = $message;
        Request::redirect($back);
    }
}

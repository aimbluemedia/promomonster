<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Audit;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Database;
use App\Support\Request;
use App\Support\ToutifyAuth;
use App\Support\ToutifyEvents;
use App\Support\ToutifyJwt;
use App\Support\ToutifyMembers;
use App\Support\ToutifySessions;
use Throwable;

/**
 * Signing in through Toutify.
 *
 * Toutify holds the account and the password; PromoMonster holds the reviews.
 * Six routes: start a sign-in, come back from one, be sent here after sign-up,
 * sign out of both, be told by Toutify that a session ended, and be told by
 * Toutify that a member or a website changed.
 *
 * Two of them -- the webhook and the back-channel logout -- are called by
 * Toutify's SERVER, not by a browser. They have no session and no CSRF token,
 * and they must not have one: the signature on the body is the authorisation.
 */
final class ToutifyController
{
    /** Where the state, nonce and PKCE verifier wait for the member's return. */
    private const PENDING = 'toutify_pending';

    /* ------------------------------------------------------------ 1. login */

    public function login(): void
    {
        if (!ToutifyAuth::configured()) {
            $this->stop(503, 'Sign-in is not switched on yet.');
        }

        // Only the four hints Toutify documents, taken from the query string
        // and passed through. Anything else a visitor adds is dropped by
        // ToutifyAuth::begin(), which builds the URL from a fixed list.
        $hints = [];
        foreach (['login_hint', 'name_hint', 'business_name_hint', 'phone_hint'] as $hint) {
            $value = trim((string) ($_GET[$hint] ?? ''));
            if ($value !== '') {
                $hints[$hint] = mb_substr($value, 0, 200);
            }
        }

        try {
            $begin = ToutifyAuth::begin(!empty($_GET['signup']), $hints);
        } catch (Throwable $e) {
            error_log('Toutify login could not start: ' . $e->getMessage());
            $this->stop(502, 'We could not reach Toutify just now. Please try again in a minute.');
        }

        $_SESSION[self::PENDING] = [
            'state'    => $begin['state'],
            'nonce'    => $begin['nonce'],
            'verifier' => $begin['verifier'],
            'expires'  => time() + ToutifyAuth::PENDING_SECONDS,
        ];

        Request::redirect($begin['url']);
    }

    /* --------------------------------------------------------- 2. callback */

    public function callback(): void
    {
        $pending = $_SESSION[self::PENDING] ?? null;
        unset($_SESSION[self::PENDING]);

        if (!is_array($pending) || time() > (int) ($pending['expires'] ?? 0)) {
            // Usually a bookmarked callback or a very slow sign-up, not an
            // attack. Send them round again rather than showing an error.
            Request::redirect('/auth/toutify/login');
        }

        if (isset($_GET['error'])) {
            error_log('Toutify returned an error: ' . (string) $_GET['error']);
            $this->stop(400, 'Toutify could not sign you in. Please try again.');
        }

        $state = (string) ($_GET['state'] ?? '');
        if (!hash_equals((string) $pending['state'], $state)) {
            $this->stop(400, 'That sign-in link did not match. Please try again.');
        }

        $code = (string) ($_GET['code'] ?? '');
        if ($code === '') {
            $this->stop(400, 'Toutify did not send an authorisation code.');
        }

        try {
            $tokens = ToutifyAuth::exchange($code, (string) $pending['verifier']);
            $claims = ToutifyAuth::claims((string) $tokens['id_token'], (string) $pending['nonce']);

            // UserInfo is where the websites list and the fullest profile are.
            $info = ToutifyAuth::userInfo(
                (string) ($tokens['access_token'] ?? ''),
                (string) $claims['sub'],
            );
        } catch (Throwable $e) {
            error_log('Toutify callback failed: ' . $e->getMessage());
            $this->stop(400, 'We could not confirm that sign-in with Toutify. Please try again.');
        }

        $status = (string) ($info['account_status'] ?? 'active');
        if ($status !== 'active') {
            // Suspended or closed on Toutify means no session here either.
            Audit::log('member.refused_' . $status, 'user', null, null, ['sub' => $claims['sub']]);
            $this->stop(403, 'That Toutify account is not active. Please sort it out on Toutify and try again.');
        }

        try {
            $userId = ToutifyMembers::upsert($info);
            $websites = $info['websites'] ?? [];
            ToutifyMembers::syncWebsites((string) $info['sub'], is_array($websites) ? $websites : []);
        } catch (Throwable $e) {
            error_log('Toutify member could not be saved: ' . $e->getMessage());
            $this->stop(500, 'We signed you in with Toutify but could not set your account up here.');
        }

        Auth::signIn($userId, 'auth.toutify_login');

        // AFTER signIn, which regenerates the session id: recording the old one
        // would leave back-channel logout unable to find this session.
        $_SESSION['toutify'] = [
            'sub'      => (string) $info['sub'],
            'sid'      => (string) ($claims['sid'] ?? ''),
            'id_token' => (string) $tokens['id_token'],
        ];

        ToutifySessions::record(session_id(), (string) ($claims['sid'] ?? ''), (string) $info['sub']);

        Request::redirect('/members');
    }

    /* -------------------------------------------------------- 3. initiate */

    /**
     * Where Toutify sends a member after sign-up, already logged in there.
     *
     * The iss check is the whole guard: it makes this a link only Toutify can
     * usefully send, rather than an open redirect into the sign-in flow.
     */
    public function initiate(): void
    {
        $iss = (string) ($_GET['iss'] ?? '');

        if (!hash_equals(ToutifyAuth::issuer(), $iss)) {
            $this->stop(400, 'That sign-in link did not come from Toutify.');
        }

        Request::redirect('/auth/toutify/login');
    }

    /* ---------------------------------------------------------- 4. logout */

    public function logout(): void
    {
        $toutify  = $_SESSION['toutify'] ?? [];
        $idToken  = is_array($toutify) ? (string) ($toutify['id_token'] ?? '') : '';
        $sessionId = session_id();

        ToutifySessions::forget($sessionId);
        Auth::logout();

        try {
            Request::redirect(ToutifyAuth::endSessionUrl($idToken === '' ? null : $idToken));
        } catch (Throwable $e) {
            // Their session here is already gone, which is the part that
            // matters; Toutify's will time out on its own.
            error_log('Toutify end_session unavailable: ' . $e->getMessage());
            Request::redirect('/');
        }
    }

    /* ---------------------------------------- 5. back-channel logout (POST) */

    public function backchannelLogout(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        $token = (string) ($_POST['logout_token'] ?? '');
        if ($token === '') {
            $this->plain(400, 'no logout_token');
        }

        try {
            $claims = ToutifyJwt::decode($token, ToutifyAuth::keys());
            ToutifyJwt::checkTimes($claims);
            $this->checkLogoutToken($claims, $token);
        } catch (Throwable $e) {
            error_log('Toutify logout token rejected: ' . $e->getMessage());
            $this->plain(400, 'invalid');
        }

        $sub = (string) ($claims['sub'] ?? '');
        $sid = (string) ($claims['sid'] ?? '');

        // 200 even when nothing matched: the member may simply never have
        // signed in here, and an error would have Toutify retrying forever.
        $ended = ToutifySessions::endFor($sub, $sid === '' ? null : $sid);

        $this->plain(200, 'ok ' . $ended);
    }

    /**
     * The rules a logout token must satisfy beyond its signature.
     *
     * @param array<string,mixed> $claims
     */
    private function checkLogoutToken(array $claims, string $token): void
    {
        if ((string) ($claims['iss'] ?? '') !== ToutifyAuth::issuer()) {
            throw new \RuntimeException('wrong issuer');
        }

        $aud = $claims['aud'] ?? '';
        $aud = is_array($aud) ? $aud : [$aud];
        if (!in_array(ToutifyAuth::clientId(), array_map('strval', $aud), true)) {
            throw new \RuntimeException('wrong audience');
        }

        // typ lives in the header, so it is read from the token itself.
        $header = json_decode(ToutifyJwt::base64(explode('.', $token)[0]), true);
        if (!is_array($header) || strtolower((string) ($header['typ'] ?? '')) !== 'logout+jwt') {
            throw new \RuntimeException('not a logout+jwt');
        }

        $events = $claims['events'] ?? null;
        if (!is_array($events)
            || !array_key_exists('http://schemas.openid.net/event/backchannel-logout', $events)) {
            throw new \RuntimeException('no backchannel-logout event');
        }

        // A nonce would mean this is an ID token being replayed as a logout.
        if (isset($claims['nonce'])) {
            throw new \RuntimeException('logout token carries a nonce');
        }

        if (($claims['sub'] ?? '') === '' && ($claims['sid'] ?? '') === '') {
            throw new \RuntimeException('neither sub nor sid');
        }
    }

    /* --------------------------------------------------- 6. webhook (POST) */

    public function webhook(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        // The raw bytes, before anything re-encodes them: the signature covers
        // exactly what was sent.
        $raw = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_TOUTIFY_SIGNATURE'] ?? null;

        $why = ToutifyEvents::verify(
            $raw,
            is_string($signature) ? $signature : null,
            ToutifyAuth::webhookSecret(),
        );

        if ($why !== null) {
            error_log('Toutify webhook rejected: ' . $why);
            $this->plain(400, 'no');
        }

        $event = json_decode($raw, true);
        if (!is_array($event)) {
            $this->plain(400, 'expected JSON');
        }

        $id   = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if (!ToutifyEvents::firstSighting($id, $type)) {
            $this->plain(200, 'already handled');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // One event, one transaction. website.joined can create a member
            // AND a website; without this, a failure on the second leaves the
            // first behind and the retry sees a half-applied event. The spec
            // asks for a rollback and this is it.
            $note = $this->applyEvent($type, $event);
            $pdo->commit();

            // Outside the transaction: tidying up must never roll back an
            // event that applied cleanly.
            ToutifyEvents::prune();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // The event id was written before the work, so a retry would be
            // dropped as a duplicate unless it is forgotten again.
            ToutifyEvents::forget($id);
            error_log('Toutify webhook ' . $id . ' (' . $type . ') failed: ' . $e->getMessage());
            $this->plain(500, 'retry');
        }

        $this->plain(200, $note);
    }

    /**
     * @param  array<string,mixed> $event
     * @return string a line for the log
     */
    private function applyEvent(string $type, array $event): string
    {
        $member  = is_array($event['member'] ?? null) ? $event['member'] : null;
        $website = is_array($event['website'] ?? null) ? $event['website'] : null;

        switch ($type) {
            case 'member.updated':
            case 'member.reactivated':
            case 'member.suspended':
            case 'member.closed':
                if ($member === null) {
                    return 'no member on ' . $type;
                }
                $sub = (string) ($member['sub'] ?? '');
                ToutifyMembers::apply($sub, $member);

                // Suspended or closed means they stop being signed in here too.
                if (in_array((string) ($member['account_status'] ?? ''), ['suspended', 'closed'], true)) {
                    ToutifySessions::endFor($sub);
                }

                return $type . ' for ' . $sub;

            case 'member.deleted':
                $sub = (string) ($member['sub'] ?? '');
                ToutifySessions::endFor($sub);
                ToutifyMembers::markDeleted($sub);

                // Their reviews and requests are left alone on purpose. Removing
                // a business's content because an account closed is not
                // something to do without being asked.
                return 'marked ' . $sub . ' deleted, content left in place';

            case 'website.joined':
            case 'website.updated':
                if ($website === null) {
                    return 'no website on ' . $type;
                }
                // The owner may be somebody we have never seen.
                if ($member !== null && ToutifyMembers::bySub((string) ($member['sub'] ?? '')) === null) {
                    ToutifyMembers::upsert($member);
                } elseif ($member !== null) {
                    ToutifyMembers::apply((string) ($member['sub'] ?? ''), $member);
                }

                ToutifyMembers::saveWebsite($website + ['status' => 'active']);

                return $type . ' for ' . (string) ($website['id'] ?? '?');

            case 'website.removed':
            case 'website.deleted':
                $id = (string) ($website['id'] ?? ($event['id'] ?? ''));
                if (is_array($website) && isset($website['id'])) {
                    ToutifyMembers::removeWebsite((string) $website['id']);
                }

                return $type . ' for ' . $id;

            default:
                // site.test lands here, and so does anything Toutify adds later.
                return 'ignored ' . ($type === '' ? 'event with no type' : $type);
        }
    }

    /* -------------------------------------------------------------- helpers */

    private function plain(int $status, string $body): never
    {
        http_response_code($status);
        echo $body, "\n";
        exit;
    }

    private function stop(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Sign in</title>'
           . '<div style="font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem">'
           . '<h1 style="font-size:1.25rem">' . htmlspecialchars($message, ENT_QUOTES) . '</h1>'
           . '<p><a href="/">Back to PromoMonster</a></p></div>';
        exit;
    }
}

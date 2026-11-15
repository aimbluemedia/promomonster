<?php

declare(strict_types=1);

/**
 * Finds a syntax error in app/config.php while the whole site is down.
 *
 * When config.php has a syntax error, every single request dies -- including
 * diagnose.php, because a parse error in a required file is a COMPILE error,
 * not an exception, so no try/catch anywhere can survive it. That is the state
 * this file is for: the one page that still answers when nothing else does.
 *
 * It never includes or executes config.php. It reads the bytes and hands them
 * to token_get_all() with TOKEN_PARSE, which throws a catchable ParseError and
 * names the line -- the same answer `php -l` would give, from a browser.
 *
 * IT NEVER PRINTS A VALUE. This file sits on a public URL with no login in
 * front of it, and config.php holds the database password, the Stripe secret
 * key and app_key. So every value is reduced to its type and length before it
 * reaches the screen: you get "line 191: 'secret_key' => string(107)", which is
 * enough to spot a key that got truncated or a block pasted in the wrong place,
 * and useless to anybody else.
 *
 * DELETE IT FROM THE SERVER once the site is back, like diagnose.php and the
 * other tools in here. It tells an attacker your file layout for free.
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$base = __DIR__;

// Both layouts: project-as-docroot (this file beside app/) and a real
// public/ docroot (this file inside it).
$candidates = [$base . '/app/config.php', dirname($base) . '/app/config.php'];
$path = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $path = $candidate;
        break;
    }
}

/**
 * PHP's own error message, with any content stripped out of it.
 *
 * Necessary, not paranoid: PHP quotes the offending token in its message, so a
 * config truncated mid-value produces `unexpected token "'sk_live_51Hx..."` --
 * and printing that verbatim on a public page hands over the start of the
 * secret key. The first version of this file did exactly that, and a canary
 * value in a test config is what caught it.
 *
 * Allow-list, not block-list. A quoted token survives only if it has no
 * alphanumerics at all (so `=>`, `]`, `,` come through, which is most of what
 * is useful) or it is one of the handful of words PHP actually uses in these
 * messages. Everything else becomes a placeholder, because anything else COULD
 * be a fragment of the file.
 */
function saferMessage(string $message): string
{
    $allowed = [
        'end of file', 'return', 'array', 'function', 'variable', 'identifier',
        'string', 'integer', 'double', 'declare', 'namespace', 'use', 'const',
        'class', 'new', 'null', 'true', 'false', 'if', 'else', 'foreach', 'echo',
    ];

    return (string) preg_replace_callback(
        '/"([^"]*)"/',
        static function (array $m) use ($allowed): string {
            $token = $m[1];

            if (preg_match('/[A-Za-z0-9]/', $token) !== 1) {
                return '"' . $token . '"';
            }
            if (in_array(strtolower($token), $allowed, true)) {
                return '"' . $token . '"';
            }
            if (preg_match('/^T_[A-Z_]+$/', $token) === 1) {
                return '"' . $token . '"';
            }

            return '(something from the file, ' . strlen($token) . ' characters, not shown here)';
        },
        $message,
    );
}

/** A value reduced to something safe to show. Never the value itself. */
function shape(string $value): string
{
    $value = trim($value);
    $value = rtrim($value, ',');

    if ($value === '') {
        return '';
    }
    if ($value === '[' || $value === '[]') {
        return $value;
    }
    if (in_array(strtolower($value), ['null', 'true', 'false'], true)) {
        return strtolower($value);
    }
    if (is_numeric($value)) {
        return 'number';
    }

    $quoted = (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'")
        || (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"');

    if ($quoted) {
        $inner = strlen($value) - 2;

        return $inner === 0 ? "'' (empty)" : 'string(' . $inner . ')';
    }

    return 'expression';
}

/**
 * Which brackets were opened and never closed.
 *
 * The reason this exists: when a `[` is never closed, PHP reads to the end of
 * the file before giving up, so it reports the LAST line -- which is the one
 * line that is certainly not the problem. "Syntax error on line 50" on a
 * 50-line file means "something above here never closed", and that is all it
 * means. This finds the opener.
 *
 * token_get_all WITHOUT TOKEN_PARSE, on purpose: it does not throw, so it
 * still tokenises a file PHP refuses to compile. Brackets inside strings and
 * comments arrive as part of one string or comment token, never as the
 * single-character tokens counted here, so they cannot throw the count off --
 * which is the whole reason this is not done by counting characters.
 *
 * @return array{unclosed:list<array{char:string,line:int}>, extra:list<array{char:string,line:int}>}
 */
function brackets(string $source): array
{
    $tokens = @token_get_all($source);
    $stack  = [];
    $extra  = [];
    $pairs  = [']' => '[', ')' => '(', '}' => '{'];

    // A line number has to be tracked by hand: token_get_all attaches one only
    // to array tokens, so a bare "[" carries none. Counting the newlines inside
    // each array token keeps the running line correct across a long comment.
    $line = 1;

    foreach ($tokens as $token) {
        if (is_array($token)) {
            $line = (int) $token[2];
            $line += substr_count((string) $token[1], "\n");
            continue;
        }

        if (in_array($token, ['[', '(', '{'], true)) {
            $stack[] = ['char' => $token, 'line' => $line];
            continue;
        }

        if (isset($pairs[$token])) {
            $top = array_pop($stack);
            if ($top === null) {
                $extra[] = ['char' => $token, 'line' => $line];
            }
        }
    }

    return ['unclosed' => array_values($stack), 'extra' => $extra];
}

/**
 * Where a missing closer most likely belongs, read off the indentation.
 *
 * Bracket counting alone always blames the OUTERMOST unclosed bracket, which
 * on a config file is `return [` on line 5 -- true, and useless, because the
 * line nobody typed wrong is the one it names. The human mistake is wherever a
 * `],` went missing, and the evidence for that is indentation: the author
 * indented the next key as though the array had closed, so the line where the
 * indentation disagrees with the bracket depth is the line the closer belongs
 * before.
 *
 * A heuristic, and labelled as one on screen. A file indented inconsistently,
 * or with two spaces instead of four, will point somewhere unhelpful -- so it
 * says "most likely" and the bracket count stays the authority.
 *
 * @return list<array{line:int, short:int}>
 */
function misindented(string $source): array
{
    $tokens = @token_get_all($source);
    $lines  = explode("\n", $source);

    // Bracket depth at the START of each line.
    $depthAt = [];
    $depth   = 0;
    $line    = 1;
    $depthAt[1] = 0;

    foreach ($tokens as $token) {
        if (is_array($token)) {
            $text = (string) $token[1];
            $line = (int) $token[2];
            foreach (range(0, max(0, substr_count($text, "\n") - 1)) as $i) {
                $depthAt[$line + $i + 1] = $depth;
            }
            $line += substr_count($text, "\n");
            $depthAt[$line] ??= $depth;
            continue;
        }

        if (in_array($token, ['[', '(', '{'], true)) {
            $depth++;
        } elseif (in_array($token, [']', ')', '}'], true)) {
            $depth = max(0, $depth - 1);
        }
        $depthAt[$line] ??= $depth;
    }

    $found = [];

    foreach ($lines as $i => $raw) {
        $n    = $i + 1;
        $bare = trim($raw);

        // Only lines that are plainly a key in an array. Comments, blanks and
        // continuations have no reliable indentation contract.
        if (preg_match("/^('[^']*'|\"[^\"]*\")\s*=>/", $bare) !== 1) {
            continue;
        }
        if (!isset($depthAt[$n])) {
            continue;
        }

        $spaces     = strlen(str_replace("\t", '    ', $raw)) - strlen(ltrim($raw));
        $byIndent   = intdiv($spaces, 4);
        $byBrackets = $depthAt[$n];

        if ($byIndent < $byBrackets) {
            $found[] = ['line' => $n, 'short' => $byBrackets - $byIndent];
        }
    }

    return $found;
}

$problems = [];
$notes    = [];

if ($path === null) {
    $problems[] = 'app/config.php was not found next to this file or one level up. '
        . 'If you are looking at a blank or broken site, that alone is the reason: '
        . 'copy app/config.example.php to app/config.php and fill it in.';
    $source = '';
} else {
    $source = (string) file_get_contents($path);
}

if ($source !== '') {
    // --- The things a paste does to a file ------------------------------
    if (str_contains($source, '```')) {
        $problems[] = 'The file contains ``` backticks. Those are markdown fences from '
            . 'wherever the block was copied, not PHP. Delete every line that is only '
            . 'backticks.';
    }
    if (str_starts_with($source, "\xEF\xBB\xBF")) {
        $problems[] = 'The file starts with a UTF-8 byte-order mark before <?php. Some '
            . 'editors add it. Re-save as "UTF-8 without BOM".';
    }
    if (!str_starts_with(ltrim($source), '<?php')) {
        $problems[] = 'The file does not begin with <?php.';
    }
    if (!str_contains($source, 'return')) {
        $problems[] = 'There is no `return` in the file. config.php must return an array.';
    }
    if (preg_match('/\?>\s*\S/', $source) === 1) {
        $notes[] = 'There is content after a closing ?> tag.';
    }

    // --- The actual answer ----------------------------------------------
    //
    // TOKEN_PARSE makes token_get_all throw a catchable ParseError instead of
    // emitting a warning, which is the whole trick: the same verdict php -l
    // gives, from a page that is still standing.
    try {
        token_get_all($source, TOKEN_PARSE);
        $parse = null;
    } catch (ParseError $e) {
        $parse = $e;
    } catch (Throwable $e) {
        $parse = $e;
    }
}

$lines = $source === '' ? [] : explode("\n", $source);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Config check</title>
<style>
  :root { color-scheme: light dark; }
  body { font: 15px/1.6 ui-sans-serif, system-ui, sans-serif; margin: 0; padding: 2rem 1rem;
         max-width: 56rem; margin-inline: auto; }
  h1 { font-size: 1.3rem; margin: 0 0 .3rem; }
  .sub { color: #666; margin: 0 0 1.5rem; font-size: .92rem; }
  .box { border: 1px solid currentColor; border-left-width: 5px; border-radius: 8px;
         padding: .9rem 1.1rem; margin-bottom: 1rem; }
  .bad { border-color: #b3261e; }
  .good { border-color: #1b7f4b; }
  .note { border-color: #9a6700; }
  code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .88rem; }
  table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
  td, th { text-align: left; padding: .18rem .6rem .18rem 0; vertical-align: top;
           border-bottom: 1px solid rgba(128,128,128,.25); }
  td.n { text-align: right; color: #888; width: 4rem; font-family: ui-monospace, monospace; }
  tr.hit td { background: rgba(179,38,30,.14); font-weight: 600; }
  .k { font-family: ui-monospace, monospace; }
  .v { color: #666; }
</style>
</head><body>

<h1>Config check</h1>
<p class="sub">
  Reads <code>app/config.php</code> as text and reports where it stops being valid PHP.
  It never runs the file and <strong>never prints a value</strong> &mdash; only types and
  lengths. Delete this file from the server when you are done.
</p>

<?php foreach ($problems as $problem): ?>
  <div class="box bad"><strong>Problem.</strong> <?= htmlspecialchars($problem, ENT_QUOTES) ?></div>
<?php endforeach; ?>

<?php if ($path !== null): ?>
  <div class="box">
    <strong>The file</strong><br>
    <code><?= htmlspecialchars($path, ENT_QUOTES) ?></code><br>
    <?= number_format(strlen($source)) ?> bytes, <?= count($lines) ?> lines,
    last changed <?= date('j M Y H:i', (int) filemtime($path)) ?>
    <?php if (!is_readable($path)): ?>
      <br><strong>It is not readable by PHP.</strong> That alone would take the site down.
    <?php endif; ?>
  </div>

  <?php if (($parse ?? null) === null): ?>
    <div class="box good">
      <strong>config.php is valid PHP.</strong>
      <p>So a site-wide 500 is not coming from a syntax error in here. Next most likely,
        in order: the database credentials in this file are wrong or the database is
        down (every page reads it); <code>app/bootstrap.php</code> or another file was
        half-uploaded; or file permissions changed. Check your host's PHP error log
        &mdash; hPanel &rarr; Advanced &rarr; PHP Configuration, or an
        <code>error_log</code> file next to this one &mdash; and read the last line.</p>
    </div>
  <?php else: ?>
    <?php $badLine = (int) $parse->getLine(); ?>
    <div class="box bad">
      <strong>Syntax error on line <?= $badLine ?>.</strong>
      <p><code><?= htmlspecialchars(saferMessage($parse->getMessage()), ENT_QUOTES) ?></code></p>
      <p>PHP reports where it gave up, which is often just after the real mistake &mdash;
        so look at the lines just above <?= $badLine ?> too. The usual cause is a block
        pasted after the final <code>];</code> instead of before it, or a missing comma
        on the line before the new entry.</p>
    </div>

    <?php
      $b = brackets($source);
      $atEnd = $badLine >= count($lines) - 1;
    ?>
    <?php if ($b['unclosed'] !== []): ?>
      <div class="box bad">
        <strong>You are <?= count($b['unclosed']) ?>
          closing bracket<?= count($b['unclosed']) === 1 ? '' : 's' ?> short.</strong>
        <p>
          <?php foreach ($b['unclosed'] as $open): ?>
            The <code><?= htmlspecialchars($open['char'], ENT_QUOTES) ?></code> opened on
            <strong>line <?= $open['line'] ?></strong> is never closed.<br>
          <?php endforeach; ?>
        </p>
        <?php $where = misindented($source); ?>
        <?php if ($where !== []): ?>
          <p><strong>Most likely it belongs just before line <?= $where[0]['line'] ?>.</strong>
            That line is indented as though
            <?= $where[0]['short'] === 1 ? 'an array had' : $where[0]['short'] . ' arrays had' ?>
            already closed, but <?= $where[0]['short'] === 1 ? 'it has' : 'they have' ?> not
            &mdash; so put
            <code><?= str_repeat('],', $where[0]['short']) ?></code> on its own line above it,
            at the indentation of the block it closes.
            <br><span class="v">(Read off the indentation, so treat it as a strong hint
            rather than a fact &mdash; the bracket count above is the part that is certain.)</span></p>
        <?php endif; ?>
        <?php if ($atEnd): ?>
          <p>This is why the error is reported on the last line of the file: PHP read all
            the way to the end still looking for
            <?= count($b['unclosed']) === 1 ? 'that bracket' : 'those brackets' ?>, so the
            line it names is the one line that is certainly <em>not</em> the mistake.
            Add the missing
            <code><?= count($b['unclosed']) === 1 ? '],' : ']' ?></code>
            at the right nesting level and the file closes.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($b['extra'] !== []): ?>
      <div class="box bad">
        <strong>There <?= count($b['extra']) === 1 ? 'is' : 'are' ?>
          <?= count($b['extra']) ?> closing bracket<?= count($b['extra']) === 1 ? '' : 's' ?>
          too many.</strong>
        <p><?php foreach ($b['extra'] as $close): ?>
            The <code><?= htmlspecialchars($close['char'], ENT_QUOTES) ?></code> on
            <strong>line <?= $close['line'] ?></strong> closes something that was never
            opened.<br>
          <?php endforeach; ?></p>
      </div>
    <?php endif; ?>

    <h2 style="font-size:1.05rem;">Around line <?= $badLine ?></h2>
    <table>
      <?php
      $from = max(1, $badLine - 8);
      $to   = min(count($lines), $badLine + 4);
      for ($n = $from; $n <= $to; $n++):
          $line = $lines[$n - 1] ?? '';
          // Keys are shown; values are reduced. A line that is not a key/value
          // pair is shown as its structure only, never its text.
          if (preg_match("/^(\s*)('[^']*'|\"[^\"]*\")\s*=>(.*)$/", $line, $m) === 1) {
              $shown = str_repeat('&nbsp;', strlen(str_replace("\t", '    ', $m[1])))
                     . '<span class="k">' . htmlspecialchars($m[2], ENT_QUOTES) . ' =&gt; </span>'
                     . '<span class="v">' . htmlspecialchars(shape($m[3]), ENT_QUOTES) . '</span>';
          } else {
              $pad  = str_repeat('&nbsp;', strlen(str_replace("\t", '    ', $line)) - strlen(ltrim($line)));
              $bare = trim($line);
              // Structural punctuation and comment markers are safe to show
              // literally; anything else is only measured.
              $shown = $bare === '' || preg_match('/^[\[\]\(\),;]+$/', $bare) === 1
                     || str_starts_with($bare, '//') || str_starts_with($bare, '/*')
                     || str_starts_with($bare, '*') || $bare === '<?php'
                     || str_starts_with($bare, 'return') || str_starts_with($bare, 'declare')
                  ? $pad . '<span class="v">' . htmlspecialchars($bare, ENT_QUOTES) . '</span>'
                  : $pad . '<span class="v">(' . strlen($bare) . ' characters)</span>';
          }
      ?>
        <tr class="<?= $n === $badLine ? 'hit' : '' ?>">
          <td class="n"><?= $n ?></td><td><?= $shown ?></td>
        </tr>
      <?php endfor; ?>
    </table>
  <?php endif; ?>

  <h2 style="font-size:1.05rem;margin-top:1.6rem;">The last 12 lines</h2>
  <p class="sub" style="margin-bottom:.4rem;">
    A correct file ends with the closing <code>];</code> of the returned array and nothing
    after it. Anything below that line is the bug.
  </p>
  <table>
    <?php
    $start = max(1, count($lines) - 11);
    for ($n = $start; $n <= count($lines); $n++):
        $raw  = $lines[$n - 1] ?? '';
        $pad  = str_repeat('&nbsp;', strlen(str_replace("\t", '    ', $raw)) - strlen(ltrim($raw)));
        $bare = trim($raw);
        $safe = $bare === '' || preg_match('/^[\[\]\(\),;]+$/', $bare) === 1
             || str_starts_with($bare, '//') || str_starts_with($bare, '*')
             || str_starts_with($bare, '/*');
    ?>
      <tr><td class="n"><?= $n ?></td>
        <td><?= $pad ?><span class="v"><?= $safe
            ? htmlspecialchars($bare, ENT_QUOTES)
            : '(' . strlen($bare) . ' characters)' ?></span></td></tr>
    <?php endfor; ?>
  </table>
<?php endif; ?>

<?php foreach ($notes as $note): ?>
  <div class="box note" style="margin-top:1rem;"><?= htmlspecialchars($note, ENT_QUOTES) ?></div>
<?php endforeach; ?>

</body></html>

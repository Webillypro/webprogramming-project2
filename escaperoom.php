<?php
require_once __DIR__ . '/includes/auth.php';      // starts the session, provides h(), csrf and login helpers
require_once __DIR__ . '/includes/scores.php';

// Session check: nobody gets into the room without being logged in.
require_login();

// First visit after logging in (or after logging in again): start the game and the timer.
if (empty($_SESSION['start_time'])) {
    game_init();
}
if (!empty($_SESSION['end_time'])) {
    header('Location: leaderboard.php');
    exit;
}

if (!isset($_SESSION['inventory']) || !is_array($_SESSION['inventory'])) $_SESSION['inventory'] = [];
if (!isset($_SESSION['solved'])    || !is_array($_SESSION['solved']))    $_SESSION['solved']    = [];
if (!isset($_SESSION['hints_used'])) $_SESSION['hints_used'] = 0;
$ANSWERS = [
    'shelf'    => 'echo',   // riddle on the bookshelf
    'cipher'   => 'five',   // Caesar-shift note inside the desk drawer
    'painting' => '6',      // (gulls - ships) * lighthouses = (6-3)*2
];
$DIGITS = [
    'shelf'    => '4',
    'cipher'   => '5',
    'painting' => '6',
];
$VAULT_ORDER = ['shelf', 'cipher', 'painting'];

$VIEWS = ['room', 'rug', 'bookshelf', 'desk', 'painting', 'vault'];
function flash(string $msg): void { $_SESSION['flash'] = $msg; }

function go(string $view): void {
    header('Location: escaperoom.php?view=' . urlencode($view));
    exit;
}

function has_item(string $item): bool { return in_array($item, $_SESSION['inventory'], true); }
function add_item(string $item): void {
    if (!has_item($item)) $_SESSION['inventory'][] = $item;
}
function clean(string $s): string { return strtolower(trim($s)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $view   = $_POST['view'] ?? 'room';
    if (!in_array($view, $VIEWS, true)) $view = 'room';

    switch ($action) {

        case 'take_key':
            if (has_item('brass key')) {
                flash('You already have the brass key.');
            } else {
                add_item('brass key');
                flash('You slip the small brass key into your pocket.');
            }
            go('rug');

        case 'use_key':
            if (!has_item('brass key')) {
                flash('The drawer is locked. You need a key.');
            } elseif (!empty($_SESSION['solved']['drawer'])) {
                flash('The drawer is already open.');
            } else {
                $_SESSION['solved']['drawer'] = true;
                flash('Click! The key turns and the drawer slides open.');
            }
            go('desk');

        case 'answer':
            $puzzle = $_POST['puzzle'] ?? '';
            $given  = clean($_POST['answer'] ?? '');
            if (!isset($ANSWERS[$puzzle])) go('room');

            if ($puzzle === 'cipher' && empty($_SESSION['solved']['drawer'])) {
                flash('The drawer is still locked.');
                go('desk');
            }

            $backTo = ['shelf' => 'bookshelf', 'cipher' => 'desk', 'painting' => 'painting'][$puzzle];

            if (!empty($_SESSION['solved'][$puzzle])) {
                flash('You already solved this one.');
            } elseif ($given === $ANSWERS[$puzzle]) {
                $_SESSION['solved'][$puzzle] = true;
                flash('Correct! You discover the digit ' . $DIGITS[$puzzle] . '. Write it down.');
            } else {
                flash('Nothing happens. That is not right.');
            }
            go($backTo);

        case 'hint':
            $_SESSION['hints_used']++;
            $hint = 'Look around the room carefully.';
            if (!has_item('brass key')) {
                $hint = 'Something shiny might be hidden where people wipe their feet.';
            } elseif (empty($_SESSION['solved']['drawer'])) {
                $hint = 'That brass key must fit the desk drawer.';
            } elseif (empty($_SESSION['solved']['shelf'])) {
                $hint = 'The riddle on the bookshelf speaks, but never has a mouth. Think of what repeats you.';
            } elseif (empty($_SESSION['solved']['cipher'])) {
                $hint = 'Each letter in the note was shifted forward by 3. Shift each one back.';
            } elseif (empty($_SESSION['solved']['painting'])) {
                $hint = 'Count the gulls and the ships, subtract, then multiply by the lighthouses.';
            } else {
                $hint = 'You have all three digits. Try them in order at the vault.';
            }
            flash('HINT: ' . $hint . ' (-100 points)');
            go($view);

        case 'vault_code':
            $given = preg_replace('/\D/', '', $_POST['code'] ?? '');

            $real = '';
            foreach ($VAULT_ORDER as $p) $real .= $DIGITS[$p];

            if ($given !== '' && hash_equals($real, $given)) {
                $_SESSION['end_time']     = time();
                $elapsed                  = $_SESSION['end_time'] - (int)$_SESSION['start_time'];
                $score                    = max(0, 1000 - $elapsed - ((int)$_SESSION['hints_used'] * 100));
                $_SESSION['elapsed_time'] = $elapsed;
                $_SESSION['score']        = $score;

                if (!save_score(current_username(), $score, $elapsed, (int)$_SESSION['hints_used'])) {
                    $_SESSION['score_save_failed'] = true;   // leaderboard.php warns the player
                }

                header('Location: leaderboard.php');
                exit;
            }
            flash('The vault buzzes angrily. Wrong code.');
            go('vault');
    }

    go($view);
}

$view = $_GET['view'] ?? 'room';
if (!in_array($view, $VIEWS, true)) $view = 'room';

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$elapsedNow = time() - (int)$_SESSION['start_time'];
$mins = floor($elapsedNow / 60);
$secs = $elapsedNow % 60;
$foundDigits = [];
foreach ($VAULT_ORDER as $p) {
    if (!empty($_SESSION['solved'][$p])) $foundDigits[$p] = $DIGITS[$p];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escape Room</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">

    <header>
        <h1>The Locked Study</h1>
        <p>
            Player: <strong><?= h(current_username()) ?></strong> |
            Time: <?= $mins ?>m <?= $secs ?>s (refreshes when you act) |
            Hints used: <?= (int)$_SESSION['hints_used'] ?>
        </p>
    </header>

    <?php if ($flash !== ''): ?>
        <div class="message"><?= h($flash) ?></div>
    <?php endif; ?>

    <main>
    <?php if ($view === 'room'): ?>

        <h2>The Room</h2>
        <p>You wake up in a dusty study. The door is bolted and a heavy vault sits in the corner.
           Something in here must reveal the code. Click an object to examine it.</p>
        <nav class="objects">
            <a class="btn" href="?view=rug">Worn Rug</a>
            <a class="btn" href="?view=bookshelf">Bookshelf</a>
            <a class="btn" href="?view=desk">Oak Desk</a>
            <a class="btn" href="?view=painting">Painting</a>
            <a class="btn" href="?view=vault">Vault</a>
        </nav>

    <?php elseif ($view === 'rug'): ?>

        <h2>Worn Rug</h2>
        <p>The rug is frayed at one corner. Something glints beneath it.</p>
        <?php if (!has_item('brass key')): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="take_key">
                <input type="hidden" name="view" value="rug">
                <button type="submit" class="btn">Take the small brass key</button>
            </form>
        <?php else: ?>
            <p><em>Nothing else is under the rug.</em></p>
        <?php endif; ?>

    <?php elseif ($view === 'bookshelf'): ?>

        <h2>Bookshelf</h2>
        <p>Most of the books are crumbling, but one slip of paper is tucked between two spines:</p>
        <blockquote>
            "I speak without a mouth and hear without ears. I have no body, but I come alive with wind.
            Call out and I repeat you. What am I?"
        </blockquote>
        <?php if (!empty($_SESSION['solved']['shelf'])): ?>
            <p class="good">Solved! The digit behind the slip is <strong><?= h($DIGITS['shelf']) ?></strong>.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="answer">
                <?= csrf_field() ?>
                <input type="hidden" name="puzzle" value="shelf">
                <input type="hidden" name="view" value="bookshelf">
                <label>Your answer: <input type="text" name="answer" autocomplete="off" required></label>
                <button type="submit" class="btn">Answer</button>
            </form>
        <?php endif; ?>

    <?php elseif ($view === 'desk'): ?>

        <h2>Oak Desk</h2>
        <?php if (empty($_SESSION['solved']['drawer'])): ?>
            <p>A tidy desk with a single locked drawer. A tiny brass lock is set into the front.</p>
            <?php if (has_item('brass key')): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="use_key">
                    <input type="hidden" name="view" value="desk">
                    <button type="submit" class="btn">Use the brass key</button>
                </form>
            <?php else: ?>
                <p><em>You have nothing that fits the lock.</em></p>
            <?php endif; ?>
        <?php else: ?>
            <p>Inside the open drawer is a diary page. The last line is scrawled in odd letters:</p>
            <blockquote>"My lucky number is spelled <strong>ILYH</strong>. (Each letter was pushed 3 steps forward.)"</blockquote>
            <?php if (!empty($_SESSION['solved']['cipher'])): ?>
                <p class="good">Solved! The digit is <strong><?= h($DIGITS['cipher']) ?></strong>.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="answer">
                    <?= csrf_field() ?>
                    <input type="hidden" name="puzzle" value="cipher">
                    <input type="hidden" name="view" value="desk">
                    <label>What does it say? <input type="text" name="answer" autocomplete="off" required></label>
                    <button type="submit" class="btn">Answer</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($view === 'painting'): ?>

        <h2>Painting</h2>
        <p>A seaside scene: <strong>2 lighthouses</strong>, <strong>3 ships</strong> on the water, and
           <strong>6 gulls</strong> overhead. A small plaque reads:</p>
        <blockquote>"Take the gulls, remove the ships, then multiply by the lighthouses."</blockquote>
        <?php if (!empty($_SESSION['solved']['painting'])): ?>
            <p class="good">Solved! The digit behind the frame is <strong><?= h($DIGITS['painting']) ?></strong>.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="action" value="answer">
                <?= csrf_field() ?>
                <input type="hidden" name="puzzle" value="painting">
                <input type="hidden" name="view" value="painting">
                <label>Result: <input type="text" name="answer" inputmode="numeric" autocomplete="off" required></label>
                <button type="submit" class="btn">Answer</button>
            </form>
        <?php endif; ?>

    <?php elseif ($view === 'vault'): ?>

        <h2>The Vault</h2>
        <p>A steel vault with a three-digit keypad. The digits you have found so far:</p>
        <p class="digits">
            <?php foreach ($VAULT_ORDER as $i => $p): ?>
                [ <?= isset($foundDigits[$p]) ? h($foundDigits[$p]) : '?' ?> ]
            <?php endforeach; ?>
        </p>
        <p><small>The code is the three digits in the order you found their clues:
           bookshelf, desk, painting.</small></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="vault_code">
            <input type="hidden" name="view" value="vault">
            <label>Enter code:
                <input type="text" name="code" maxlength="3" inputmode="numeric" autocomplete="off" required>
            </label>
            <button type="submit" class="btn">Open vault</button>
        </form>

    <?php endif; ?>
    </main>

    <aside>
        <h3>Inventory</h3>
        <?php if (empty($_SESSION['inventory'])): ?>
            <p><em>Empty</em></p>
        <?php else: ?>
            <ul>
                <?php foreach ($_SESSION['inventory'] as $item): ?>
                    <li><?= h($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <footer>
        <?php if ($view !== 'room'): ?>
            <a class="btn" href="?view=room">Back to room</a>
        <?php endif; ?>
        <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hint">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <button type="submit" class="btn">Get a hint (-100 pts)</button>
        </form>
        <form method="post" action="reset.php" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn">Restart</button>
        </form>
        <form method="post" action="logout.php" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn secondary">Log out</button>
        </form>
    </footer>

</div>
</body>
</html>

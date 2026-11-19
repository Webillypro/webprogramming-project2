<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scores.php';
require_once __DIR__ . '/includes/attempts.php';

require_login();

if (empty($_SESSION['start_time'])) {
    game_init();
}
if (!empty($_SESSION['end_time'])) {
    header('Location: leaderboard.php');
    exit;
}

foreach (['inventory', 'solved', 'hint_ptr', 'wrong_attempts', 'lockout_until', 'clean_solves'] as $key) {
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) $_SESSION[$key] = [];
}
if (!isset($_SESSION['hints_used'])) $_SESSION['hints_used'] = 0;

$HINT_COST = 100;

$rooms = [
    'rug' => [
        'hints' => [
            'Something small is hidden in this room.',
            'It glints near the frayed corner.',
            'Click the button to take the brass key. You will need it for the desk.',
        ],
    ],
    'bookshelf' => [
        'answer' => 'echo',
        'digit'  => '4',
        'hints'  => [
            'The riddle describes something that is heard but never seen.',
            'It comes back to you when you shout into a canyon.',
            'The answer is a four-letter word for a repeated sound.',
        ],
        'error'  => [
            'type'   => 'word',
            'length' => 4,
            'common' => [
                'wind'  => 'The wind brings it to life, but it is not the answer. What repeats you?',
                'sound' => 'You are close in spirit. Name the specific thing that repeats a sound back.',
            ],
        ],
    ],
    'desk' => [
        'answer' => 'five',
        'digit'  => '5',
        'hints'  => [
            'The drawer needs something found elsewhere in the room.',
            'The brass key from the rug fits the lock. Once it is open, read the diary page.',
            'Shift each letter of the scrambled word back by 3 places in the alphabet.',
        ],
        'error'  => [
            'type'   => 'word',
            'length' => 4,
            'cipher' => 'ilyh',
            'shift'  => 3,
        ],
    ],
    'painting' => [
        'answer' => '6',
        'digit'  => '6',
        'hints'  => [
            'The plaque gives you three steps to follow in order.',
            'First subtract the number of ships from the number of gulls.',
            'Then multiply that result by the number of lighthouses.',
        ],
        'error'  => [
            'type'   => 'number',
            'length' => 1,
            'common' => [
                '3'  => 'You removed the ships, but you still need to multiply by the lighthouses.',
                '12' => 'You multiplied, but you forgot to remove the ships first.',
                '18' => 'The ships are taken away, not added.',
            ],
        ],
    ],
    'vault' => [
        'hints' => [
            'The code is built from what the other rooms reveal.',
            'You need one digit each from the bookshelf, the desk and the painting.',
            'Enter the digits in the order: bookshelf, desk, painting.',
        ],
    ],
];

$VAULT_ORDER  = ['bookshelf', 'desk', 'painting'];
$PUZZLE_ROOMS = array_keys(array_filter($rooms, function (array $r): bool { return isset($r['answer']); }));
$VIEWS        = array_merge(['room'], array_keys($rooms));

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

if (!function_exists('post_str')) {
    function post_str(string $key): string {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }
}

function lock_seconds_left(string $roomId): int {
    return max(0, (int)($_SESSION['lockout_until'][$roomId] ?? 0) - time());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post_str('action');
    $view   = post_str('view');
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
            $room = post_str('room');
            if (!in_array($room, $PUZZLE_ROOMS, true)) go('room');

            $given = substr(clean(post_str('answer')), 0, 50);

            if ($room === 'desk' && empty($_SESSION['solved']['drawer'])) {
                flash('The drawer is still locked.');
                go('desk');
            }

            if (!empty($_SESSION['solved'][$room])) {
                flash('You already solved this one.');

            } elseif ($given === $rooms[$room]['answer']) {
                $_SESSION['solved'][$room] = true;
                if ((int)($_SESSION['hint_ptr'][$room] ?? 0) === 0) {
                    $_SESSION['clean_solves'][$room] = true;
                }
                unset($_SESSION['wrong_attempts'][$room], $_SESSION['lockout_until'][$room]);
                flash('Correct! You discover the digit ' . $rooms[$room]['digit'] . '. Write it down.');

            } else {
                $_SESSION['wrong_attempts'][$room] = (int)($_SESSION['wrong_attempts'][$room] ?? 0) + 1;
                $msg = answer_feedback($given, $rooms[$room]['answer'], $rooms[$room]['error']);

                if ($_SESSION['wrong_attempts'][$room] >= WRONG_ATTEMPT_LIMIT) {
                    $_SESSION['lockout_until'][$room] = time() + HINT_LOCK_SECONDS;
                    $msg .= ' Suspicious Activity Detected. The AI Security System has locked the hint button for ' . HINT_LOCK_SECONDS . ' seconds.';
                }
                flash($msg);
            }
            go($room);

        case 'hint':
            if (!isset($rooms[$view])) go('room');

            $left = lock_seconds_left($view);
            if ($left > 0) {
                flash('Suspicious Activity Detected. The hint button is locked for ' . $left . ' more seconds.');
                go($view);
            }

            $total = count($rooms[$view]['hints']);
            $ptr   = (int)($_SESSION['hint_ptr'][$view] ?? 0);

            if ($ptr >= $total) {
                flash('You have already used all ' . $total . ' hints for this room.');
            } else {
                $_SESSION['hint_ptr'][$view] = $ptr + 1;
                $_SESSION['hints_used']++;
                flash('Hint ' . ($ptr + 1) . ' of ' . $total . ' revealed (-' . $HINT_COST . ' points).');
            }
            go($view);

        case 'vault_code':
            foreach ($VAULT_ORDER as $p) {
                if (empty($_SESSION['solved'][$p])) {
                    flash('The keypad does not respond. You have not found all three digits yet.');
                    go('vault');
                }
            }
            $given = preg_replace('/\D/', '', post_str('code'));

            $real = '';
            foreach ($VAULT_ORDER as $p) $real .= $rooms[$p]['digit'];

            if ($given !== '' && hash_equals($real, $given)) {
                $_SESSION['end_time']     = time();
                $elapsed                  = $_SESSION['end_time'] - (int)$_SESSION['start_time'];
                $score                    = max(0, 1000 - $elapsed - ((int)$_SESSION['hints_used'] * $HINT_COST));
                $_SESSION['elapsed_time'] = $elapsed;
                $_SESSION['score']        = $score;

                if (!save_score(current_username(), $score, $elapsed, (int)$_SESSION['hints_used'])) {
                    $_SESSION['score_save_failed'] = true;
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
if (!is_string($view) || !in_array($view, $VIEWS, true)) $view = 'room';

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$elapsedNow = time() - (int)$_SESSION['start_time'];
$mins = floor($elapsedNow / 60);
$secs = $elapsedNow % 60;

$foundDigits = [];
foreach ($VAULT_ORDER as $p) {
    if (!empty($_SESSION['solved'][$p])) $foundDigits[$p] = $rooms[$p]['digit'];
}

$clearance = (int)round(count($_SESSION['clean_solves']) / count($PUZZLE_ROOMS) * 100);

$hintTotal = isset($rooms[$view]) ? count($rooms[$view]['hints']) : 0;
$hintPtr   = (int)($_SESSION['hint_ptr'][$view] ?? 0);
$lockLeft  = isset($rooms[$view]) ? lock_seconds_left($view) : 0;
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
    <?php if ($lockLeft > 0): ?>
        <div class="error" role="alert">
            <strong>Suspicious Activity Detected.</strong>
            The AI Security System has locked the hint button for <?= $lockLeft ?> more seconds.
        </div>
    <?php endif; ?>

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
        <?php if (!empty($_SESSION['solved']['bookshelf'])): ?>
            <p class="good">Solved! The digit behind the slip is <strong><?= h($rooms['bookshelf']['digit']) ?></strong>.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="answer">
                <input type="hidden" name="room" value="bookshelf">
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
            <?php if (!empty($_SESSION['solved']['desk'])): ?>
                <p class="good">Solved! The digit is <strong><?= h($rooms['desk']['digit']) ?></strong>.</p>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="answer">
                    <input type="hidden" name="room" value="desk">
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
            <p class="good">Solved! The digit behind the frame is <strong><?= h($rooms['painting']['digit']) ?></strong>.</p>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="answer">
                <input type="hidden" name="room" value="painting">
                <input type="hidden" name="view" value="painting">
                <label>Result: <input type="text" name="answer" inputmode="numeric" autocomplete="off" required></label>
                <button type="submit" class="btn">Answer</button>
            </form>
        <?php endif; ?>

    <?php elseif ($view === 'vault'): ?>

        <h2>The Vault</h2>
        <p>A steel vault with a three-digit keypad. The digits you have found so far:</p>
        <p class="digits">
            <?php foreach ($VAULT_ORDER as $p): ?>
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

    <?php if ($hintPtr > 0): ?>
        <h3>Hints for this room</h3>
        <ol>
            <?php for ($i = 0; $i < $hintPtr; $i++): ?>
                <li><?= h($rooms[$view]['hints'][$i]) ?></li>
            <?php endfor; ?>
        </ol>
    <?php endif; ?>
    </main>

    <aside>
        <h3>Security Clearance</h3>
        <p>AI Clearance Level: <strong><?= $clearance ?>%</strong></p>
        <meter class="clearance" style="width:100%" min="0" max="100" value="<?= $clearance ?>"
               aria-label="AI Security Clearance Level <?= $clearance ?> percent"></meter>
        <p><small>Rises when you solve a puzzle without using any hints in that room.</small></p>

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
            <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="hint">
                <input type="hidden" name="view" value="<?= h($view) ?>">
                <button type="submit" class="btn"<?= ($lockLeft > 0 || $hintPtr >= $hintTotal) ? ' disabled' : '' ?>>
                    Get a hint (<?= $hintPtr ?>/<?= $hintTotal ?> used, -<?= $HINT_COST ?> pts)
                </button>
            </form>
        <?php endif; ?>
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

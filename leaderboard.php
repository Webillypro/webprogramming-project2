<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scores.php';

const BOARD_SIZE = 20;

// Anyone may look at the leaderboard; logged-in players also see their own result and row.
$loggedIn = is_logged_in();
$me       = current_username();

$runs  = load_scores();            // every finished run, best first; [] if file missing or empty
$board = best_per_user($runs);     // one row per username (their best run)

// Did this player just finish a game?
$finished = $loggedIn && !empty($_SESSION['end_time']);
$myScore  = $finished ? (int)($_SESSION['score'] ?? 0) : 0;
$myTime   = $finished ? (int)($_SESSION['elapsed_time'] ?? 0) : 0;
$myHints  = $finished ? (int)($_SESSION['hints_used'] ?? 0) : 0;

// Position of this username on the board (highlighted row).
$myIndex = null;
if ($loggedIn) {
    foreach ($board as $i => $row) {
        if (strtolower($row['username']) === strtolower($me)) {
            $myIndex = $i;
            break;
        }
    }
}

// A game is running but not finished: offer a way back.
$inProgress = $loggedIn && !empty($_SESSION['start_time']) && empty($_SESSION['end_time']);

$shown = array_slice($board, 0, BOARD_SIZE, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Locked Study - Leaderboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container narrow">

    <header>
        <h1>The Locked Study</h1>
        <p class="tagline">Hall of Escapees</p>
        <?php if ($loggedIn): ?>
            <p>Logged in as <strong><?= h($me) ?></strong></p>
        <?php endif; ?>
    </header>

    <main>
        <?php if ($finished && !empty($_SESSION['score_save_failed'])): ?>
            <div class="error" role="alert">Your score could not be saved because the scores file is not writable. Ask the site owner to check permissions on the data folder.</div>
        <?php endif; ?>

        <?php if ($finished): ?>
            <div class="result-card">
                <h2>The vault swings open!</h2>
                <p>Well done, <strong><?= h($me) ?></strong>. You escaped.</p>
                <p>Score: <strong><?= $myScore ?></strong> &nbsp;|&nbsp;
                   Time: <strong><?= h(format_time($myTime)) ?></strong> &nbsp;|&nbsp;
                   Hints used: <strong><?= $myHints ?></strong></p>
                <?php if ($myIndex !== null): ?>
                    <p>Your best run ranks <strong>#<?= $myIndex + 1 ?></strong> of <?= count($board) ?> players.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2>Top Scores</h2>

        <?php if (empty($board)): ?>
            <div class="empty-board">No scores yet. Be the first to escape the study.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="scores">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Username</th>
                            <th class="num">Score</th>
                            <th class="num">Time</th>
                            <th class="num">Hints</th>
                            <th class="num">Escapes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shown as $i => $row): ?>
                        <tr<?= $i === $myIndex ? ' class="me"' : '' ?>>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($row['username']) ?><?= $i === $myIndex ? ' (you)' : '' ?></td>
                            <td class="num"><?= $row['score'] ?></td>
                            <td class="num"><?= h(format_time($row['time'])) ?></td>
                            <td class="num"><?= $row['hints'] ?></td>
                            <td class="num"><?= $row['games'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint-text" style="margin-top:10px;">
                Each player is listed once, with their best run.
                <?php if (count($board) > BOARD_SIZE): ?>
                    Showing the top <?= BOARD_SIZE ?> of <?= count($board) ?> players.
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </main>

    <footer>
        <?php if ($loggedIn): ?>
            <?php if ($inProgress): ?>
                <a class="btn secondary" href="escaperoom.php">Return to game</a>
            <?php endif; ?>
            <form method="post" action="reset.php" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn"><?= $inProgress ? 'Start over' : 'Play again' ?></button>
            </form>
            <form method="post" action="logout.php" class="inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn secondary">Log out</button>
            </form>
        <?php else: ?>
            <a class="btn" href="login.php">Log in</a>
            <a class="btn secondary" href="register.php">Register</a>
        <?php endif; ?>
    </footer>

</div>
</body>
</html>

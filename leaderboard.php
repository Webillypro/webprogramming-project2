<?php
session_start();
require_once __DIR__ . '/includes/scores.php';

const BOARD_SIZE = 20;

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$scores = load_scores();   // already sorted best-first; [] if file is missing or empty

// Has this visitor just finished a game?
$finished = !empty($_SESSION['end_time']) && !empty($_SESSION['player_name']);
$myName   = $finished ? (string)$_SESSION['player_name'] : '';
$myScore  = $finished ? (int)($_SESSION['score'] ?? 0) : 0;
$myTime   = $finished ? (int)($_SESSION['elapsed_time'] ?? 0) : 0;
$myHints  = $finished ? (int)($_SESSION['hints_used'] ?? 0) : 0;

// [ADDED] true only if escaperoom.php tried to write scores.json and it failed
$saveFailed = $finished && isset($_SESSION['score_saved']) && $_SESSION['score_saved'] === false;
// [END ADDED]

// Find this player's row so it can be highlighted (first exact match only).
$myIndex = null;
if ($finished) {
    foreach ($scores as $i => $row) {
        if ($row['name'] === $myName && $row['score'] === $myScore && $row['time'] === $myTime) {
            $myIndex = $i;
            break;
        }
    }
}

// A game is running but not finished: offer a way back.
$inProgress = !empty($_SESSION['player_name']) && !empty($_SESSION['start_time']) && empty($_SESSION['end_time']);

$shown = array_slice($scores, 0, BOARD_SIZE, true);
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
    </header>

    <main>
        <?php if ($finished): ?>
            <div class="result-card">
                <h2>The vault swings open!</h2>
                <p>Well done, <strong><?= h($myName) ?></strong>. You escaped.</p>
                <p>Score: <strong><?= $myScore ?></strong> &nbsp;|&nbsp;
                   Time: <strong><?= h(format_time($myTime)) ?></strong> &nbsp;|&nbsp;
                   Hints used: <strong><?= $myHints ?></strong></p>
                <?php if ($saveFailed): ?>
                    <!-- [ADDED] shown only if the score could not be written to scores.json -->
                    <p class="error">Your score could not be saved to the leaderboard. The server may not allow writing <code>scores.json</code>, so check the folder permissions.</p>
                    <!-- [END ADDED] -->
                <?php endif; ?>
                <?php if ($myIndex !== null): ?>
                    <p>You placed <strong>#<?= $myIndex + 1 ?></strong> of <?= count($scores) ?>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2>Top Scores</h2>

        <?php if (empty($scores)): ?>
            <div class="empty-board">No scores yet. Be the first to escape the study.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="scores">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th class="num">Score</th>
                            <th class="num">Time</th>
                            <th class="num">Hints</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($shown as $i => $row): ?>
                        <tr<?= $i === $myIndex ? ' class="me"' : '' ?>>
                            <td><?= $i + 1 ?></td>
                            <td><?= h($row['name']) ?><?= $i === $myIndex ? ' (you)' : '' ?></td>
                            <td class="num"><?= $row['score'] ?></td>
                            <td class="num"><?= h(format_time($row['time'])) ?></td>
                            <td class="num"><?= $row['hints'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($scores) > BOARD_SIZE): ?>
                <p class="hint-text" style="margin-top:10px;">Showing the top <?= BOARD_SIZE ?> of <?= count($scores) ?> scores.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <?php if ($inProgress): ?>
            <a class="btn secondary" href="escaperoom.php">Return to game</a>
        <?php endif; ?>
        <a class="btn" href="reset.php"><?= ($finished || !$inProgress) ? 'Play again' : 'Start over' ?></a>
        <a class="btn secondary" href="login.php">Back to start</a>
    </footer>

</div>
</body>
</html>
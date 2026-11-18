<?php
session_start();

const NAME_MIN = 1;
const NAME_MAX = 20;

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$error = '';
$name  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // trim() removes leading/trailing spaces; collapse inner runs of whitespace too.
    // [MODIFIED] was: $name = trim((string)($_POST['player_name'] ?? ''));
    // BUG FIX: a tampered form (player_name[]=x) caused an "Array to string conversion" warning.
    $rawName = $_POST['player_name'] ?? '';
    $name    = is_string($rawName) ? trim($rawName) : '';

    // [MODIFIED] was: preg_replace('/\s+/', ' ', $name)
    // Added the /u flag so multibyte text is handled correctly. preg_replace() returns null
    // on invalid UTF-8, which is reported as an error below instead of slipping through.
    $collapsed   = preg_replace('/\s+/u', ' ', $name);
    $badEncoding = ($collapsed === null);
    $name        = $badEncoding ? '' : $collapsed;

    // [MODIFIED] was: $len = mb_strlen($name, 'UTF-8');
    // Falls back to strlen() so the page does not crash on a server without the mbstring extension.
    $len = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if ($badEncoding) {   // [ADDED] branch for invalid text encoding
        $error = 'That name contains characters that could not be read. Please use plain letters and numbers.';
    } elseif ($len < NAME_MIN) {   // [MODIFIED] was: if ($len < NAME_MIN) {
        $error = 'Please enter a name. Blank names or only spaces are not allowed.';
    } elseif ($len > NAME_MAX) {
        $error = 'That name is too long. Please use ' . NAME_MAX . ' characters or fewer.';
    } elseif (!preg_match("/^[\p{L}\p{N} _.'-]+$/u", $name)) {
        // Allow-list: letters, numbers, space, underscore, dot, apostrophe, hyphen.
        // Rejects <, >, &, quotes and other characters that could be used for markup.
        $error = "Names may only contain letters, numbers, spaces, and the characters _ . ' -";
    } else {
        // Valid: start a brand-new game.
        session_regenerate_id(true);
        $_SESSION = [];                       // wipes any old game, flash, finished flag, score
        $_SESSION['player_name'] = $name;
        $_SESSION['start_time']  = time();
        $_SESSION['inventory']   = [];
        $_SESSION['solved']      = [];
        $_SESSION['hints_used']  = 0;

        header('Location: escaperoom.php');   // Post/Redirect/Get
        exit;
    }
}

// Is there a game already running in this session?
$inProgress = !empty($_SESSION['player_name'])
           && !empty($_SESSION['start_time'])
           && empty($_SESSION['end_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Locked Study - Enter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container narrow">

    <header>
        <h1>The Locked Study</h1>
        <p class="tagline">An escape room puzzle game</p>
    </header>

    <main>
        <div class="welcome">
            <h2>Welcome</h2>
            <p>You wake up in a dusty study. The door is bolted, and the only way out is the vault
               in the corner. Somewhere in this room are the three digits that open it.</p>
            <ul>
                <li>Click objects in the room to examine them and find clues.</li>
                <li>Solve each puzzle to uncover a digit of the vault code.</li>
                <!-- [MODIFIED] was: "Your score starts at 1000 and drops with every second and every hint (-100 each)." Same rule, now states the per-second rate. -->
                <li>Your score starts at 1000 and drops by 1 point every second and by 100 points for every hint.</li>
                <li>A full playthrough takes about 5 to 10 minutes.</li>
            </ul>
        </div>

        <?php if ($inProgress): ?>
            <div class="resume">
                <p>A game is already in progress for <strong><?= h($_SESSION['player_name']) ?></strong>.</p>
                <a class="btn secondary" href="escaperoom.php">Resume game</a>
                <p class="hint-text" style="margin-top:10px;">Starting a new game below will replace it.</p>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <label for="player_name">Enter your name to begin:</label>
            <input type="text" id="player_name" name="player_name"
                   value="<?= h($name) ?>" maxlength="<?= NAME_MAX ?>"
                   placeholder="Your name" autocomplete="off" autofocus>
            <button type="submit" class="btn">Enter the study</button>
        </form>
    </main>

    <footer>
        <a class="btn secondary" href="leaderboard.php">View leaderboard</a>
    </footer>

</div>
</body>
</html>
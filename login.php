<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';

// Already logged in? Go straight to the game.
if (is_logged_in()) {
    redirect('escaperoom.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');     // never trimmed: spaces can be part of a password

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } elseif (strlen($username) > USER_MAX || strlen($password) > PASS_MAX) {
        // Cannot match any account; also keeps huge inputs away from password_verify().
        $error = 'Invalid username or password.';
    } else {
        $who = verify_login($username, $password);
        if ($who === null) {
            // Same message whether the name or the password was wrong.
            $error = 'Invalid username or password.';
        } else {
            log_in_user($who);
            redirect('escaperoom.php');                 // Post/Redirect/Get
        }
    }
}

$registered = isset($_GET['registered']);
$loggedOut  = isset($_GET['loggedout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Locked Study - Log in</title>
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
                <li>Your score starts at 1000 and drops with every second and every hint (-100 each).</li>
                <li>A full playthrough takes about 5 to 10 minutes.</li>
            </ul>
        </div>

        <?php if ($registered): ?>
            <div class="good">Account created. Log in below to enter the study.</div>
        <?php endif; ?>
        <?php if ($loggedOut): ?>
            <div class="good">You have been logged out.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= h($username) ?>" maxlength="<?= USER_MAX ?>"
                   autocomplete="username" autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   maxlength="<?= PASS_MAX ?>" autocomplete="current-password">

            <button type="submit" class="btn">Log in</button>
        </form>

        <p class="hint-text">No account yet? <a href="register.php">Register here</a>.</p>
    </main>

    <footer>
        <a class="btn secondary" href="register.php">Register</a>
        <a class="btn secondary" href="leaderboard.php">View leaderboard</a>
    </footer>

</div>
</body>
</html>

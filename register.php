<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/users.php';

if (is_logged_in()) {
    redirect('escaperoom.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    $error = validate_username($username);
    if ($error === '') {
        $error = validate_password($password, $confirm);
    }
    if ($error === '') {
        $error = create_user($username, $password);      // '' on success, message on failure
    }
    if ($error === '') {
        redirect('login.php?registered=1');              // Post/Redirect/Get
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Locked Study - Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container narrow">

    <header>
        <h1>The Locked Study</h1>
        <p class="tagline">Create an account</p>
    </header>

    <main>
        <h2>Register</h2>
        <p>Pick a username and password. Your username is what appears on the leaderboard.</p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
            <?= csrf_field() ?>
            <label for="username">Username
                <small>(<?= USER_MIN ?>-<?= USER_MAX ?> characters: letters, numbers, _ . -)</small></label>
            <input type="text" id="username" name="username"
                   value="<?= h($username) ?>" maxlength="<?= USER_MAX ?>"
                   autocomplete="username" autofocus>

            <label for="password">Password <small>(at least <?= PASS_MIN ?> characters)</small></label>
            <input type="password" id="password" name="password"
                   maxlength="<?= PASS_MAX ?>" autocomplete="new-password">

            <label for="confirm">Confirm password</label>
            <input type="password" id="confirm" name="confirm"
                   maxlength="<?= PASS_MAX ?>" autocomplete="new-password">

            <button type="submit" class="btn">Create account</button>
        </form>

        <p class="hint-text">Already registered? <a href="login.php">Log in</a>.</p>
    </main>

    <footer>
        <a class="btn secondary" href="login.php">Back to log in</a>
        <a class="btn secondary" href="leaderboard.php">View leaderboard</a>
    </footer>

</div>
</body>
</html>

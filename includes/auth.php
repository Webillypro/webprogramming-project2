<?php
/**
 * Session, authentication and small shared helpers.
 * Every page includes this file instead of calling session_start() itself.
 */

/** Start the session once, with safer cookie settings. */
function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,                                   // ends when the browser closes
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),           // only over HTTPS when available
        'httponly' => true,                                // not readable from JavaScript
        'samesite' => 'Lax',                               // not sent on cross-site POSTs
    ]);
    session_start();
}

app_session_start();

/** HTML-escape for output. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Build the message for a file-storage failure and log the real reason.
 * Visitors only see the short message. When the page is opened from the same computer
 * (localhost) the technical detail is appended too, so setup problems are easy to diagnose
 * without leaking server paths to other people.
 */
function storage_problem(string $msg, string $path): string
{
    $err    = error_get_last();
    $reason = is_array($err) ? (string)$err['message'] : 'no PHP error recorded';
    $runner = (function_exists('posix_geteuid') && function_exists('posix_getpwuid'))
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
        : get_current_user();

    error_log("[escape-room] $msg path=$path reason=$reason php_user=$runner");

    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if ($local) {
        $msg .= " [Details: $reason | path: $path | PHP is running as: $runner]";
    }
    return $msg;
}

/** Redirect and stop. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Who is logged in? ---------- */

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function current_username(): string
{
    return is_logged_in() ? (string)$_SESSION['username'] : '';
}

/**
 * Guard for protected pages. Anyone without a logged-in session is sent to login.php.
 * Must be called before any output.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/** Mark the session as logged in. Called only after the password has been verified. */
function log_in_user(string $username): void
{
    session_regenerate_id(true);          // new session ID on privilege change (prevents fixation)
    $_SESSION = [];                       // drop anything left from an earlier visitor
    $_SESSION['user_id']  = strtolower($username);
    $_SESSION['username'] = $username;
    // Game state is created on first visit to escaperoom.php (see game_init()).
}

/** Fully end the session: clear data, expire the cookie, destroy the server-side session. */
function log_out_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/* ---------- Game state ---------- */

/** Start (or restart) a game for the logged-in user. Keeps the login, resets everything else. */
function game_init(): void
{
    $_SESSION['start_time'] = time();
    $_SESSION['inventory']  = [];
    $_SESSION['solved']     = [];
    $_SESSION['hints_used'] = 0;
    unset($_SESSION['end_time'], $_SESSION['elapsed_time'], $_SESSION['score'], $_SESSION['flash'], $_SESSION['score_save_failed']);
}

/* ---------- CSRF protection for every POST form ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Stop a POST that has a missing or wrong token. */
function csrf_require(): void
{
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Your session token was missing or expired. Go back, reload the page and try again.');
    }
}

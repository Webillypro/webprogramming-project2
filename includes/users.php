<?php
/**
 * Flat-file user storage (no database).
 *
 * Users live in data/users.php as JSON, keyed by lower-cased username:
 *   { "billy": { "username": "Billy", "hash": "$2y$10$...", "created": "2026-11-18T22:00:00-05:00" } }
 *
 * Passwords are only ever stored as password_hash() output and checked with password_verify().
 *
 * The file is named .php and starts with a line that stops execution, so even if the web server
 * serves it directly, a browser gets an empty page instead of the password hashes.
 * data/users.php is also listed in .gitignore so hashes are never pushed to GitHub.
 */

require_once __DIR__ . '/storage.php';    // users_file(): data/users.php, or a writable fallback

const USERS_GUARD  ="<?php http_response_code(404); exit; ?>\n";
const USER_MIN     = 3;
const USER_MAX     = 20;
const PASS_MIN     = 8;
const PASS_MAX     = 72;       // bcrypt only uses the first 72 bytes

/** Decode file contents (guard line + JSON) into an array. Anything unreadable -> []. */
function users_decode($raw): array
{
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    if (strncmp($raw, USERS_GUARD, strlen(USERS_GUARD)) === 0) {
        $raw = substr($raw, strlen(USERS_GUARD));
    }
    if (trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Read every user (shared lock so we never read a half-written file). */
function users_load(): array
{
    if (!is_file(users_file()) || !is_readable(users_file())) {
        return [];
    }
    $fh = fopen(users_file(), 'r');
    if ($fh === false) {
        return [];
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return users_decode($raw);
}

function find_user(string $username): ?array
{
    $users = users_load();
    $key   = strtolower($username);
    return (isset($users[$key]) && is_array($users[$key])) ? $users[$key] : null;
}

/** Validate a username. Returns an error message, or '' when it is fine. */
function validate_username(string $u): string
{
    $len = strlen($u);
    if ($len < USER_MIN || $len > USER_MAX) {
        return 'Username must be ' . USER_MIN . ' to ' . USER_MAX . ' characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $u)) {
        return 'Username may only contain letters, numbers, and the characters _ . -';
    }
    return '';
}

/** Validate a password (and its confirmation). Returns an error message, or ''. */
function validate_password(string $p, string $confirm): string
{
    if (strlen($p) < PASS_MIN) {
        return 'Password must be at least ' . PASS_MIN . ' characters.';
    }
    if (strlen($p) > PASS_MAX) {
        return 'Password must be ' . PASS_MAX . ' characters or fewer.';
    }
    if ($p !== $confirm) {
        return 'The two passwords do not match.';
    }
    return '';
}

/**
 * Add a user. The duplicate check and the write happen under one exclusive lock,
 * so two people registering the same name at the same moment cannot both succeed.
 *
 * @return string '' on success, otherwise an error message safe to show.
 */
function create_user(string $username, string $password): string
{
    $dir = dirname(users_file());
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return storage_problem('Could not create the data folder. Check folder permissions.', $dir);
    }

    $fh = @fopen(users_file(), 'c+');
    if ($fh === false) {
        return storage_problem('Could not open the user file. Check file permissions.', users_file());
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return storage_problem('Could not lock the user file. Please try again.', users_file());
    }

    $users = users_decode(stream_get_contents($fh));
    $key   = strtolower($username);

    if (isset($users[$key])) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return 'That username is already taken.';
    }

    $users[$key] = [
        'username' => $username,
        'hash'     => password_hash($password, PASSWORD_DEFAULT),
        'created'  => date('c'),
    ];

    ftruncate($fh, 0);
    rewind($fh);
    $ok = fwrite($fh, USERS_GUARD . json_encode($users, JSON_PRETTY_PRINT)) !== false;
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $ok ? '' : 'Could not save your account. Please try again.';
}

/**
 * Check a username/password pair.
 * Returns the stored (correctly capitalised) username on success, or null on failure.
 * Runs password_verify() even for unknown users so response time does not reveal which names exist.
 */
function verify_login(string $username, string $password): ?string
{
    static $dummy = null;
    if ($dummy === null) {
        $dummy = password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }

    $user = find_user($username);
    $hash = ($user !== null && isset($user['hash'])) ? (string)$user['hash'] : $dummy;
    $ok   = password_verify($password, $hash);

    return ($ok && $user !== null) ? (string)$user['username'] : null;
}

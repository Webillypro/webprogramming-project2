<?php
/**
 * Decides where users.php and scores.json are stored.
 *
 * 1. The project's own data/ folder is used whenever the web server can write to it.
 * 2. If it cannot (common with XAMPP/Apache running as a different user than the folder owner),
 *    the app falls back to a private folder in the system temp directory so registration and
 *    scores still work with no setup. The choice is logged with error_log().
 *
 * "Can write" is tested by really creating a file, because is_writable() can be wrong
 * on Windows and some Docker/WSL mounts.
 */

/** Can we create and delete a file in this folder, and write to the data files already there? */
function dir_is_usable(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $probe = $dir . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
    $fh    = @fopen($probe, 'c');
    if ($fh === false) {
        return false;
    }
    fclose($fh);
    @unlink($probe);

    // A data file that already exists must itself be writable, not just the folder.
    foreach (['users.php', 'scores.json'] as $name) {
        $f = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($f) && !is_writable($f)) {
            return false;
        }
    }
    return true;
}

/** Folder that holds users.php and scores.json (created if needed). */
function data_dir(): string
{
    static $chosen = null;
    if ($chosen !== null) {
        return $chosen;
    }

    $project  = __DIR__ . '/../data';
    $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR
              . 'escape-room-' . substr(sha1((string)(realpath(__DIR__ . '/..') ?: __DIR__)), 0, 12);

    foreach ([[$project, 0775], [$fallback, 0700]] as [$dir, $mode]) {
        if (!is_dir($dir)) {
            @mkdir($dir, $mode, true);
        }
        if (!dir_is_usable($dir)) {
            @chmod($dir, $mode | 0070);       // works when PHP owns the folder; harmless otherwise
        }
        if (dir_is_usable($dir)) {
            if ($dir === $fallback) {
                error_log('[escape-room] data/ is not writable by the web server; using ' . $dir);
            }
            return $chosen = $dir;
        }
    }

    // Nothing worked. Return the project folder so the error message names the expected place.
    return $chosen = $project;
}

function users_file(): string  { return data_dir() . '/users.php'; }
function scores_file(): string { return data_dir() . '/scores.json'; }

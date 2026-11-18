<?php
/**
 * Score helpers shared by escaperoom.php and leaderboard.php.
 * Scores live in scores.json in the project root.
 */

const SCORES_FILE = __DIR__ . '/../scores.json';

// [ADDED] ---- Scoring rules: the ONE place they are defined ----------------------------
// These match the welcome text on login.php. Change them here and the live score in the
// header and the final saved score both follow.
const START_SCORE       = 1000;   // score at the moment the game starts
const POINTS_PER_SECOND = 1;      // points lost for every second that passes
const HINT_PENALTY      = 100;    // points lost for every hint used

/** Score after $elapsedSeconds seconds and $hintsUsed hints. Never below 0. */
function calculate_score(int $elapsedSeconds, int $hintsUsed): int
{
    $elapsedSeconds = max(0, $elapsedSeconds);
    $hintsUsed      = max(0, $hintsUsed);
    return max(0, START_SCORE - ($elapsedSeconds * POINTS_PER_SECOND) - ($hintsUsed * HINT_PENALTY));
}
// [END ADDED]

/**
 * Load every saved score, best first.
 * Missing, empty or corrupt file -> empty array (never an error).
 * Ranking: higher score first, then faster time, then earlier entry.
 */
function load_scores(): array
{
    if (!is_file(SCORES_FILE) || !is_readable(SCORES_FILE)) {
        return [];
    }

    $raw  = file_get_contents(SCORES_FILE);
    $data = ($raw === false || trim($raw) === '') ? [] : json_decode($raw, true);

    if (!is_array($data)) {
        return [];
    }

    // Keep only well-formed rows.
    $rows = [];
    foreach ($data as $row) {
        if (is_array($row) && isset($row['name'], $row['score'], $row['time'])) {
            $rows[] = [
                'name'  => (string)$row['name'],
                'score' => (int)$row['score'],
                'time'  => (int)$row['time'],
                'hints' => (int)($row['hints'] ?? 0),
                'date'  => (string)($row['date'] ?? ''),
            ];
        }
    }

    usort($rows, function (array $a, array $b): int {
        return [$b['score'], $a['time']] <=> [$a['score'], $b['time']];
    });

    return $rows;
}

/**
 * Append one score. The file is locked for the whole read-modify-write
 * so two players finishing at the same moment cannot corrupt it.
 */
function save_score(string $name, int $score, int $elapsed, int $hints): bool
{
    // [MODIFIED] was: $fh = fopen(SCORES_FILE, 'c+');
    // The @ stops a folder-permissions problem from printing a PHP warning, which would break
    // the header() redirect that follows in escaperoom.php. A failure is still reported through
    // the false return value (see the score_saved flag in escaperoom.php).
    $fh = @fopen(SCORES_FILE, 'c+');   // creates the file if it does not exist
    if ($fh === false) {
        return false;
    }

    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }

    $raw  = stream_get_contents($fh);
    $data = ($raw === false || trim($raw) === '') ? [] : json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
    }

    $data[] = [
        'name'  => $name,
        'score' => $score,
        'time'  => $elapsed,
        'hints' => $hints,
        'date'  => date('Y-m-d H:i'),
    ];

    // [MODIFIED] was: ftruncate() first, then fwrite(json_encode(...)).
    // BUG FIX: if json_encode() failed, the file had already been emptied and every saved
    // score was lost. Now the JSON is built first and the file is only touched if it succeeds.
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return false;
    }

    ftruncate($fh, 0);
    rewind($fh);
    $ok = fwrite($fh, $json) !== false;
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);

    return $ok;
}

/** Format seconds as "3m 07s". */
function format_time(int $seconds): string
{
    $seconds = max(0, $seconds);
    return floor($seconds / 60) . 'm ' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) . 's';
}
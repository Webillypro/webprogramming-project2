<?php
/**
 * Score helpers shared by escaperoom.php and leaderboard.php.
 * Scores live in scores.json in the project root.
 */

const SCORES_FILE = __DIR__ . '/../scores.json';

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
    $fh = fopen(SCORES_FILE, 'c+');   // creates the file if it does not exist
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

    ftruncate($fh, 0);
    rewind($fh);
    $ok = fwrite($fh, json_encode($data, JSON_PRETTY_PRINT)) !== false;
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

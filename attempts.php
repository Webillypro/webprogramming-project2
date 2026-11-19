<?php

const WRONG_ATTEMPT_LIMIT = 3;
const HINT_LOCK_SECONDS   = 30;

function caesar_shift(string $text, int $shift): string
{
    $out = '';
    foreach (str_split(strtolower($text)) as $ch) {
        if ($ch >= 'a' && $ch <= 'z') {
            $out .= chr((((ord($ch) - 97 + $shift) % 26) + 26) % 26 + 97);
        } else {
            $out .= $ch;
        }
    }
    return $out;
}

function close_threshold(string $answer): int
{
    return strlen($answer) <= 4 ? 1 : 2;
}

function answer_feedback(string $given, string $answer, array $error): string
{
    $type = $error['type'] ?? 'word';

    if ($type === 'number' && !ctype_digit($given)) {
        return 'This lock wants a number.';
    }
    if ($type === 'word' && preg_match('/\d/', $given)) {
        return 'This one wants a word, not numbers.';
    }

    if (isset($error['common']) && array_key_exists($given, $error['common'])) {
        return $error['common'][$given];
    }

    if (isset($error['cipher'], $error['shift'])) {
        $shift = (int)$error['shift'];

        if ($given === $error['cipher']) {
            return 'That is just the scrambled text. It still needs decoding.';
        }
        for ($k = 1; $k <= 12; $k++) {
            if ($k !== $shift && $given === caesar_shift($error['cipher'], -$k)) {
                return 'Your cipher shift is in the right direction but the wrong magnitude.';
            }
        }
        for ($k = 1; $k <= 12; $k++) {
            if ($given === caesar_shift($error['cipher'], $k)) {
                return 'Your cipher shift is going the wrong way. Try moving the letters in the opposite direction.';
            }
        }
    }

    if ($type === 'word' && levenshtein($given, $answer) <= close_threshold($answer)) {
        return 'Very close! Check your spelling.';
    }

    if (isset($error['length'])) {
        if (strlen($given) < $error['length']) return 'Your answer is too short.';
        if (strlen($given) > $error['length']) return 'Your answer is too long.';
    }

    return 'Nothing happens. That is not right.';
}

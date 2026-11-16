<?php
session_start();
require_once __DIR__ . '/includes/scores.php';

if (empty($_SESSION['player_name']) || empty($_SESSION['start_time'])) {
  header('Location: login.php');
  exit;
}
if (!empty($_SESSION['end_time'])) {
  header('Location: leaderboard.php');
  exit;
}

if (!isset($_SESSION['inventory']) || !is_array($_SESSION['inventory'])) $_SESSION['inventory'] = [];
if (!isset($_SESSION['solved']) || !is_array($_SESSION['solved'])) $_SESSION['solved'] = []
if (!isset($_SESSION['hints_used'])) $_SESSION['hints_used'] = 0;

$ANSWERS = [
  'shelf' => 'echo',
  'cipher' => 'five',
  'painting' => '6',
  ];
$DIGITS = [
    'shelf'    => '4',
    'cipher'   => '5',
    'painting' => '6',
];
$VAULT_ORDER = ['shelf', 'cipher', 'painting'];
$VIEWS = ['room', 'rug', 'bookshelf', 'desk', 'painting', 'vault'];

function flash(string $msg): void { $_SESSION['flash'] = $msg; }
function go(string $view): void {
    header('Location: escaperoom.php?view=' . urlencode($view));
    exit;
}

function has_item(string $item): bool { return in_array($item, $_SESSION['inventory'], true); }
function add_item(string $item): void {
    if (!has_item($item)) $_SESSION['inventory'][] = $item;
}
function clean(string $s): string { return strtolower(trim($s)); }
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $view   = $_POST['view'] ?? 'room';
    if (!in_array($view, $VIEWS, true)) $view = 'room';
switch ($action) {

        case 'take_key':
            if (has_item('brass key')) {
                flash('You already have the brass key.');
            } else {
                add_item('brass key');
                flash('You slip the small brass key into your pocket.');
            }
            go('rug');

        case 'use_key':
            if (!has_item('brass key')) {
                flash('The drawer is locked. You need a key.');
            } elseif (!empty($_SESSION['solved']['drawer'])) {
                flash('The drawer is already open.');
            } else {
                $_SESSION['solved']['drawer'] = true;
                flash('Click! The key turns and the drawer slides open.');
            }
            go('desk');

        case 'answer':
            $puzzle = $_POST['puzzle'] ?? '';
            $given  = clean($_POST['answer'] ?? '');
            if (!isset($ANSWERS[$puzzle])) go('room');
            if ($puzzle === 'cipher' && empty($_SESSION['solved']['drawer'])) {
                flash('The drawer is still locked.');
                go('desk');
            }

            $backTo = ['shelf' => 'bookshelf', 'cipher' => 'desk', 'painting' => 'painting'][$puzzle];

            if (!empty($_SESSION['solved'][$puzzle])) {
                flash('You already solved this one.');
            } elseif ($given === $ANSWERS[$puzzle]) {
                $_SESSION['solved'][$puzzle] = true;
                flash('Correct! You discover the digit ' . $DIGITS[$puzzle] . '. Write it down.');
            } else {
                flash('Nothing happens. That is not right.');
            }
            go($backTo);

        case 'hint':
            $_SESSION['hints_used']++;
            $hint = 'Look around the room carefully.';
            if (!has_item('brass key')) {
                $hint = 'Something shiny might be hidden where people wipe their feet.';
            } elseif (empty($_SESSION['solved']['drawer'])) {
                $hint = 'That brass key must fit the desk drawer.';
            } elseif (empty($_SESSION['solved']['shelf'])) {
                $hint = 'The riddle on the bookshelf speaks, but never has a mouth. Think of what repeats you.';
            } elseif (empty($_SESSION['solved']['cipher'])) {
                $hint = 'Each letter in the note was shifted forward by 3. Shift each one back.';
            } elseif (empty($_SESSION['solved']['painting'])) {
                $hint = 'Count the gulls and the ships, subtract, then multiply by the lighthouses.';
            } else {
                $hint = 'You have all three digits. Try them in order at the vault.';
            }
            flash('HINT: ' . $hint . ' (-100 points)');
            go($view);

        case 'vault_code':
            $given = preg_replace('/\D/', '', $_POST['code'] ?? '');
            $real = '';
            foreach ($VAULT_ORDER as $p) $real .= $DIGITS[$p];

            if ($given !== '' && hash_equals($real, $given)) {
                $_SESSION['end_time']     = time();
                $elapsed                  = $_SESSION['end_time'] - (int)$_SESSION['start_time'];
                $score                    = max(0, 1000 - $elapsed - ((int)$_SESSION['hints_used'] * 100));
                $_SESSION['elapsed_time'] = $elapsed;
                $_SESSION['score']        = $score;

                save_score($_SESSION['player_name'], $score, $elapsed, (int)$_SESSION['hints_used']);

                header('Location: leaderboard.php');
                exit;
            }
            flash('The vault buzzes angrily. Wrong code.');
            go('vault');
    }

    go($view);
}

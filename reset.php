<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

// Restart the puzzle only. The player stays logged in (use logout.php to end the session).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    game_init();
}

redirect('escaperoom.php');

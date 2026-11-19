<?php
require_once __DIR__ . '/includes/auth.php';

// Logging out is a POST with a CSRF token, so another website cannot log a player out
// with a hidden link or image. Opening this URL directly just bounces back.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    log_out_user();                         // clears $_SESSION, expires the cookie, session_destroy()
    redirect('login.php?loggedout=1');
}

redirect(is_logged_in() ? 'escaperoom.php' : 'login.php');

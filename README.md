The Locked Study: PHP Escape Room

A browser-based escape room game built with only HTML, CSS and PHP. There is no JavaScript and no database. The player enters a name, explores a room by clicking objects, solves puzzles, and opens a vault. Their time and score are then saved to a leaderboard.

How It Works
The player enters a name on login.php.
In escaperoom.php, they click objects (rug, bookshelf, desk, painting, vault). Each click sends a request to PHP.
PHP tracks progress in $_SESSION: inventory, solved puzzles, hints used and start time.
Every puzzle answer is checked on the server. Answers and the final vault code live only in PHP, so they can't be found by viewing the page source.
When the vault opens, PHP records the elapsed time, calculates a score, saves it to scores.json, and redirects to leaderboard.php.

Files:
login.php
escaperoom.php
leaderboard.php
reset.php

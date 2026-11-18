The Locked Study: PHP Escape Room

A browser-based escape room game built with only HTML, CSS and PHP. There is no JavaScript and no database. The player enters a name, explores a room by clicking objects, solves puzzles, and opens a vault. Their time and score are then saved to a leaderboard.

How It Works
The player enters a name on login.php.
In escaperoom.php, they click objects (rug, bookshelf, desk, painting, vault). Each click sends a request to PHP.
PHP tracks progress in $_SESSION: inventory, solved puzzles, hints used and start time.
Every puzzle answer is checked on the server. Answers and the final vault code live only in PHP, so they can't be found by viewing the page source.
When the vault opens, PHP records the elapsed time, calculates a score, saves it to scores.json, and redirects to leaderboard.php.

Files:
login.php        | Intro and rules, the name form. Sets player_name and start_time, and intializes inventory and solved.
escaperoom.php   | Core gameplay: objects, puzzles, hints, inventory and the vault.
leaderboard.php  | Shows ranked scores and the current player's position.
reset.php        | Starts a new game: session_destroy(), then redirect to login.php.
scores.php       | Helper to save and load scores. file_put_content(), LOCK_EX, json_decode(), usort().
style.css        | Shared styling for every page.
scores.json      | Saved scores, created automatically in the project root on the first win.

Session Variables:
player_name      | login.php      | Name shown on the leaderboard
start_time       | login.php      | Unix timestamp when the game began
inventory        | login.php      | Items the player has collected
solved           | login.php      | Which puzzles have been solved
hints_used       | login.php      | Number of hints taken
end_time         | escaperoom.php | Unix timestamp when the vault opened
elapsed_time     | escaperoom.php | end_time - start_time in seconds
score            | escaperoom.php | Final score
score_saved      | escaperoom.php | true or false: whether the score was written to scores.json
flash            | escaperoom.php | One time message shown after an action

Scoring:
score = max(0, 1000 - elapsed_seconds - (hints_used * 100))
The score starts at 1000, drops by 1 point every second, and drops by 100 points per hint. Ties are ranked by the faster time.

The rules are defined in one place, the constants START_SCORE, POINTS_PER_SECOND and HINT_PENALTY at the top of includes/scores.php. Changing them updates the live score, the hint button label and the final saved score together. If you change them, also update the rules text on login.php, which is written out by hand.
Running Locally:

cd path/to/escapep-room
php -S localhost:8000

Then open http://localhost:8000/login.php
The web server must be able to write to the project folder, or scores.json can't be created. If it can't be written, the leaderboard shows a warning after a win.

Customizing the Room
Most of the game content is in escaperoom.php:
  Puzzle answers: the $Answers array near the top
  Revealed digits and vault order: the $DIGITS and $VAULT_ORDER arrays.
  Notes and clues: the text in the rug, bookshelf, desk and painting sections.
  Hints: the hint case. If you change a puzzle, rewrite its hint to match.
  Intro text: the "Welcome" section in login.php
  Look and feel: the colors and fonts are CSS variables at the top of style.css .

Notes
  Every game action is a POST that PHP processes before redirecting, so refreshing the page never repeats an action.
  The elapsed time in the header only updates when the page reloads. The live score in the header counts down on its own using a CSS animation, but it needs a current browser. The real score is always calculated by PHP.
  Finished games redirect to the leaderboard. Use reset.php or log in again to start a new game.
  Scores are stored in a flat file and are not secured against tampering, so this is meant for a class project or small demo.

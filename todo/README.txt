How to run (needs PHP 8 with the pdo_sqlite extension):
  1. Open a terminal in this folder.
  2. php -S localhost:8000
  3. Open http://localhost:8000
The database file planner.sqlite is created automatically on first visit.

Structure:
  *.php        pages the user can open (login, dashboard, projects, ...)
  includes/    shared code: functions.php, header.php, footer.php
  actions/     POST-only endpoints: task_action.php, logout.php
  assets/      style.css

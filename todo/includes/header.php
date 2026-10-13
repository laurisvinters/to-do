<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= escape($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (getCurrentUserId() !== null) {
    $invitationCount = runQuery(
        "SELECT COUNT(*) FROM project_members WHERE user_id = ? AND status = 'invited'",
        [getCurrentUserId()]
    )->fetchColumn(); ?>
  <nav id="sidebar">
    <div class="logo">✔ To-Do</div>
    <a href="dashboard.php">Dashboard</a>
    <a href="projects.php">Projects<?= $invitationCount > 0 ? " (" . (int) $invitationCount . " new)" : "" ?></a>
    <p><?= escape($_SESSION["user_name"]) ?></p>
    <form method="post" action="actions/logout.php"><?= csrfField() ?><button class="light-button">Log out</button></form>
  </nav>
<?php } ?>
<main>

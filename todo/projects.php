<?php
require __DIR__ . "/includes/functions.php";
requireLogin();
$userId = getCurrentUserId();
$errorText = "";

// 1. Handle forms
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $actionName = $_POST["action"] ?? "";

    if ($actionName === "create") {
        $projectName = trim($_POST["name"] ?? "");
        if (mb_strlen($projectName) < 2 || mb_strlen($projectName) > 60) {
            $errorText = "Project name must have 2 to 60 characters.";
        } else {
            $database = getDatabase();
            $database->beginTransaction();
            runQuery("INSERT INTO projects (name, owner_id) VALUES (?, ?)", [$projectName, $userId]);
            $newProjectId = $database->lastInsertId();
            runQuery("INSERT INTO project_members (project_id, user_id, status) VALUES (?, ?, 'accepted')", [$newProjectId, $userId]);
            $database->commit();
            redirectTo("project.php?id=" . $newProjectId);
        }
    }

    if ($actionName === "accept" || $actionName === "decline") {
        $newStatus = $actionName === "accept" ? "accepted" : "declined";
        runQuery(
            "UPDATE project_members SET status = ? WHERE project_id = ? AND user_id = ? AND status = 'invited'",
            [$newStatus, (int) ($_POST["project_id"] ?? 0), $userId]
        );
        redirectTo("projects.php");
    }
}

// 2. Load data
$invitationsList = runQuery(
    "SELECT projects.id, projects.name, users.name AS owner_name FROM project_members
     JOIN projects ON projects.id = project_members.project_id
     JOIN users ON users.id = projects.owner_id
     WHERE project_members.user_id = ? AND project_members.status = 'invited'",
    [$userId]
)->fetchAll();

$projectsList = runQuery(
    "SELECT projects.id, projects.name, projects.owner_id,
            (SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id) AS task_count,
            (SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.completed = 1) AS done_count
     FROM project_members JOIN projects ON projects.id = project_members.project_id
     WHERE project_members.user_id = ? AND project_members.status = 'accepted'
     ORDER BY projects.name",
    [$userId]
)->fetchAll();

// 3. Show the page
$pageTitle = "Projects";
require __DIR__ . "/includes/header.php";
?>
<h1>Projects</h1>

<?php if (count($invitationsList) > 0) { ?>
  <div class="card">
    <h3>Invitations</h3>
    <?php foreach ($invitationsList as $invitation) { ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="project_id" value="<?= (int) $invitation["id"] ?>">
        <?= escape($invitation["owner_name"]) ?> invited you to <b><?= escape($invitation["name"]) ?></b>
        <button name="action" value="accept">Accept</button>
        <button name="action" value="decline" class="light-button">Decline</button>
      </form>
    <?php } ?>
  </div>
<?php } ?>

<form method="post" class="card" style="margin: 15px 0">
  <h3>Create project</h3>
  <?php if ($errorText !== "") { ?><p class="error-message"><?= escape($errorText) ?></p><?php } ?>
  <?= csrfField() ?>
  <input type="hidden" name="action" value="create">
  <input type="text" name="name" placeholder="Project name" required maxlength="60">
  <button type="submit">Create project</button>
</form>

<div class="cards-grid">
  <?php foreach ($projectsList as $project) { ?>
    <a class="card" href="project.php?id=<?= (int) $project["id"] ?>">
      <h3><?= escape($project["name"]) ?></h3>
      <p><?= $project["owner_id"] == $userId ? "You are the owner" : "Member" ?> · <?= (int) $project["done_count"] ?>/<?= (int) $project["task_count"] ?> tasks done</p>
      <progress value="<?= (int) $project["done_count"] ?>" max="<?= max(1, (int) $project["task_count"]) ?>"></progress>
    </a>
  <?php } ?>
</div>
<?php require __DIR__ . "/includes/footer.php"; ?>

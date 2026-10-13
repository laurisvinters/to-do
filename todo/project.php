<?php
require __DIR__ . "/includes/functions.php";
requireLogin();
$userId = getCurrentUserId();
$messageText = "";
$errorText = "";

// 1. Check that this user is an accepted member
$projectId = (int) ($_GET["id"] ?? 0);
$project = runQuery("SELECT * FROM projects WHERE id = ?", [$projectId])->fetch();
if (!$project) {
    http_response_code(404);
    exit("Project not found.");
}
if (!isAcceptedMember($projectId, $userId)) {
    denyAccess();
}
$isOwner = $project["owner_id"] == $userId;

// 2. Handle POST forms (owner only)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$isOwner) {
        denyAccess();
    }
    checkCsrf();
    $actionName = $_POST["action"] ?? "";

    if ($actionName === "delete") {
        // Tasks and member rows are removed by ON DELETE CASCADE
        runQuery("DELETE FROM projects WHERE id = ?", [$projectId]);
        redirectTo("projects.php");
    }

    if ($actionName === "invite") {
        $emailText = strtolower(trim($_POST["email"] ?? ""));
        $invitedUser = runQuery("SELECT id FROM users WHERE email = ?", [$emailText])->fetch();

        if (!filter_var($emailText, FILTER_VALIDATE_EMAIL)) {
            $errorText = "Email address is not valid.";
        } elseif (!$invitedUser) {
            $errorText = "No registered user has this email.";
        } else {
            $oldStatus = runQuery(
                "SELECT status FROM project_members WHERE project_id = ? AND user_id = ?",
                [$projectId, $invitedUser["id"]]
            )->fetchColumn();

            if ($oldStatus === false) {
                runQuery("INSERT INTO project_members (project_id, user_id, status) VALUES (?, ?, 'invited')", [$projectId, $invitedUser["id"]]);
                $messageText = "Invitation sent.";
            } elseif ($oldStatus === "declined") {
                runQuery("UPDATE project_members SET status = 'invited' WHERE project_id = ? AND user_id = ?", [$projectId, $invitedUser["id"]]);
                $messageText = "Invitation sent again.";
            } else {
                $errorText = "This user is already a member or already invited.";
            }
        }
    }
}

// 3. Load members and tasks
$memberStatusFilter = $isOwner ? "" : " AND project_members.status = 'accepted'";
$membersList = runQuery(
    "SELECT users.name, project_members.status FROM project_members
     JOIN users ON users.id = project_members.user_id
     WHERE project_members.project_id = ?" . $memberStatusFilter . " ORDER BY users.name",
    [$projectId]
)->fetchAll();

$tasksList = runQuery(
    TASK_SELECT_SQL . " WHERE tasks.project_id = ? ORDER BY tasks.completed ASC, tasks.due_date ASC",
    [$projectId]
)->fetchAll();

// 4. Show the page
$pageTitle = $project["name"];
require __DIR__ . "/includes/header.php";
?>
<h1><?= escape($project["name"]) ?></h1>
<p><a href="projects.php">← All projects</a></p>

<div class="card">
  <h3>Members</h3>
  <ul>
    <?php foreach ($membersList as $member) { ?>
      <li><?= escape($member["name"]) ?> (<?= escape($member["status"]) ?>)</li>
    <?php } ?>
  </ul>
  <?php if ($isOwner) { ?>
    <form method="post">
      <?php if ($errorText !== "") { ?><p class="error-message"><?= escape($errorText) ?></p><?php } ?>
      <?php if ($messageText !== "") { ?><p class="success-message"><?= escape($messageText) ?></p><?php } ?>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="invite">
      <input type="email" name="email" placeholder="Email of a registered user" required>
      <button type="submit">Invite</button>
    </form>
  <?php } ?>
</div>

<p><a class="button" href="task_form.php?project_id=<?= $projectId ?>">+ Add project task</a></p>
<div class="card"><?php showTasksTable($tasksList); ?></div>

<?php if ($isOwner) { ?>
  <form method="post" style="margin-top: 15px" onsubmit="return confirm('Delete this project and all its tasks?')">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <button class="light-button">Delete project</button>
  </form>
<?php } ?>
<?php require __DIR__ . "/includes/footer.php"; ?>

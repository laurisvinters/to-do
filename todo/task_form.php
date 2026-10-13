<?php
require __DIR__ . "/includes/functions.php";
requireLogin();
$userId = getCurrentUserId();
$errorsList = [];

// 1. Find the task (edit) or the project (new project task)
$taskId = (int) ($_GET["id"] ?? 0);
if ($taskId > 0) {
    $task = loadTask($taskId);
    if (!$task || !canChangeTask($task, $userId)) {
        denyAccess();
    }
    $projectId = $task["project_id"];
} else {
    $projectId = (int) ($_GET["project_id"] ?? 0);
    if ($projectId === 0) {
        $projectId = null;
    } elseif (!isAcceptedMember($projectId, $userId)) {
        denyAccess();
    }
    $task = ["title" => "", "description" => "", "due_date" => date("Y-m-d"), "priority" => "Medium", "assigned_user_id" => null];
}
$membersList = $projectId === null ? [] : loadAcceptedMembers($projectId);

// 2. Handle the form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    [$errorsList, $formValues] = readTaskForm();

    $assignedUserId = null;
    $postedUserId = (int) ($_POST["assigned_user_id"] ?? 0);
    if ($projectId !== null && $postedUserId > 0) {
        if (isAcceptedMember($projectId, $postedUserId)) {
            $assignedUserId = $postedUserId;
        } else {
            $errorsList[] = "The assigned user is not a member of this project.";
        }
    }

    if (count($errorsList) === 0) {
        if ($taskId > 0) {
            runQuery(
                "UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, assigned_user_id = ? WHERE id = ?",
                [$formValues["title"], $formValues["description"], $formValues["due_date"], $formValues["priority"], $assignedUserId, $taskId]
            );
        } else {
            runQuery(
                "INSERT INTO tasks (title, description, due_date, priority, owner_id, project_id, assigned_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$formValues["title"], $formValues["description"], $formValues["due_date"], $formValues["priority"], $userId, $projectId, $assignedUserId]
            );
        }
        $defaultPage = $projectId === null ? "/dashboard.php" : "/project.php?id=" . $projectId;
        redirectTo(safeReturnPage($defaultPage));
    }
    $task = array_merge($task, $formValues, ["assigned_user_id" => $assignedUserId]);
}

// 3. Show the page
$pageTitle = $taskId > 0 ? "Edit task" : "New task";
require __DIR__ . "/includes/header.php";
?>
<form class="form-page card" method="post">
  <h1><?= escape($pageTitle) ?></h1>
  <?php foreach ($errorsList as $errorText) { ?><p class="error-message"><?= escape($errorText) ?></p><?php } ?>
  <?= csrfField() ?>
  <label>Title</label>
  <input type="text" name="title" value="<?= escape($task["title"]) ?>" required maxlength="100">
  <label>Description</label>
  <textarea name="description" maxlength="500"><?= escape($task["description"]) ?></textarea>
  <label>Due date</label>
  <input type="date" name="due_date" value="<?= escape($task["due_date"]) ?>" required>
  <label>Priority</label>
  <select name="priority">
    <?php foreach (["Low", "Medium", "High"] as $priorityName) { ?>
      <option <?= $task["priority"] === $priorityName ? "selected" : "" ?>><?= $priorityName ?></option>
    <?php } ?>
  </select>
  <?php if ($projectId !== null) { ?>
    <label>Assigned to</label>
    <select name="assigned_user_id">
      <option value="0">Nobody</option>
      <?php foreach ($membersList as $member) { ?>
        <option value="<?= (int) $member["id"] ?>" <?= $task["assigned_user_id"] == $member["id"] ? "selected" : "" ?>><?= escape($member["name"]) ?></option>
      <?php } ?>
    </select>
  <?php } ?>
  <button type="submit">Save task</button>
  <p><a href="<?= escape(safeReturnPage($projectId === null ? "/dashboard.php" : "/project.php?id=" . (int) $projectId)) ?>">Cancel</a></p>
</form>
<?php require __DIR__ . "/includes/footer.php"; ?>

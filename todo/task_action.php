<?php
require "functions.php";
requireLogin();
$userId = getCurrentUserId();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("dashboard.php");
}
checkCsrf();

// 1. Load the task and check rights
$task = loadTask((int) ($_POST["task_id"] ?? 0));
if (!$task) {
    denyAccess();
}
$actionName = $_POST["action"] ?? "";

// 2. Do the action
if ($actionName === "toggle" && canChangeTask($task, $userId)) {
    runQuery("UPDATE tasks SET completed = 1 - completed WHERE id = ?", [$task["id"]]);
} elseif ($actionName === "delete" && canDeleteTask($task, $userId)) {
    runQuery("DELETE FROM tasks WHERE id = ?", [$task["id"]]);
} else {
    denyAccess();
}

// 3. Go back to the page the button was clicked on
$defaultPage = $task["project_id"] === null ? "dashboard.php" : "project.php?id=" . (int) $task["project_id"];
redirectTo(safeReturnPage($defaultPage));

<?php
require __DIR__ . "/includes/functions.php";
requireLogin();
$userId = getCurrentUserId();

// 1. Read filter and sort (only allowed values)
$statusFilter = $_GET["status"] ?? "active";
if (!in_array($statusFilter, ["active", "completed", "all"], true)) {
    $statusFilter = "active";
}
$sortOptionsList = [
    "due_date" => "tasks.due_date ASC",
    "priority" => "CASE tasks.priority WHEN 'High' THEN 1 WHEN 'Medium' THEN 2 ELSE 3 END, tasks.due_date ASC",
];
$sortName = $_GET["sort"] ?? "due_date";
if (!isset($sortOptionsList[$sortName])) {
    $sortName = "due_date";
}

// 2. Load tasks: my personal tasks and project tasks assigned to me
$whereText = "((tasks.project_id IS NULL AND tasks.owner_id = :user_id) OR tasks.assigned_user_id = :user_id)";
$counts = runQuery(
    "SELECT COUNT(*) AS total, COALESCE(SUM(completed), 0) AS done FROM tasks WHERE " . $whereText,
    ["user_id" => $userId]
)->fetch();

$filterText = "";
if ($statusFilter === "active") {
    $filterText = " AND tasks.completed = 0";
} elseif ($statusFilter === "completed") {
    $filterText = " AND tasks.completed = 1";
}
$tasksList = runQuery(
    TASK_SELECT_SQL . " WHERE " . $whereText . $filterText . " ORDER BY " . $sortOptionsList[$sortName],
    ["user_id" => $userId]
)->fetchAll();

// 3. Show the page
$pageTitle = "Dashboard";
require __DIR__ . "/includes/header.php";
?>
<h1>Dashboard</h1>
<div class="statistics-row">
  <div class="statistic-card statistic-total">Total tasks<strong><?= (int) $counts["total"] ?></strong></div>
  <div class="statistic-card statistic-completed">Completed<strong><?= (int) $counts["done"] ?></strong></div>
  <div class="statistic-card">Active<strong><?= (int) $counts["total"] - (int) $counts["done"] ?></strong></div>
</div>

<p>
  <a class="button" href="task_form.php">+ Add task</a>
  <a class="button <?= $statusFilter === "active" ? "" : "light-button" ?>" href="?status=active&sort=<?= $sortName ?>">Active</a>
  <a class="button <?= $statusFilter === "completed" ? "" : "light-button" ?>" href="?status=completed&sort=<?= $sortName ?>">Completed</a>
  <a class="button <?= $statusFilter === "all" ? "" : "light-button" ?>" href="?status=all&sort=<?= $sortName ?>">All</a>
  Sort by:
  <a href="?status=<?= $statusFilter ?>&sort=due_date">Due date</a> |
  <a href="?status=<?= $statusFilter ?>&sort=priority">Priority</a>
</p>

<div class="card"><?php showTasksTable($tasksList); ?></div>
<?php require __DIR__ . "/includes/footer.php"; ?>

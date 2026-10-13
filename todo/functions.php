<?php
// ===== 1. Session =====
session_start();
header("Cache-Control: no-store");

// ===== 2. Database =====
function getDatabase()
{
    static $database = null;
    if ($database === null) {
        $database = new PDO("sqlite:" . __DIR__ . "/planner.sqlite");
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $database->exec("PRAGMA foreign_keys = ON");
        $database->exec("PRAGMA busy_timeout = 5000");
        createTables($database);
    }
    return $database;
}

function createTables($database)
{
    $database->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            owner_id INTEGER NOT NULL REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS project_members (
            project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id),
            status TEXT NOT NULL,
            PRIMARY KEY (project_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            due_date TEXT NOT NULL,
            priority TEXT NOT NULL,
            completed INTEGER NOT NULL DEFAULT 0,
            owner_id INTEGER NOT NULL REFERENCES users(id),
            project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
            assigned_user_id INTEGER REFERENCES users(id)
        );
        CREATE INDEX IF NOT EXISTS idx_tasks_owner ON tasks(owner_id);
        CREATE INDEX IF NOT EXISTS idx_tasks_project ON tasks(project_id);
        CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks(assigned_user_id);
        CREATE INDEX IF NOT EXISTS idx_members_user ON project_members(user_id);
    ");
}

function runQuery($sqlText, $parametersList = [])
{
    $statement = getDatabase()->prepare($sqlText);
    $statement->execute($parametersList);
    return $statement;
}

// ===== 3. Page helpers =====
function escape($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, "UTF-8");
}

function redirectTo($pageName)
{
    header("Location: " . $pageName);
    exit;
}

function denyAccess()
{
    http_response_code(403);
    exit("You do not have access to this page.");
}

// Only allow redirects back to our own list pages (no open redirects).
function safeReturnPage($defaultPage)
{
    $target = $_POST["redirect"] ?? $_GET["redirect"] ?? "";
    if (preg_match('~^/?(dashboard|project|projects)\.php(\?[a-zA-Z0-9_=&%:-]*)?$~', $target)) {
        return $target;
    }
    return $defaultPage;
}

function getCurrentUserId()
{
    return $_SESSION["user_id"] ?? null;
}

function requireLogin()
{
    if (getCurrentUserId() === null) {
        redirectTo("login.php");
    }
}

// ===== 4. Form protection =====
function getCsrfToken()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(16));
    }
    return $_SESSION["csrf_token"];
}

function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . getCsrfToken() . '">';
}

function checkCsrf()
{
    if (!hash_equals(getCsrfToken(), $_POST["csrf_token"] ?? "")) {
        denyAccess();
    }
}

// ===== 5. Project rules =====
function isAcceptedMember($projectId, $userId)
{
    $status = runQuery(
        "SELECT status FROM project_members WHERE project_id = ? AND user_id = ?",
        [$projectId, $userId]
    )->fetchColumn();
    return $status === "accepted";
}

function loadAcceptedMembers($projectId)
{
    return runQuery(
        "SELECT users.id, users.name FROM project_members
         JOIN users ON users.id = project_members.user_id
         WHERE project_members.project_id = ? AND project_members.status = 'accepted'
         ORDER BY users.name",
        [$projectId]
    )->fetchAll();
}

// ===== 6. Task rules =====
const TASK_SELECT_SQL = "
    SELECT tasks.*, projects.name AS project_name, projects.owner_id AS project_owner_id,
           assigned_users.name AS assigned_name
    FROM tasks
    LEFT JOIN projects ON projects.id = tasks.project_id
    LEFT JOIN users AS assigned_users ON assigned_users.id = tasks.assigned_user_id ";

function loadTask($taskId)
{
    return runQuery(TASK_SELECT_SQL . " WHERE tasks.id = ?", [$taskId])->fetch();
}

// Personal task: only its owner. Project task: owner of project, creator or assigned member.
function canChangeTask($task, $userId)
{
    if ($task["project_id"] === null) {
        return $task["owner_id"] == $userId;
    }
    if (!isAcceptedMember($task["project_id"], $userId)) {
        return false;
    }
    return $task["project_owner_id"] == $userId
        || $task["owner_id"] == $userId
        || $task["assigned_user_id"] == $userId;
}

// Delete: only the creator or the project owner.
function canDeleteTask($task, $userId)
{
    if ($task["project_id"] === null) {
        return $task["owner_id"] == $userId;
    }
    return $task["project_owner_id"] == $userId || $task["owner_id"] == $userId;
}

function readTaskForm()
{
    $formValues = [
        "title" => trim($_POST["title"] ?? ""),
        "description" => trim($_POST["description"] ?? ""),
        "due_date" => $_POST["due_date"] ?? "",
        "priority" => $_POST["priority"] ?? "",
    ];
    $errorsList = [];

    if ($formValues["title"] === "" || mb_strlen($formValues["title"]) > 100) {
        $errorsList[] = "Title is required and can have at most 100 characters.";
    }
    if (mb_strlen($formValues["description"]) > 500) {
        $errorsList[] = "Description can have at most 500 characters.";
    }
    $parsedDate = DateTime::createFromFormat("Y-m-d", $formValues["due_date"]);
    if (!$parsedDate || $parsedDate->format("Y-m-d") !== $formValues["due_date"]) {
        $errorsList[] = "Due date is not valid.";
    }
    if (!in_array($formValues["priority"], ["Low", "Medium", "High"], true)) {
        $errorsList[] = "Priority must be Low, Medium or High.";
    }
    return [$errorsList, $formValues];
}

// ===== 7. Task table =====
function showTaskButton($task, $actionName, $buttonText, $buttonClass, $needsConfirm = false)
{
    $confirmText = $needsConfirm ? ' onsubmit="return confirm(\'Are you sure?\')"' : "";
    echo '<form method="post" action="task_action.php" style="display:inline"' . $confirmText . '>'
        . csrfField()
        . '<input type="hidden" name="redirect" value="' . escape($_SERVER["REQUEST_URI"] ?? "") . '">'
        . '<input type="hidden" name="task_id" value="' . (int) $task["id"] . '">'
        . '<input type="hidden" name="action" value="' . $actionName . '">'
        . '<button class="' . $buttonClass . '">' . $buttonText . '</button></form> ';
}

function showTasksTable($tasksList)
{
    if (count($tasksList) === 0) {
        echo "<p>No tasks here yet.</p>";
        return;
    }
    $userId = getCurrentUserId();

    echo '<div class="table-wrapper"><table><thead><tr>
          <th>Task</th><th>Priority</th><th>Due date</th><th>Project / assigned to</th><th>Actions</th>
          </tr></thead><tbody>';

    foreach ($tasksList as $task) {
        $titleClass = $task["completed"] ? "completed-task" : "";
        $placeText = $task["project_name"] === null ? "Personal" : $task["project_name"];
        if ($task["assigned_name"] !== null) {
            $placeText .= " / " . $task["assigned_name"];
        }

        echo "<tr>";
        echo '<td class="' . $titleClass . '"><b>' . escape($task["title"]) . "</b><br><small>" . escape($task["description"]) . "</small></td>";
        echo '<td class="priority-' . escape($task["priority"]) . '">' . escape($task["priority"]) . "</td>";
        echo "<td>" . escape($task["due_date"]) . "</td>";
        echo "<td>" . escape($placeText) . "</td><td>";
        if (canChangeTask($task, $userId)) {
            showTaskButton($task, "toggle", $task["completed"] ? "Mark active" : "Mark done", "button");
            echo '<a class="button light-button" href="task_form.php?id=' . (int) $task["id"]
                . "&redirect=" . urlencode($_SERVER["REQUEST_URI"] ?? "") . '">Edit</a> ';
        }
        if (canDeleteTask($task, $userId)) {
            showTaskButton($task, "delete", "Delete", "light-button", true);
        }
        echo "</td></tr>";
    }
    echo "</tbody></table></div>";
}

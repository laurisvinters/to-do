<?php
require "functions.php";
if (getCurrentUserId() !== null) {
    redirectTo("dashboard.php");
}
$errorsList = [];
$nameText = "";
$emailText = "";

// 1. Handle the form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $nameText = trim($_POST["name"] ?? "");
    $emailText = strtolower(trim($_POST["email"] ?? ""));
    $passwordText = $_POST["password"] ?? "";

    if (mb_strlen($nameText) < 2 || mb_strlen($nameText) > 50) {
        $errorsList[] = "Name must have 2 to 50 characters.";
    }
    if (!filter_var($emailText, FILTER_VALIDATE_EMAIL)) {
        $errorsList[] = "Email address is not valid.";
    }
    if (strlen($passwordText) < 8 || strlen($passwordText) > 72) {
        $errorsList[] = "Password must have 8 to 72 characters.";
    }
    if (count($errorsList) === 0) {
        $emailExists = runQuery("SELECT id FROM users WHERE email = ?", [$emailText])->fetch();
        if ($emailExists) {
            $errorsList[] = "This email is already registered.";
        }
    }
    if (count($errorsList) === 0) {
        try {
            runQuery(
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)",
                [$nameText, $emailText, password_hash($passwordText, PASSWORD_DEFAULT)]
            );
            redirectTo("login.php?registered=1");
        } catch (PDOException $exception) {
            if (!str_contains($exception->getMessage(), "UNIQUE")) {
                throw $exception;
            }
            $errorsList[] = "This email is already registered.";
        }
    }
}

// 2. Show the page
$pageTitle = "Create account";
require "header.php";
?>
<form class="form-page card" method="post">
  <h1>Create your account</h1>
  <?php foreach ($errorsList as $errorText) { ?><p class="error-message"><?= escape($errorText) ?></p><?php } ?>
  <?= csrfField() ?>
  <label>Full name</label>
  <input type="text" name="name" value="<?= escape($nameText) ?>" required maxlength="50">
  <label>Email address</label>
  <input type="email" name="email" value="<?= escape($emailText) ?>" required>
  <label>Password (at least 8 characters)</label>
  <input type="password" name="password" minlength="8" maxlength="72" required>
  <button type="submit">Create account</button>
  <p>Already have an account? <a href="login.php">Log in</a></p>
</form>
<?php require "footer.php"; ?>

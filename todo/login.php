<?php
require "functions.php";
if (getCurrentUserId() !== null) {
    redirectTo("dashboard.php");
}
$errorText = "";

// 1. Handle the form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    $emailText = strtolower(trim($_POST["email"] ?? ""));
    $passwordText = $_POST["password"] ?? "";

    $user = runQuery("SELECT * FROM users WHERE email = ?", [$emailText])->fetch();
    if ($user && password_verify($passwordText, $user["password_hash"])) {
        session_regenerate_id(true);
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        redirectTo("dashboard.php");
    }
    $errorText = "Wrong email or password.";
}

// 2. Show the page
$pageTitle = "Log in";
require "header.php";
?>
<form class="form-page card" method="post">
  <h1>Log in</h1>
  <?php if (isset($_GET["registered"])) { ?><p class="success-message">Account created. You can log in now.</p><?php } ?>
  <?php if ($errorText !== "") { ?><p class="error-message"><?= escape($errorText) ?></p><?php } ?>
  <?= csrfField() ?>
  <label>Email address</label>
  <input type="email" name="email" required>
  <label>Password</label>
  <input type="password" name="password" required>
  <button type="submit">Log in</button>
  <p>No account yet? <a href="register.php">Sign up</a></p>
</form>
<?php require "footer.php"; ?>

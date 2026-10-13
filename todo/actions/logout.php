<?php
require dirname(__DIR__) . "/includes/functions.php";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    session_destroy();
}
redirectTo("/login.php");

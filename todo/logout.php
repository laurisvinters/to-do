<?php
require "functions.php";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    checkCsrf();
    session_destroy();
}
redirectTo("login.php");

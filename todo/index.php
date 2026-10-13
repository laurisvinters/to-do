<?php
require __DIR__ . "/includes/functions.php";
redirectTo(getCurrentUserId() === null ? "login.php" : "dashboard.php");

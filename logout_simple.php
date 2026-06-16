<?php
session_start();
session_destroy();
header("Location: test_login_simple.php");
exit();
?>
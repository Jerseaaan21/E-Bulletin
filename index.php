<?php
// Change this to the page you want to redirect to
$redirectTo = "login.php";

// Redirect
header("Location: $redirectTo");
exit();

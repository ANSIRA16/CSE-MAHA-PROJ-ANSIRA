
<?php

session_start();

// Remove all session variables.
$_SESSION = [];

// Delete the session cookie if cookies are enabled.
if (ini_get("session.use_cookies")) {

    $cookieParams = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $cookieParams["path"],
        $cookieParams["domain"],
        $cookieParams["secure"],
        $cookieParams["httponly"]
    );
}

// Destroy the server-side session.
session_destroy();

// Return to the home page.
header("Location: index.php");
exit();

?>

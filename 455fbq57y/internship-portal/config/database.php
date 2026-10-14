
<?php

$host = "db01.dbhost.dev";
$port = 5051;
$dbname = "db_455fbn362";
$username = "user_455fbn362";
$password = "p455fbn362";

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $pdo->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

} catch (PDOException $e) {

    error_log(
        "Database connection failed: " . $e->getMessage()
    );

    die(
        "Unable to connect to the database. Please check your configuration."
    );
}
?>

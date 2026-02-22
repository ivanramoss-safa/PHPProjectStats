<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=futbol_stats_db', 'root', '');
    $pdo->exec("UPDATE user SET roles = '[\"ROLE_ADMIN\"]'");
    echo "Success: all users promoted to admin for testing.";
}
catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

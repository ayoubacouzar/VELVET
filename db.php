<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');   
define('DB_PASS', '');       
define('DB_NAME', 'bd_velvet');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;color:red;padding:20px;">Connexion DB échouée : '.htmlspecialchars($e->getMessage()).'</p>');
}

<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=born_angel_db', 'root', 'Iamqueen0403');
    echo "Connected successfully to DB via PDO\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}

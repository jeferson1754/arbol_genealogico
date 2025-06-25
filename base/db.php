<?php
$host = 'localhost';
$db = 'genealogy';
$user = 'root'; // Cambia esto según tu configuración
$pass = ''; // Cambia esto según tu configuración

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}

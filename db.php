<?php

/**
 * db.php
 *
 * Archivo de configuración para la conexión a la base de datos MySQL.
 */

$servername = "localhost";          // Generalmente 'localhost'
$username = "root";                 // Tu usuario de MySQL (por defecto en XAMPP es 'root')
$password = "";                     // Tu contraseña de MySQL (por defecto en XAMPP está vacía)
$dbname = "genealogia";     // El nombre de tu base de datos

// --- Crear la conexión ---
$conn = new mysqli($servername, $username, $password, $dbname);

// Establecer el juego de caracteres a utf8mb4 para soportar emojis y caracteres especiales
if (!$conn->set_charset("utf8mb4")) {
    // Si falla, muestra un error. En producción, esto debería registrarse en un archivo de log.
    error_log("Error cargando el conjunto de caracteres utf8mb4: %s\n", $conn->error);
}

// --- Verificar la conexión ---
if ($conn->connect_error) {
    // Si la conexión falla, termina el script y muestra un error.
    // En un entorno de producción, nunca muestres el error detallado al usuario.
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos.',
        'error_details' => 'Revisa las credenciales en el archivo db.php' // Mensaje para el desarrollador
    ]));
}

// La variable $conn ya está lista para ser usada en otros archivos que la incluyan.

$dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación de sentencias preparadas
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos.',
        'error_details' => $e->getMessage() // Solo mostrar esto en desarrollo
    ]);
    exit;
}

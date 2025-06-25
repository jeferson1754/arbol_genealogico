<?php
// get_persons.php

header('Content-Type: application/json');


require_once '../includes/database.php';
// Conectar a la base de datos
$conn = new mysqli($host, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión: " . $conn->connect_error]);
    exit;
}

// Consulta de personas
$sql = "SELECT id, nombre AS name, fecha_nacimiento AS birthDate, fecha_muerte AS deathDate,
               edad_muerte AS ageAtDeath, genero AS gender, foto AS photo, notas AS notes
        FROM personas";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Error en la consulta SQL: " . $conn->error]);
    exit;
}




// Resultado
$personas = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Obtener ascendentes y descendientes
        $id = $row["id"];

        // Ascendientes
        $ascQuery = "SELECT id_ascendiente FROM ascendientes WHERE id_descendiente = $id";
        $ascResult = $conn->query($ascQuery);
        $ascendants = [];
        while ($asc = $ascResult->fetch_assoc()) {
            $ascendants[] = (int)$asc["id_ascendiente"];
        }

        // Descendientes
        $descQuery = "SELECT id_descendiente FROM ascendientes WHERE id_ascendiente = $id";
        $descResult = $conn->query($descQuery);
        $descendants = [];
        while ($desc = $descResult->fetch_assoc()) {
            $descendants[] = (int)$desc["id_descendiente"];
        }

        // Agregar al array
        $row["ascendants"] = $ascendants;
        $row["descendants"] = $descendants;

        $personas[] = $row;
    }
}

echo json_encode($personas);

$conn->close();

<?php
// api/persons.php

header('Content-Type: application/json');
require_once '../includes/database.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Obtener lista de personas o una persona específica
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
            $stmt->execute([$id]);
            $person = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($person) {
                // Obtener relaciones
                $stmt = $pdo->prepare("SELECT id_ascendiente FROM ascendientes WHERE id_descendiente = ?");
                $stmt->execute([$id]);
                $person['ascendants'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $pdo->prepare("SELECT id_descendiente FROM ascendientes WHERE id_ascendiente = ?");
                $stmt->execute([$id]);
                $person['descendants'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

                echo json_encode($person);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Persona no encontrada']);
            }
        } else {
            // Paginación
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $itemsPerPage = isset($_GET['itemsPerPage']) ? (int)$_GET['itemsPerPage'] : 5;
            $offset = ($page - 1) * $itemsPerPage;

            // Obtener total de personas
            $total = $pdo->query("SELECT COUNT(*) FROM personas")->fetchColumn();
            $totalPages = ceil($total / $itemsPerPage);

            // Obtener personas paginadas
            $stmt = $pdo->prepare("SELECT * FROM personas LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $itemsPerPage, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'persons' => $persons,
                'totalPages' => $totalPages
            ]);
        }
        break;

    case 'POST':
        // Crear o actualizar persona
        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['id'])) {
            // Actualizar persona existente
            $stmt = $pdo->prepare("UPDATE personas SET 
                nombre = ?, fecha_nacimiento = ?, fecha_muerte = ?, 
                genero = ?, notas = ?, foto = ? 
                WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['birthDate'],
                $data['deathDate'] ?: null,
                $data['gender'],
                $data['notes'],
                $data['photo'],
                $data['id']
            ]);

            // Actualizar relaciones
            $pdo->prepare("DELETE FROM ascendientes WHERE id_descendiente = ?")->execute([$data['id']]);
            foreach ($data['ascendants'] as $ascId) {
                $pdo->prepare("INSERT INTO ascendientes (id_ascendiente, id_descendiente) VALUES (?, ?)")
                    ->execute([$ascId, $data['id']]);
            }

            echo json_encode(['success' => true, 'message' => 'Persona actualizada']);
        } else {
            // Crear nueva persona
            $stmt = $pdo->prepare("INSERT INTO personas 
                (nombre, fecha_nacimiento, fecha_muerte, genero, notas, foto) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['birthDate'],
                $data['deathDate'] ?: null,
                $data['gender'],
                $data['notes'],
                $data['photo'] ?: 'https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png'
            ]);
            $id = $pdo->lastInsertId();

            // Agregar relaciones
            foreach ($data['ascendants'] as $ascId) {
                $pdo->prepare("INSERT INTO ascendientes (id_ascendiente, id_descendiente) VALUES (?, ?)")
                    ->execute([$ascId, $id]);
            }

            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Persona creada']);
        }
        break;

    case 'DELETE':
        // Eliminar persona
        $id = $_GET['id'];

        // Verificar si la persona tiene descendientes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ascendientes WHERE id_ascendiente = ?");
        $stmt->execute([$id]);
        $hasDescendants = $stmt->fetchColumn() > 0;

        if ($hasDescendants) {
            http_response_code(400);
            echo json_encode(['error' => 'No se puede eliminar una persona con descendientes']);
        } else {
            // Eliminar relaciones primero
            $pdo->prepare("DELETE FROM ascendientes WHERE id_descendiente = ?")->execute([$id]);

            // Luego eliminar la persona
            $stmt = $pdo->prepare("DELETE FROM personas WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Persona eliminada']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}

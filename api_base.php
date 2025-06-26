<?php

/**
 * api.php
 *
 * Este script actúa como el backend para el árbol genealógico.
 * Se conecta a la base de datos MySQL, obtiene los datos de los familiares
 * y los devuelve en formato JSON. También maneja la actualización y adición de datos.
 */

// Incluir el archivo de configuración de la base de datos
require_once 'db.php';

// --- CABECERAS HTTP ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get_request($conn);
        break;
    case 'POST':
        handle_post_request($conn, $pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Método no permitido.']);
        break;
}

/**
 * Maneja las peticiones GET.
 */
function handle_get_request($conn)
{
    // Modificado para usar ALIASES en GROUP_CONCAT y evitar conflictos con JS
    $sql = "
        SELECT
            p.*,
            GROUP_CONCAT(DISTINCT pc_children.child_id) as children_ids_str,
            GROUP_CONCAT(DISTINCT pc_parents.parent_id) as parent_ids_str
        FROM
            people p
        LEFT JOIN
            parent_child pc_children ON p.id = pc_children.parent_id
        LEFT JOIN
            parent_child pc_parents ON p.id = pc_parents.child_id
        GROUP BY
            p.id
    ";

    $result = $conn->query($sql);

    if ($result) {
        $people_data = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['spouse_id'] = $row['spouse_id'] ? (int)$row['spouse_id'] : null;

            // Convertir cadenas de IDs a arrays de enteros
            $row['children_ids'] = $row['children_ids_str'] ? array_map('intval', explode(',', $row['children_ids_str'])) : [];
            $row['parent_ids'] = $row['parent_ids_str'] ? array_map('intval', explode(',', $row['parent_ids_str'])) : [];

            // Eliminar los campos string originales si ya no son necesarios
            unset($row['children_ids_str']);
            unset($row['parent_ids_str']);

            $people_data[] = $row;
        }
        echo json_encode($people_data);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error al ejecutar la consulta en la base de datos.', 'error' => $conn->error]);
    }
}

/**
 * Maneja las peticiones POST, enrutando a la función de acción específica.
 */
function handle_post_request($conn, $pdo)
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['_action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no especificada.']);
        return;
    }

    switch ($data['_action']) {
        case 'update_person':
            update_person($conn, $data);
            break;
        case 'add_descendant':
            add_descendant($conn, $data, $pdo);
            break;
        case 'add_ancestor':
            add_ancestor($conn, $data, $pdo);
            break;
        case 'add_spouse':
            add_spouse($conn, $data);
            break;
        case 'remove_parent_child': // <--- AÑADE ESTO
            remove_parent_child_link($conn, $data); // <--- AÑADE ESTO
            break;
        case 'remove_spouse': // <--- AÑADE ESTO
            remove_spouse_link($conn, $data, $pdo); // <--- AÑADE ESTO
            break;
        case 'link_existing_spouse': // <--- AÑADE ESTO
            link_existing_spouse($conn, $data); // <--- AÑADE ESTO
            break;
        case 'link_existing_parent_child': // <--- AÑADE ESTO
            link_existing_parent_child($conn, $data, $pdo); // <--- AÑADE ESTO
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción inválida.' . $data['_action']]);
            break;
    }
}

/**
 * Función base para insertar una nueva persona en la tabla 'people'.
 * @return int|false El ID de la nueva persona o false en caso de error.
 */
function insert_new_person($conn, $person_data)
{
    $stmt = $conn->prepare(
        "INSERT INTO people (name, relationship, rut, gender, dob, dom, dod, photo, spouse_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    // Coalesce empty strings to null for database if desired, or handle in frontend
    $name = $person_data['name'] ?? null;
    $relationship = $person_data['relationship'] ?? null;
    $rut = $person_data['rut'] ?? null;
    $gender = $person_data['gender'] ?? null;
    $dob = ($person_data['dob'] && $person_data['dob'] !== '0000-00-00') ? $person_data['dob'] : null;
    $dom = ($person_data['dom'] && $person_data['dom'] !== '0000-00-00') ? $person_data['dom'] : null;
    $dod = ($person_data['dod'] && $person_data['dod'] !== '0000-00-00') ? $person_data['dod'] : null;
    $photo = $person_data['photo'] ?? null;
    $spouse_id = isset($person_data['spouseId']) && $person_data['spouseId'] !== null ? (int)$person_data['spouseId'] : null;

    $stmt->bind_param(
        "ssssssssi", // s for string, i for integer (spouse_id)
        $name,
        $relationship,
        $rut,
        $gender,
        $dob,
        $dom,
        $dod,
        $photo,
        $spouse_id
    );
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        return $new_id;
    } else {
        error_log("Error inserting new person: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Función base para agregar un vínculo padre-hijo en la tabla 'parent_child'.
 * Previene duplicados.
 * @return bool True si el vínculo se agregó o ya existía, false en caso de error.
 */
function add_parent_child_link($conn, $pdo, $parent_id, $child_id)
{
    // Convertir a int para seguridad, aunque ya deberían serlo
    $parent_id = (int)$parent_id;
    $child_id = (int)$child_id;

    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM parent_child WHERE parent_id = ? AND child_id = ?");
    $check_stmt->execute([$parent_id, $child_id]);
    $count = $check_stmt->fetchColumn();

    if ($count > 0) {
        return true; // El vínculo ya existe, considerarlo un éxito
    }

    $stmt = $conn->prepare("INSERT INTO parent_child (parent_id, child_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $parent_id, $child_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        error_log("Error al agregar vínculo parent_child: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Actualiza los datos de una persona existente en la tabla 'people'.
 */
function update_person($conn, $data)
{
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El ID de la persona es requerido para actualizar.']);
        return;
    }
    $id = (int)$data['id'];

    $stmt = $conn->prepare(
        "UPDATE people SET name=?, relationship=?, rut=?, gender=?, dob=?, dom=?, dod=?, photo=?, spouse_id=? WHERE id=?"
    );

    // Mapear los datos para la actualización
    $name = $data['name'] ?? null;
    $relationship = $data['relationship'] ?? null;
    $rut = $data['rut'] ?? null;
    $gender = $data['gender'] ?? null;
    $dob = ($data['dob'] && $data['dob'] !== '0000-0000') ? $data['dob'] : null;
    $dom = ($data['dom'] && $data['dom'] !== '0000-00-00') ? $data['dom'] : null;
    $dod = ($data['dod'] && $data['dod'] !== '0000-00-00') ? $data['dod'] : null;
    $photo = $data['photo'] ?? null;
    $spouse_id = isset($data['spouseId']) && $data['spouseId'] !== null ? (int)$data['spouseId'] : null; // Asumiendo que spouseId puede venir en la data

    $stmt->bind_param(
        "ssssssssii", // 's' for string, 'i' for integer spouse_id and id
        $name,
        $relationship,
        $rut,
        $gender,
        $dob,
        $dom,
        $dod,
        $photo,
        $spouse_id,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Persona actualizada correctamente.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la persona.', 'error' => $stmt->error]);
    }
    $stmt->close();
}

/**
 * Agrega una nueva persona como descendiente de la persona seleccionada.
 */
function add_descendant($conn, $data, $pdo)
{
    if (!isset($data['selectedPersonId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de persona seleccionada es requerido.']);
        return;
    }
    $selected_person_id = (int)$data['selectedPersonId'];

    // Insertar la nueva persona (el descendiente)
    $new_child_id = insert_new_person($conn, $data['personData']);
    if ($new_child_id === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear la descendencia.']);
        return;
    }

    // Vincular a la persona seleccionada como padre del nuevo descendiente
    if (!add_parent_child_link($conn, $pdo, $selected_person_id, $new_child_id)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular el padre/madre con la descendencia.']);
        return;
    }

    // Si la persona seleccionada tiene un cónyuge, vincular también al cónyuge como padre
    // Es importante que el `spouseId` de la `selectedPerson` se envíe desde el frontend
    if (isset($data['selectedPersonSpouseId']) && $data['selectedPersonSpouseId'] !== null) {
        $spouse_id = (int)$data['selectedPersonSpouseId'];
        if (!add_parent_child_link($conn, $pdo, $spouse_id, $new_child_id)) {
            error_log("Error: No se pudo vincular al cónyuge ($spouse_id) con el nuevo hijo ($new_child_id)");
        }
    }

    echo json_encode(['success' => true, 'message' => 'Descendencia agregada correctamente.', 'new_person_id' => $new_child_id]);
}

/**
 * Agrega una nueva persona como ancestro de la persona seleccionada.
 */
function add_ancestor($conn, $data, $pdo)
{
    if (!isset($data['selectedPersonId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de persona seleccionada es requerido.']);
        return;
    }
    $selected_person_id = (int)$data['selectedPersonId'];

    // Insertar la nueva persona (el ancestro)
    $new_parent_id = insert_new_person($conn, $data['personData']);
    if ($new_parent_id === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear la ascendencia.']);
        return;
    }

    // Vincular al nuevo ancestro como padre de la persona seleccionada
    if (!add_parent_child_link($conn, $pdo, $new_parent_id, $selected_person_id)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular el nuevo ancestro con la persona seleccionada.']);
        return;
    }

    echo json_encode(['success' => true, 'message' => 'Ascendencia agregada correctamente.', 'new_person_id' => $new_parent_id]);
}


function add_spouse($conn, $data)
{
    if (!isset($data['selectedPersonId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de persona seleccionada es requerido.']);
        return;
    }
    $selected_person_id = (int)$data['selectedPersonId'];

    // 1. Insertar la nueva persona (el cónyuge)
    $new_spouse_id = insert_new_person($conn, $data['personData']);
    if ($new_spouse_id === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el cónyuge.']);
        return;
    }

    // 2. Actualizar a la persona seleccionada para que apunte al nuevo cónyuge
    $stmt1 = $conn->prepare("UPDATE people SET spouse_id = ? WHERE id = ?");
    $stmt1->bind_param("ii", $new_spouse_id, $selected_person_id);
    if (!$stmt1->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular cónyuge con persona seleccionada.', 'error' => $stmt1->error]);
        $stmt1->close();
        return;
    }
    $stmt1->close();

    // 3. Actualizar al nuevo cónyuge para que apunte a la persona seleccionada (vínculo bidireccional)
    $stmt2 = $conn->prepare("UPDATE people SET spouse_id = ? WHERE id = ?");
    $stmt2->bind_param("ii", $selected_person_id, $new_spouse_id);
    if (!$stmt2->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular persona seleccionada con nuevo cónyuge.', 'error' => $stmt2->error]);
        $stmt2->close();
        return;
    }
    $stmt2->close();

    echo json_encode(['success' => true, 'message' => 'Cónyuge agregado correctamente.', 'new_person_id' => $new_spouse_id]);
}

function remove_parent_child_link($conn, $data)
{
    if (!isset($data['parent_id']) || !isset($data['child_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'IDs de padre e hijo son requeridos para eliminar el vínculo.']);
        return;
    }
    // Asignar los valores a variables DIRECTAMENTE para que bind_param pueda tomarlos por referencia
    $parent_id = $data['parent_id'];
    $child_id = $data['child_id'];

    $stmt = $conn->prepare("DELETE FROM parent_child WHERE parent_id = ? AND child_id = ?");

    // Asegurarse de que $parent_id y $child_id se pasen por referencia
    // El cast a (int) ya lo hiciste al principio, pero si lo haces aquí directamente,
    // mysqli puede tener un problema si el valor original no es una variable.
    // Lo más seguro es que $parent_id y $child_id sean las variables a las que se hace referencia.
    $stmt->bind_param("ii", $parent_id, $child_id); // Esto debería funcionar si $parent_id y $child_id son variables directas

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vínculo padre-hijo eliminado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Vínculo no encontrado o ya eliminado.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el vínculo padre-hijo.', 'error' => $stmt->error]);
    }
    $stmt->close();
}


function remove_spouse_link($conn, $data, $pdo)
{
    if (!isset($data['person_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de persona es requerido para eliminar el vínculo de cónyuge.']);
        return;
    }
    $person_id = (int)$data['person_id'];

    // 1. Obtener el cónyuge actual para eliminar el vínculo recíproco
    // 1. Obtener el cónyuge actual para eliminar el vínculo recíproco
    $current_spouse_id = null;
    // 1. Obtener el cónyuge actual para eliminar el vínculo recíproco

    $stmt = $pdo->prepare("SELECT spouse_id FROM people WHERE id = ?");
    $stmt->execute([$person_id]);
    $spouse_id = $stmt->fetchColumn();

    if ($spouse_id !== false && $spouse_id !== null) {
        $current_spouse_id = (int)$spouse_id;
    }


    // 2. Establecer spouse_id a NULL para la persona actual
    $stmt1 = $conn->prepare("UPDATE people SET spouse_id = NULL WHERE id = ?");
    $stmt1->bind_param("i", $person_id);
    if (!$stmt1->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar vínculo de cónyuge de la persona principal.', 'error' => $stmt1->error]);
        $stmt1->close();
        return;
    }
    $stmt1->close();

    // 3. Si había un cónyuge, establecer también su spouse_id a NULL
    if ($current_spouse_id !== null) {
        $stmt2 = $conn->prepare("UPDATE people SET spouse_id = NULL WHERE id = ?");
        $stmt2->bind_param("i", $current_spouse_id);
        if (!$stmt2->execute()) {
            error_log("Error: No se pudo eliminar el vínculo de cónyuge recíproco para el cónyuge $current_spouse_id.");
            // Esto no debería hacer que falle toda la operación, solo loguear.
        }
        $stmt2->close();
    }

    echo json_encode(['success' => true, 'message' => 'Vínculo de cónyuge eliminado correctamente.']);
}

function link_existing_spouse($conn, $data)
{
    if (!isset($data['person1_id']) || !isset($data['person2_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Los IDs de ambas personas son requeridos para vincular cónyuges.']);
        return;
    }
    $person1_id = (int)$data['person1_id'];
    $person2_id = (int)$data['person2_id'];

    // Prevenir vincular a la misma persona consigo misma
    if ($person1_id === $person2_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No puedes vincular a una persona consigo misma como cónyuge.']);
        return;
    }

    // Actualizar spouse_id para la primera persona
    $stmt1 = $conn->prepare("UPDATE people SET spouse_id = ? WHERE id = ?");
    $stmt1->bind_param("ii", $person2_id, $person1_id);
    if (!$stmt1->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular cónyuge con persona 1.', 'error' => $stmt1->error]);
        $stmt1->close();
        return;
    }
    $stmt1->close();

    // Actualizar spouse_id para la segunda persona (vínculo recíproco)
    $stmt2 = $conn->prepare("UPDATE people SET spouse_id = ? WHERE id = ?");
    $stmt2->bind_param("ii", $person1_id, $person2_id);
    if (!$stmt2->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al vincular cónyuge con persona 2.', 'error' => $stmt2->error]);
        $stmt2->close();
        return;
    }
    $stmt2->close();

    echo json_encode(['success' => true, 'message' => 'Cónyuges vinculados correctamente.']);
}

/**
 * Vincula una persona existente como padre de otra persona existente.
 * Reusa add_parent_child_link.
 */
function link_existing_parent_child($conn, $data, $pdo)
{
    if (!isset($data['parent_id']) || !isset($data['child_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Los IDs de padre e hijo son requeridos para vincular.']);
        return;
    }
    $parent_id = (int)$data['parent_id'];
    $child_id = (int)$data['child_id'];

    if ($parent_id === $child_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No puedes vincular una persona consigo misma como padre/hijo.']);
        return;
    }

    if (add_parent_child_link($conn, $pdo, $parent_id, $child_id)) {
        echo json_encode(['success' => true, 'message' => 'Vínculo padre-hijo creado correctamente.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el vínculo padre-hijo.']);
    }
}


// Cierra la conexión a la base de datos al final del script
$conn->close();

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
        // Pasamos la conexión PDO también a las funciones POST que la necesiten
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
            GROUP_CONCAT(DISTINCT pc_children.child_id) AS children_ids_str,
            GROUP_CONCAT(DISTINCT pc_parents.parent_id) AS parent_ids_str
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
            // Asegurarse de que si GROUP_CONCAT devuelve NULL (sin hijos/padres), esto sea un array vacío.
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
        case 'edit_person':
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
        case 'remove_parent_child':
            remove_parent_child_link($conn, $data);
            break;
        case 'remove_spouse':
            remove_spouse_link($conn, $data, $pdo);
            break;
        case 'link_existing_spouse':
            link_existing_spouse($conn, $data);
            break;
        case 'link_existing_parent_child':
            link_existing_parent_child($conn, $data, $pdo);
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
    // Coalesce empty strings to null for database
    $name = $person_data['name'] ?? null;
    $relationship = $person_data['relationship'] ?? null;
    $rut = $person_data['rut'] ?? null;
    $gender = $person_data['gender'] ?? null;
    // Convertir fechas vacías a NULL para la base de datos
    $dob = (!empty($person_data['dob']) && $person_data['dob'] !== '0000-00-00') ? $person_data['dob'] : null;
    $dom = (!empty($person_data['dom']) && $person_data['dom'] !== '0000-00-00') ? $person_data['dom'] : null;
    $dod = (!empty($person_data['dod']) && $person_data['dod'] !== '0000-00-00') ? $person_data['dod'] : null;
    $photo = $person_data['photo'] ?? null;
    // spouseId viene de la data del formulario (si se está añadiendo un cónyuge a alguien pre-existente, o null)
    $spouse_id = isset($person_data['spouseId']) && $person_data['spouseId'] !== null ? (int)$person_data['spouseId'] : null;

    // Usar 'i' para spouse_id si es un entero, 's' si es nulo (no tiene sentido, siempre es int o null)
    // MySQLi bind_param requiere que el tipo 'i' siempre sea un entero. Para NULL, debemos pasar NULL.
    // Esto es un problema conocido con mysqli si se quiere bindear un NULL a un 'i'.
    // Una solución es cambiar la columna a VARCHAR y manejar la conversión, o usar PDO.
    // Para simplificar, asumiremos que si viene como null se bindea como int(0) si no se especifica bien,
    // o que la columna permite nulls y se maneja desde el script con un valor de 0 si es int.
    // La forma más robusta con mysqli para nulls en enteros es así:
    if ($spouse_id === null) {
        $stmt->bind_param("sssssssss", $name, $relationship, $rut, $gender, $dob, $dom, $dod, $photo, $spouse_id);
    } else {
        $stmt->bind_param("ssssssssi", $name, $relationship, $rut, $gender, $dob, $dom, $dod, $photo, $spouse_id);
    }

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

    // Verificar si el vínculo ya existe usando PDO para sentencias preparadas robustas
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM parent_child WHERE parent_id = ? AND child_id = ?");
    $check_stmt->execute([$parent_id, $child_id]);
    $count = $check_stmt->fetchColumn();

    if ($count > 0) {
        // error_log("Vínculo padre-hijo entre $parent_id y $child_id ya existe.");
        return true; // El vínculo ya existe, considerarlo un éxito
    }

    // Insertar el vínculo usando mysqli
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

// ... (resto del código)

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

    // Mapear los datos del frontend a variables PHP, convirtiendo vacíos a null
    $name = !empty($data['name']) ? $data['name'] : null;
    $relationship = !empty($data['relationship']) ? $data['relationship'] : null;
    $rut = !empty($data['rut']) ? $data['rut'] : null;
    $gender = !empty($data['gender']) ? $data['gender'] : null;
    $dob = (!empty($data['dob']) && $data['dob'] !== '0000-00-00') ? $data['dob'] : null;
    $dom = (!empty($data['dom']) && $data['dom'] !== '0000-00-00') ? $data['dom'] : null;
    $dod = (!empty($data['dod']) && $data['dod'] !== '0000-00-00') ? $data['dod'] : null;
    $photo = !empty($data['photo']) ? $data['photo'] : null;
    $spouse_id = isset($data['spouseId']) && $data['spouseId'] !== null ? (int)$data['spouseId'] : null;


    $set_clauses = [];
    $bind_params_values = [];
    $bind_params_types = '';

    // Utiliza variables intermedias para asegurar que se pasen por referencia
    // PHP 5.3+ permite pasar literales y resultados de funciones, pero bind_param es especial.
    // Es más seguro usar una variable para cada valor.
    // **NOTA**: Ya estas variables fueron declaradas al inicio de la función con los valores finales.
    //          Las referencias a ellas son suficientes.

    // No necesitas redeclarar $bind_name, etc., si ya están definidas arriba con los valores.
    // Solo necesitamos asegurarnos de que se añade la referencia al array.

    // Si ya tienes:
    // $name = !empty($data['name']) ? $data['name'] : null;
    // ...
    // $spouse_id = isset($data['spouseId']) && $data['spouseId'] !== null ? (int)$data['spouseId'] : null;
    // $id = (int)$data['id']; // esta ya es una variable


    if ($name !== null) {
        $set_clauses[] = "name = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$name;
    } else {
        $set_clauses[] = "name = NULL";
    }
    if ($relationship !== null) {
        $set_clauses[] = "relationship = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$relationship;
    } else {
        $set_clauses[] = "relationship = NULL";
    }
    if ($rut !== null) {
        $set_clauses[] = "rut = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$rut;
    } else {
        $set_clauses[] = "rut = NULL";
    }
    if ($gender !== null) {
        $set_clauses[] = "gender = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$gender;
    } else {
        $set_clauses[] = "gender = NULL";
    }
    if ($dob !== null) {
        $set_clauses[] = "dob = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$dob;
    } else {
        $set_clauses[] = "dob = NULL";
    }
    if ($dom !== null) {
        $set_clauses[] = "dom = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$dom;
    } else {
        $set_clauses[] = "dom = NULL";
    }
    if ($dod !== null) {
        $set_clauses[] = "dod = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$dod;
    } else {
        $set_clauses[] = "dod = NULL";
    }
    if ($photo !== null) {
        $set_clauses[] = "photo = ?";
        $bind_params_types .= 's';
        $bind_params_values[] = &$photo;
    } else {
        $set_clauses[] = "photo = NULL";
    }

    // Manejo especial para spouse_id que es INT
    if ($spouse_id !== null) {
        $set_clauses[] = "spouse_id = ?";
        $bind_params_types .= 'i';
        $bind_params_values[] = &$spouse_id; // ¡Asegúrate de que este & esté aquí!
    } else {
        $set_clauses[] = "spouse_id = NULL";
    }

    $sql = "UPDATE people SET " . implode(', ', $set_clauses) . " WHERE id = ?";
    $bind_params_types .= 'i'; // Tipo para el ID
    $bind_params_values[] = &$id; // ¡Asegúrate de que este & esté aquí!

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al preparar la sentencia UPDATE: ' . $conn->error]);
        return;
    }

    // Preparar el primer argumento para bind_param (la cadena de tipos)
    $args = [$bind_params_types];
    // Añadir el resto de los valores por referencia
    // Este bucle ya debería estar bien si los valores se añaden con & al $bind_params_values.
    // Sin embargo, si los valores se añadieron como COPIAS a $bind_params_values,
    // este bucle también fallaría. La forma más segura es re-iterar sobre las variables originales.

    // Opción 1: (Ideal) Asegúrate que $bind_params_values ya contenga las referencias desde el inicio
    // (Esta es la que hemos estado intentando conseguir)
    // El código de arriba con `&$variable` debería lograr esto.

    // Opción 2: Si por alguna razón la primera opción no funciona, puedes hacer esto (menos eficiente):
    // Descomenta la sección `bind_params_values[] = &$bind_name;` etc. y usa esto:
    /*
    $temp_bind_values = [];
    if ($name !== null) $temp_bind_values[] = &$name;
    if ($relationship !== null) $temp_bind_values[] = &$relationship;
    // ... repetir para todos los campos hasta photo
    if ($photo !== null) $temp_bind_values[] = &$photo;

    // Y luego para spouse_id y id:
    if ($spouse_id !== null) $temp_bind_values[] = &$spouse_id;
    $temp_bind_values[] = &$id; // Para el ID final

    // Ahora, $bind_params_values debe ser $temp_bind_values
    $bind_params_values = $temp_bind_values; // Reemplaza
    */

    // Reconfirmamos la implementación correcta de `call_user_func_array` con referencias
    // Esta parte del código ya estaba bien si los elementos de $bind_params_values eran referencias.
    // El warning indica que en algún punto, se insertaron valores, no referencias.
    // Asegurémonos de que el array $args se construye correctamente con referencias.

    $args = [];
    $args[] = $bind_params_types; // El primer elemento es la cadena de tipos
    foreach ($bind_params_values as $k => $v) {
        $args[] = &$bind_params_values[$k]; // ¡Esto es clave para pasar por referencia!
    }

    call_user_func_array([$stmt, 'bind_param'], $args);


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

    // Si la persona seleccionada tiene un cónyuge y ese cónyuge también está en los datos enviados, vincularlo
    // Esto asegura que ambos padres de una pareja se vinculen al nuevo hijo.
    if (isset($data['selectedPersonSpouseId']) && $data['selectedPersonSpouseId'] !== null) {
        $spouse_id = (int)$data['selectedPersonSpouseId'];
        if (!add_parent_child_link($conn, $pdo, $spouse_id, $new_child_id)) {
            // No es un error crítico si no se puede vincular al cónyuge, solo loguear
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

/**
 * Agrega una nueva persona como cónyuge de la persona seleccionada.
 */
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

/**
 * Elimina un vínculo padre-hijo de la tabla `parent_child`.
 */
function remove_parent_child_link($conn, $data)
{
    if (!isset($data['parent_id']) || !isset($data['child_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'IDs de padre e hijo son requeridos para eliminar el vínculo.']);
        return;
    }
    $parent_id = (int)$data['parent_id'];
    $child_id = (int)$data['child_id'];

    $stmt = $conn->prepare("DELETE FROM parent_child WHERE parent_id = ? AND child_id = ?");
    $stmt->bind_param("ii", $parent_id, $child_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Vínculo padre-hijo eliminado correctamente.']);
        } else {
            // Esto puede ocurrir si el vínculo ya no existe (ej. doble clic o recarga)
            echo json_encode(['success' => false, 'message' => 'Vínculo no encontrado o ya eliminado.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el vínculo padre-hijo.', 'error' => $stmt->error]);
    }
    $stmt->close();
}

/**
 * Elimina el vínculo de cónyuge de una persona (y su cónyuge recíproco si existe).
 */
function remove_spouse_link($conn, $data, $pdo)
{
    if (!isset($data['person_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de persona es requerido para eliminar el vínculo de cónyuge.']);
        return;
    }
    $person_id = (int)$data['person_id'];

    // 1. Obtener el cónyuge actual para eliminar el vínculo recíproco
    $current_spouse_id = null;
    $stmt_get_spouse = $pdo->prepare("SELECT spouse_id FROM people WHERE id = ?");
    $stmt_get_spouse->execute([$person_id]);
    $spouse_id_from_db = $stmt_get_spouse->fetchColumn();

    if ($spouse_id_from_db !== false && $spouse_id_from_db !== null) {
        $current_spouse_id = (int)$spouse_id_from_db;
    }
    unset($stmt_get_spouse); // Liberar statement

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
            // Esto no debería hacer que falle toda la operación, solo loguear.
            error_log("Error: No se pudo eliminar el vínculo de cónyuge recíproco para el cónyuge $current_spouse_id.");
        }
        $stmt2->close();
    }

    echo json_encode(['success' => true, 'message' => 'Vínculo de cónyuge eliminado correctamente.']);
}

/**
 * Vincula dos personas existentes como cónyuges (bidireccional).
 */
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

    // Verificar si ya existe un vínculo que convertiría a uno en ancestro del otro
    // Esto es para prevenir bucles en el árbol (ej. A es padre de B, y B es padre de A).
    // Esto sería una comprobación compleja que podría requerir una función recursiva de SQL.
    // Por ahora, solo evitamos A->A.

    if (add_parent_child_link($conn, $pdo, $parent_id, $child_id)) {
        echo json_encode(['success' => true, 'message' => 'Vínculo padre-hijo creado correctamente.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al crear el vínculo padre-hijo.']);
    }
}


// Cierra la conexión a la base de datos al final del script
$conn->close();

<?php

/**
 * editar.php
 *
 * Página para editar la información de una persona específica.
 * Recibe el ID de la persona a través del parámetro GET 'id'.
 */

// Incluir el archivo de configuración de la base de datos
require_once 'db.php';

// Directorio donde se guardarán las imágenes subidas
// Asegúrate de que este directorio exista y tenga permisos de escritura para el servidor web
$upload_dir = 'uploads/photos/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true); // Crea el directorio si no existe con permisos
}

// Directorio donde se guardarán los documentos adjuntos
$upload_document_dir = 'uploads/documents/';
if (!is_dir($upload_document_dir)) {
    mkdir($upload_document_dir, 0755, true); // Crea el directorio si no existe con permisos
}

/**
 * Determina si un archivo dado por su ruta es una imagen basándose en su extensión.
 * @param string $filePath La ruta del archivo.
 * @return bool True si es un archivo de imagen, False en caso contrario.
 */
function is_image_file($filePath)
{
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    return in_array($extension, $imageExtensions);
}

/**
 * Devuelve la clase de Font Awesome adecuada para el icono de un archivo, basándose en su extensión.
 * @param string $filePath La ruta del archivo.
 * @return string La clase CSS de Font Awesome (ej. 'fas fa-file-pdf').
 */
function getFileIconClass($filePath)
{
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return 'fas fa-file-pdf text-red-600';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word text-blue-600';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel text-green-600';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fas fa-file-image text-purple-600';
        case 'txt':
            return 'fas fa-file-alt text-gray-600';
        default:
            return 'fas fa-file text-gray-500';
    }
}

// Verificar si se recibió un ID de persona
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirigir o mostrar un mensaje de error si no hay ID válido
    header('Location: index.html'); // O a una página de error
    exit('ID de persona no válido.');
}

$person_id = (int)$_GET['id'];

$person_data = null;
$person_documents = []; // Para almacenar los documentos de la persona
// --- Función para obtener los datos de la persona y sus documentos ---
function getPersonDataAndDocuments($conn, $person_id, $upload_document_dir)
{
    $data = [];
    $docs = [];

    $stmt_person = $conn->prepare("SELECT * FROM people WHERE id = ?");
    if ($stmt_person) {
        $stmt_person->bind_param("i", $person_id);
        $stmt_person->execute();
        $result_person = $stmt_person->get_result();
        $data = $result_person->fetch_assoc();
        $stmt_person->close();
    }

    if (!$data) {
        return ['person_data' => null, 'person_documents' => []]; // Persona no encontrada
    }

    $stmt_docs = $conn->prepare("SELECT id, document_type, file_path, file_name, upload_date FROM person_documents WHERE person_id = ? ORDER BY upload_date DESC");
    if ($stmt_docs) {
        $stmt_docs->bind_param("i", $person_id);
        $stmt_docs->execute();
        $result_docs = $stmt_docs->get_result();
        while ($row = $result_docs->fetch_assoc()) {
            $docs[] = $row;
        }
        $stmt_docs->close();
    }
    return ['person_data' => $data, 'person_documents' => $docs];
}

// --- Manejar acciones de documentos vía AJAX/Fetch (POST) ---
// Estas acciones se envían a la misma página editar.php pero con una acción especial
if (isset($_POST['_document_action'])) {
    header('Content-Type: application/json'); // La respuesta siempre será JSON para AJAX

    switch ($_POST['_document_action']) {
        case 'add_document':
            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No se subió ningún archivo o hubo un error en la subida.']);
                exit;
            }
            if (!isset($_POST['person_id']) || !isset($_POST['document_type'])) {
                echo json_encode(['success' => false, 'message' => 'Faltan datos (person_id o document_type) para subir el documento.']);
                exit;
            }

            $doc_person_id = (int)$_POST['person_id'];
            $doc_type = htmlspecialchars($_POST['document_type']);

            $file_tmp_name = $_FILES['document_file']['tmp_name'];
            $original_file_name = $_FILES['document_file']['name'];
            $file_extension = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
            $unique_file_name = uniqid('doc_') . '.' . $file_extension;
            $file_path_full = $upload_document_dir . $unique_file_name;

            // Validación de tipos de archivo y tamaño (5MB máximo)
            $allowed_file_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xlsx', 'xls', 'txt'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file_extension, $allowed_file_types)) {
                echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo PDF, JPG, PNG, GIF, DOC/X, XLS/X, TXT.']);
                exit;
            }
            if ($_FILES['document_file']['size'] > $max_file_size) {
                echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB.']);
                exit;
            }

            if (move_uploaded_file($file_tmp_name, $file_path_full)) {
                $stmt = $conn->prepare("INSERT INTO person_documents (person_id, document_type, file_path, file_name) VALUES (?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isss", $doc_person_id, $doc_type, $file_path_full, $original_file_name);
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Documento subido correctamente.', 'doc_id' => $conn->insert_id, 'file_path' => $file_path_full, 'file_name' => $original_file_name, 'document_type' => $doc_type, 'upload_date' => date('Y-m-d H:i:s')]);
                    } else {
                        unlink($file_path_full); // Limpiar archivo si falla DB
                        echo json_encode(['success' => false, 'message' => 'Error al registrar el documento en la base de datos.', 'error' => $stmt->error]);
                    }
                    $stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al preparar la sentencia de subida de documento.', 'error' => $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al mover el archivo subido al directorio de destino.']);
            }
            exit; // Salir después de procesar la acción de documento

        case 'delete_document':
            $doc_id_to_delete = (int)$_POST['document_id'];

            $stmt_select = $conn->prepare("SELECT file_path FROM person_documents WHERE id = ?");
            if ($stmt_select) {
                $stmt_select->bind_param("i", $doc_id_to_delete);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $document_info = $result->fetch_assoc();
                $stmt_select->close();

                if ($document_info) {
                    $file_to_delete = $document_info['file_path'];

                    $stmt_delete = $conn->prepare("DELETE FROM person_documents WHERE id = ?");
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("i", $doc_id_to_delete);
                        if ($stmt_delete->execute()) {
                            if (file_exists($file_to_delete)) {
                                unlink($file_to_delete);
                            }
                            echo json_encode(['success' => true, 'message' => 'Documento eliminado correctamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al eliminar el documento de la base de datos.', 'error' => $stmt_delete->error]);
                        }
                        $stmt_delete->close();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al preparar la sentencia para eliminar documento.', 'error' => $conn->error]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Documento no encontrado en la base de datos.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al preparar la sentencia para seleccionar documento para eliminar.', 'error' => $conn->error]);
            }
            exit; // Salir después de procesar la acción de documento
    }
}

// Manejar el envío del formulario (cuando se guarda la edición)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['_document_action'])) {
    // Recolectar datos del formulario
    $updated_name = !empty($_POST['name']) ? $_POST['name'] : null;
    $updated_rut = !empty($_POST['rut']) ? $_POST['rut'] : null;
    $updated_gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $updated_dob = (!empty($_POST['dob']) && $_POST['dob'] !== '0000-00-00') ? $_POST['dob'] : null;
    $updated_dom = (!empty($_POST['dom']) && $_POST['dom'] !== '0000-00-00') ? $_POST['dom'] : null;
    $updated_dod = (!empty($_POST['dod']) && $_POST['dod'] !== '0000-00-00') ? $_POST['dod'] : null;



    // --- Lógica para la imagen ---
    $photo_to_save = $current_photo_value; // Por defecto, mantener la imagen actual

    // 1. Verificar si se subió un archivo
    if (isset($_FILES['photo_file']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['photo_file']['tmp_name'];
        $file_name = $_FILES['photo_file']['name'];
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_file_name = uniqid('photo_') . '.' . $file_extension; // Nombre único
        $upload_path = $upload_dir . $new_file_name;

        // Mover el archivo subido
        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            $photo_to_save = $upload_path; // Guardar la ruta relativa
            // Opcional: Eliminar la imagen anterior si era un archivo subido
            if ($current_photo_value && strpos($current_photo_value, $upload_dir) === 0) {
                if (file_exists($current_photo_value)) {
                    unlink($current_photo_value);
                }
            }
        } else {
            echo "<script>alert('Error al subir la imagen del archivo.');</script>";
            // No salir, se intentará guardar el resto de los datos
        }
    }
    // 2. Si no se subió un archivo, verificar si se proporcionó una URL
    else if (!empty($_POST['photo_url'])) {
        $photo_to_save = filter_var($_POST['photo_url'], FILTER_SANITIZE_URL); // Sanitizar URL
        // Opcional: Eliminar la imagen anterior si era un archivo subido
        if ($current_photo_value && strpos($current_photo_value, $upload_dir) === 0) {
            if (file_exists($current_photo_value)) {
                unlink($current_photo_value);
            }
        }
    }
    // 3. Si no se subió archivo ni se dio URL y se marcó "eliminar foto"
    else if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        $photo_to_save = null; // Establecer foto a NULL en la DB
        // Eliminar la imagen anterior si era un archivo subido
        if ($current_photo_value && strpos($current_photo_value, $upload_dir) === 0) {
            if (file_exists($current_photo_value)) {
                unlink($current_photo_value);
            }
        }
    }


    $stmt_update = $conn->prepare(
        "UPDATE people SET name=?, rut=?, gender=?, dob=?, dom=?, dod=?, photo=? WHERE id=?"
    );

    if ($stmt_update) {
        // Asignar variables directamente para bind_param
        $bind_name = $updated_name;
        $bind_rut = $updated_rut;
        $bind_gender = $updated_gender;
        $bind_dob = $updated_dob;
        $bind_dom = $updated_dom;
        $bind_dod = $updated_dod;
        $bind_photo = $photo_to_save; // Usamos el valor decidido por la lógica de imagen
        $bind_person_id = $person_id; // ID para la cláusula WHERE

        // Construir los tipos de bind_param dinámicamente y las referencias


 
        // Si sigues teniendo warnings con 'i' para NULL, esa línea de types debe cambiar a 's'.
        $types = "ssssssi"; // 8 strings (hasta photo), 1 

        // Aquí pasamos las referencias para cada parámetro
        $params = [
            &$bind_name,
            &$bind_rut,
            &$bind_gender,
            &$bind_dob,
            &$bind_dom,
            &$bind_dod,
            &$bind_photo,
            &$bind_person_id // El último es el ID para WHERE
        ];

        // Añadir el tipo 'i' para el ID en WHERE al final de la cadena de tipos
        $types .= 'i';

        // El bind_param necesita el primer argumento como cadena de tipos, y luego las referencias.
        array_unshift($params, $types); // Añade la cadena de tipos al principio del array

        // La línea que causa el warning "Argument #2 must be passed by reference, value given"
        // es probable que sea debido a cómo se gestionan las referencias en $params.
        // Asegúrate de que $params contenga *referencias a variables* y no *copias de valores*.
        // El bucle `foreach ($bind_params_values as $k => $v) { $args[] = &$bind_params_values[$k]; }`
        // es la forma correcta de asegurar referencias si $bind_params_values se llenó con valores.
        // Pero en este código, $params se llena directamente con referencias, lo cual es más directo.


        // o manejarlo de otra forma. Si es INT NULL, lo que estamos haciendo debería funcionar.

        call_user_func_array([$stmt_update, 'bind_param'], $params);

        if ($stmt_update->execute()) {
            echo "<script>alert('Persona actualizada correctamente.'); window.location.href='index.html';</script>";
        } else {
            $error_message = "Error al actualizar la persona: " . $stmt_update->error;
            echo "<script>alert('$error_message');</script>";
        }
        $stmt_update->close();
    } else {
        echo "<script>alert('Error al preparar la sentencia de actualización.');</script>";
    }
}



// --- Si es una petición GET (carga inicial de la página) ---
// Obtener los datos de la persona y sus documentos para rellenar el formulario
$fetch_results = getPersonDataAndDocuments($conn, $person_id, $upload_document_dir);
$person_data = $fetch_results['person_data'];
$person_documents = $fetch_results['person_documents'];

if (!$person_data) { // Si después de intentar obtenerla, sigue sin estar.
    header('Location: index.html');
    exit('Persona no encontrada después de la recarga.');
}

// Preparar los datos para rellenar el formulario (se hace de nuevo para asegurar que estén actualizados si se recargó la página)
$name = htmlspecialchars($person_data['name'] ?? '');
$rut = htmlspecialchars($person_data['rut'] ?? '');
$gender = htmlspecialchars($person_data['gender'] ?? 'Masculino');
$dob = htmlspecialchars($person_data['dob'] ?? '');
$dom = htmlspecialchars($person_data['dom'] ?? '');
$dod = htmlspecialchars($person_data['dod'] ?? '');
$current_photo_value = htmlspecialchars($person_data['photo'] ?? '');

if ($dob === '0000-00-00') $dob = '';
if ($dom === '0000-00-00') $dom = '';
if ($dod === '0000-00-00') $dod = '';


$conn->close();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Persona</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }

        /* Contenedor principal de la página de edición para el diseño de 2 columnas */
        .page-container {
            display: flex;
            flex-direction: column;
            /* Por defecto vertical en móvil */
            gap: 2rem;
            /* Espacio entre columnas */
            padding: 2rem;
            max-width: 1200px;
            /* Ancho máximo para la página de edición */
            margin: auto;
            /* Centrar el contenedor */
            box-sizing: border-box;
            /* Incluir padding en el ancho/alto */
        }

        @media (min-width: 1024px) {

            /* A partir de pantallas grandes (lg) */
            .page-container {
                flex-direction: row;
                /* Horizontal en pantallas grandes */
                align-items: flex-start;
                /* Alinea los ítems en la parte superior */
            }

            .page-container>div {
                flex: 1;
                /* Permite que cada div ocupe el mismo ancho */
            }
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
            /* Espacio entre grupos de input */
        }

        .input-group label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.25rem;
            /* Pequeño espacio entre label y input */
            font-size: 0.875rem;
            /* Texto de label un poco más pequeño */
        }

        .input-group input[type="radio"] {
            margin-right: 0.5rem;
        }

        /* Estilos para la imagen de previsualización */
        #photo-preview-container {
            margin-top: 1rem;
            text-align: center;
        }

        #photo-preview {
            max-width: 150px;
            max-height: 150px;
            object-fit: cover;
            border: 2px solid #ccc;
            border-radius: 8px;
            margin: 0 auto;
            display: block;
            /* Para centrar la imagen */
        }


        /* Estilos para la nueva sección de documentos */
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            /* Aumentado padding */
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            /* Borde más suave */
            border-left: 4px solid #3b82f6;
            /* Barra lateral azul */
            border-radius: 8px;
            /* Bordes más redondeados */
            margin-bottom: 12px;
            /* Más espacio entre ítems */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            /* Sombra sutil */
            transition: all 0.3s ease;
        }

        .document-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        .document-item a {
            flex-grow: 1;
            margin-right: 15px;
            /* Más espacio */
            color: #1d4ed8;
            /* Color azul para enlaces */
            font-weight: 500;
        }

        .document-item a:hover {
            text-decoration: underline;
        }

        .document-item span {
            display: block;
        }

        .document-item .doc-info {
            font-size: 0.8em;
            color: #6b7280;
            margin-top: 4px;
        }

        .document-type-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 9999px;
            /* Fully rounded */
            font-weight: 600;
            margin-bottom: 4px;
        }

        /* Estilos para el área de carga de archivos (Drag & Drop) */
        .upload-area {
            border: 2px dashed #9ca3af;
            /* Color de borde */
            background-color: #f9fafb;
            /* Fondo claro */
            padding: 2.5rem;
            /* Más padding */
            border-radius: 12px;
            /* Bordes más redondeados */
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .upload-area:hover {
            border-color: #6366f1;
            /* Morado al hover */
            background-color: #eff6ff;
            /* Azul muy claro al hover */
        }

        .upload-area.dragover {
            border-color: #4f46e5;
            /* Morado más oscuro cuando se arrastra sobre */
            background-color: #e0e7ff;
            /* Azul más oscuro cuando se arrastra sobre */
        }

        .upload-area .icon {
            font-size: 2.5rem;
            /* Icono grande */
            color: #a78bfa;
            /* Color morado */
            margin-bottom: 1rem;
        }

        .upload-area .text-main {
            font-weight: 600;
            color: #4b5563;
        }

        .upload-area .text-sub {
            font-size: 0.875rem;
            /* Texto pequeño */
            color: #6b7280;
        }

        /* Estilo para la previsualización del archivo subido */
        #file-preview {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #file-preview .file-icon {
            font-size: 1.5rem;
            color: #6b7280;
        }

        #file-preview .file-info p {
            margin: 0;
            line-height: 1.3;
        }

        #file-preview .file-info .file-name {
            font-weight: 500;
            color: #1f2937;
        }

        #file-preview .file-info .file-size {
            font-size: 0.85em;
            color: #6b7280;
        }

        /* Animación Fade In para nuevos documentos */
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 flex items-center justify-center min-h-screen">

    <div class="page-container">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full">
            <div class="bg-gradient-to-r from-green-500 to-blue-500 px-6 py-4 rounded-t-lg -mx-8 -mt-8 mb-6">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-circle mr-3"></i> Información Personal
                </h2>
                <p class="text-green-100 mt-1">Actualiza los datos básicos y la foto de perfil.</p>
            </div>


            <form method="POST" action="editar.php?id=<?php echo $person_id; ?>" enctype="multipart/form-data">
                <input type="hidden" name="person_id" value="<?php echo $person_id; ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="input-group">
                        <label for="name">Nombre Completo*</label>
                        <input style="width:205%" class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" id="name" name="name" value="<?= $name; ?>" required>
                    </div>
                    <div class="input-group"> </div>

                    <div class="input-group">
                        <label for="gender">Género</label>
                        <select id="gender" name="gender" class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline">
                            <option value="Masculino" <?php echo ($gender === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Femenino" <?php echo ($gender === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Otro" <?php echo ($gender === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label for="rut">RUT</label>
                        <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" id="rut" name="rut" value="<?= $rut; ?>">
                    </div>



                    <div class="input-group">
                        <label for="dob">Fecha de Nacimiento</label>
                        <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" id="dob" name="dob" value="<?php echo $dob; ?>">
                    </div>

                    <div class="input-group">
                        <label for="dom">Fecha de Matrimonio</label>
                        <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" id="dom" name="dom" value="<?php echo $dom; ?>">
                    </div>

                    <div class="input-group">
                        <label for="dod">Fecha de Fallecimiento</label>
                        <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" id="dod" name="dod" value="<?php echo $dod; ?>">
                    </div>

                    <div class="col-span-2 mt-4 border-t pt-4">
                        <h3 class="text-lg font-semibold mb-2">Foto de Perfil</h3>

                        <div id="photo-preview-container" class="mb-4">
                            <img id="photo-preview" src="<?php echo $current_photo_value ?: 'https://placehold.co/150x150/E2E8F0/333333?text=Sin+Foto'; ?>" alt="Previsualización de Foto">
                            <?php if ($current_photo_value): ?>
                                <p class="text-sm text-gray-500 mt-1">Foto actual</p>
                            <?php endif; ?>
                        </div>

                        <label class="block mb-2">
                            <input type="checkbox" name="remove_photo" id="remove_photo" value="1">
                            <span class="ml-2 text-red-600">Eliminar foto actual</span>
                        </label>

                        <div class="input-group">
                            <label for="photo_option">Seleccionar opción de foto:</label>
                            <div class="flex items-center gap-4 mb-2">
                                <label>
                                    <input type="radio" name="photo_option" value="file" id="option_file" <?php echo (empty($current_photo_value) || !filter_var($current_photo_value, FILTER_VALIDATE_URL)) ? 'checked' : ''; ?>>
                                    Subir Archivo
                                </label>
                                <label>
                                    <input type="radio" name="photo_option" value="url" id="option_url" <?php echo (filter_var($current_photo_value, FILTER_VALIDATE_URL)) ? 'checked' : ''; ?>>
                                    Usar URL
                                </label>
                            </div>
                        </div>

                        <div id="photo_file_group" class="input-group <?php echo (empty($current_photo_value) || !filter_var($current_photo_value, FILTER_VALIDATE_URL)) ? '' : 'hidden'; ?>">
                            <label for="photo_file">Seleccionar archivo de imagen:</label>
                            <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="file" name="photo_file" id="photo_file" accept="image/*">
                            <p class="text-xs text-gray-500">Max 2MB. Formatos: JPG, PNG, GIF.</p>
                        </div>

                        <div id="photo_url_group" class="input-group <?php echo (filter_var($current_photo_value, FILTER_VALIDATE_URL)) ? '' : 'hidden'; ?>">
                            <label for="photo_url">URL de la imagen:</label>
                            <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" name="photo_url" id="photo_url" placeholder="Ej: https://ejemplo.com/mi-foto.jpg" value="<?php echo (filter_var($current_photo_value, FILTER_VALIDATE_URL) ? $current_photo_value : ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-4">
                    <a href="index.html" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancelar</a>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">Guardar Cambios</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 w-full">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-file-alt mr-3"></i>
                    Documentos Adjuntos
                </h2>
                <p class="text-blue-100 mt-1">Gestiona y organiza los documentos de la persona</p>
            </div>

            <div class="p-6">
                <div id="document-list-container" class="space-y-4">
                    <?php if (!empty($person_documents)): ?>
                        <?php foreach ($person_documents as $doc): ?>
                            <div class="document-item bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-300" data-doc-id="<?php echo $doc['id']; ?>" data-doc-name="<?php echo htmlspecialchars($doc['file_name']); ?>">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center space-x-4 flex-grow">
                                        <div class="flex-shrink-0">
                                            <div class="w-12 h-12 rounded-lg flex items-center justify-center">
                                                <i class="<?php echo getFileIconClass($doc['file_path']); ?> text-blue-600 text-xl"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="flex items-center space-x-2 mb-1">
                                                <span class="document-type-badge text-white text-xs px-2 py-1 rounded-full font-medium">
                                                    <?php echo htmlspecialchars($doc['document_type']); ?>
                                                </span>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-gray-900 hover:text-blue-600 font-medium transition-colors duration-200">
                                                <?php echo htmlspecialchars($doc['file_name']); ?>
                                            </a>
                                            <div class="flex items-center text-sm text-gray-500 mt-1">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                <span>Subido el <?php echo (new DateTime($doc['upload_date']))->format('d/m/Y'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 flex-shrink-0">
                                        <?php if (is_image_file($doc['file_path'])): // Solo mostrar "Ver" si es una imagen 
                                        ?>
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="p-2 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-200" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="delete-doc-btn p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div id="no-documents-message" class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-folder-open text-gray-400 text-2xl"></i>
                            </div>
                            <p class="text-gray-500 text-lg">No hay documentos adjuntos</p>
                            <p class="text-gray-400 text-sm mt-1">Sube tu primer documento usando el formulario de abajo</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-8 border-t pt-6">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-plus-circle mr-2 text-blue-600"></i>
                            Subir Nuevo Documento
                        </h4>
                        <p class="text-gray-600 text-sm">Formatos permitidos: PDF, JPG, PNG, DOCX (máximo 5MB)</p>
                    </div>

                    <form id="document-upload-form" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="person_id" value="<?php echo $person_id; ?>">
                        <input type="hidden" name="_document_action" value="add_document">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tags mr-1"></i>
                                Tipo de Documento
                            </label>
                            <div class="relative">
                                <select name="document_type" id="doc-type-select" class="w-full bg-white border border-gray-300 text-gray-900 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none" required>
                                    <option value="">Selecciona el tipo de documento</option>
                                    <option value="Certificado de Nacimiento">📄 Certificado de Nacimiento</option>
                                    <option value="Certificado de Matrimonio">💒 Certificado de Matrimonio</option>
                                    <option value="Certificado de Defunción">⚰️ Certificado de Defunción</option>
                                    <option value="DNI/Cédula">🆔 Carnet/Cédula</option>
                                    <option value="Otro">📋 Otro</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-upload mr-1"></i>
                                Archivo
                            </label>
                            <div class="upload-area rounded-lg p-6 text-center cursor-pointer" onclick="document.getElementById('file-input').click()">
                                <div class="space-y-2">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto">
                                        <i class="fas fa-cloud-upload-alt text-blue-600 text-xl"></i>
                                    </div>
                                    <div>
                                        <p class="text-gray-600">
                                            <span class="font-medium text-blue-600">Haz clic para subir</span> o arrastra y suelta
                                        </p>
                                        <p class="text-sm text-gray-500">PDF, JPG, PNG, DOCX hasta 5MB</p>
                                    </div>
                                </div>
                                <input type="file" id="file-input" name="document_file" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.docx,.doc,.xlsx,.xls,.txt" required>
                            </div>
                            <div id="file-preview" class="mt-3 hidden">
                                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg w-full">
                                    <i class="fas fa-file text-gray-400 file-icon"></i>
                                    <div class="flex-1 file-info">
                                        <p class="text-sm font-medium text-gray-900" id="file-name"></p>
                                        <p class="text-xs text-gray-500" id="file-size"></p>
                                    </div>
                                    <button type="button" class="text-red-600 hover:text-red-800" onclick="removeFile()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="index.html" class="bg-gray-200 cancel-upload-btn px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                                Cancelar
                            </a>
                            <button type="submit" id="upload-submit-btn" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all duration-200 transform hover:scale-105 flex items-center space-x-2">
                                <i class="fas fa-upload"></i>
                                <span>Subir Documento</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const optionFile = document.getElementById('option_file');
            const optionUrl = document.getElementById('option_url');
            const photoFileGroup = document.getElementById('photo_file_group');
            const photoUrlGroup = document.getElementById('photo_url_group');
            const photoFileInput = document.getElementById('photo_file');
            const photoUrlInput = document.getElementById('photo_url');
            const removePhotoCheckbox = document.getElementById('remove_photo');
            const photoPreview = document.getElementById('photo-preview');

            const documentUploadForm = document.getElementById('document-upload-form');
            const documentListContainer = document.getElementById('document-list-container');
            const noDocumentsMessage = document.getElementById('no-documents-message'); // Get the "No hay documentos" message

            const fileInput = document.getElementById('file-input');
            const filePreview = document.getElementById('file-preview');
            const fileNameDisplay = document.getElementById('file-name'); // Renamed to avoid conflict
            const fileSizeDisplay = document.getElementById('file-size'); // Renamed
            const uploadArea = document.querySelector('.upload-area');
            const uploadSubmitBtn = document.getElementById('upload-submit-btn'); // New button reference
            const cancelUploadBtn = document.querySelector('.cancel-upload-btn'); // New button reference
            const docTypeSelect = document.getElementById('doc-type-select');

            // Función para actualizar la vista previa de la imagen
            function updatePhotoPreview(src) {
                if (src && src.trim() !== '') {
                    photoPreview.src = src;
                } else {
                    photoPreview.src = 'https://placehold.co/150x150/E2E8F0/333333?text=Sin+Foto';
                }
            }

            function togglePhotoInputs() {
                if (optionFile.checked) {
                    photoFileGroup.classList.remove('hidden');
                    photoUrlGroup.classList.add('hidden');
                    photoUrlInput.value = ''; // Limpiar el campo URL si se elige subir archivo
                } else {
                    photoUrlGroup.classList.remove('hidden');
                    photoFileGroup.classList.add('hidden');
                    // photoFileInput.value = ''; // No se puede limpiar directamente por seguridad
                }
                // Desmarcar "Eliminar foto actual" si se elige una nueva foto/URL
                if (photoFileInput.files.length > 0 || photoUrlInput.value !== '') {
                    removePhotoCheckbox.checked = false;
                }
            }

            // Event listener para el cambio de opción (Archivo vs. URL)
            optionFile.addEventListener('change', togglePhotoInputs);
            optionUrl.addEventListener('change', togglePhotoInputs);

            // Event listener para la subida de archivo (previsualización)
            photoFileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        updatePhotoPreview(e.target.result); // Actualiza con la URL del archivo local
                    };
                    reader.readAsDataURL(this.files[0]); // Lee el archivo como URL de datos
                    removePhotoCheckbox.checked = false; // Desmarcar eliminar foto
                    photoUrlInput.value = ''; // Limpiar URL
                } else {
                    // Si no hay archivo, volver a la foto actual o placeholder
                    updatePhotoPreview('<?php echo $current_photo_value ?: 'https://placehold.co/150x150/E2E8F0/333333?text=Sin+Foto'; ?>');
                }
            });

            // Event listener para el campo de URL (previsualización)
            photoUrlInput.addEventListener('input', function() {
                updatePhotoPreview(this.value); // Actualiza con la URL ingresada
                if (this.value !== '') {
                    removePhotoCheckbox.checked = false; // Desmarcar eliminar foto
                }
            });

            // Event listener para el checkbox de eliminar foto
            removePhotoCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    photoFileInput.value = ''; // Limpiar input de archivo
                    photoUrlInput.value = ''; // Limpiar input de URL
                    optionFile.checked = true; // Por defecto, volver a la opción de archivo
                    togglePhotoInputs(); // Refrescar la visibilidad
                    updatePhotoPreview(''); // Mostrar placeholder (sin foto)
                } else {
                    // Si se desmarca, restaurar la vista previa original (o la que se estuviera editando)
                    updatePhotoPreview('<?php echo $current_photo_value ?: 'https://placehold.co/150x150/E2E8F0/333333?text=Sin+Foto'; ?>');
                }
            });

            // Llamar al inicio para establecer el estado inicial y la vista previa
            togglePhotoInputs();
            updatePhotoPreview('<?php echo $current_photo_value ?: 'https://placehold.co/150x150/E2E8F0/333333?text=Sin+Foto'; ?>');

            // --- Lógica de subida y eliminación de documentos ---

            // Helper to get file icon class
            const getFileIconClassJs = (fileNameDisplay) => {
                const ext = fileNameDisplay.split('.').pop().toLowerCase();
                switch (ext) {
                    case 'pdf':
                        return 'fas fa-file-pdf text-red-600';
                    case 'doc':
                    case 'docx':
                        return 'fas fa-file-word text-blue-600';
                    case 'xls':
                    case 'xlsx':
                        return 'fas fa-file-excel text-green-600';
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        return 'fas fa-file-image text-purple-600';
                    case 'txt':
                        return 'fas fa-file-alt text-gray-600';
                    default:
                        return 'fas fa-file text-gray-500';
                }
            };

            // Helper to format file size
            const formatFileSizeJs = (bytes) => {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            };

            // Función para actualizar la lista de documentos visible en la página
            function addDocumentToDisplay(doc) {
                if (noDocumentsMessage) { // If "No hay documentos" message exists, remove it
                    noDocumentsMessage.remove();
                }
                const docDiv = document.createElement('div');
                docDiv.className = 'document-item bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md fade-in'; // Add fade-in
                docDiv.setAttribute('data-doc-id', doc.id);
                docDiv.innerHTML = `
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center space-x-4 flex-grow">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="${getFileIconClassJs(doc.file_name)} text-xl"></i>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="document-type-badge text-white text-xs px-2 py-1 rounded-full font-medium">
                                        ${doc.document_type}
                                    </span>
                                </div>
                                <a href="${doc.file_path}" target="_blank" class="text-gray-900 hover:text-blue-600 font-medium transition-colors duration-200">
                                    ${doc.file_name}
                                </a>
                                <div class="flex items-center text-sm text-gray-500 mt-1">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <span>Subido el ${new Date(doc.upload_date).toLocaleDateString('es-ES')}</span>
                                    ${doc.file_size ? `<span class="mx-2">•</span><i class="fas fa-file-alt mr-1"></i><span>${formatFileSizeJs(doc.file_size)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            ${(doc.file_name.match(/\.(jpeg|jpg|gif|png)$/i)) ? `
                            <a href="${doc.file_path}" target="_blank" class="p-2 text-gray-600 hover:bg-gray-50 rounded-lg transition-colors duration-200" title="Ver">
                                <i class="fas fa-eye"></i>
                            </a>` : ''}
                            <button class="delete-doc-btn p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200" title="Eliminar">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
                documentListContainer.prepend(docDiv); // Add to top of list
            }
            // File input change event for document upload form
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    fileNameDisplay.textContent = file.name;
                    fileSizeDisplay.textContent = formatFileSizeJs(file.size);
                    filePreview.classList.remove('hidden');
                } else {
                    filePreview.classList.add('hidden');
                }
            });

            // Remove file from preview (for documents)
            window.removeFile = function() { // Made global for onclick
                fileInput.value = '';
                filePreview.classList.add('hidden');
            };

            // Drag and drop functionality for document upload area
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files; // Assign files to the input
                    fileNameDisplay.textContent = files[0].name;
                    fileSizeDisplay.textContent = formatFileSizeJs(files[0].size);
                    filePreview.classList.remove('hidden');
                }
            });

            // Handle Document Upload Form Submission
            documentUploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                uploadSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Subiendo...</span>';
                uploadSubmitBtn.disabled = true;
                cancelUploadBtn.disabled = true;

                const formData = new FormData(documentUploadForm);
                formData.append('_document_action', 'add_document'); // Ensure this action is set

                try {
                    const response = await fetch('editar.php?id=<?php echo $person_id; ?>', {
                        method: 'POST',
                        body: formData,
                    });
                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        addDocumentToDisplay(result);
                        documentUploadForm.reset(); // Esta línea restablece todo el formulario
                        fileInput.value = '';
                        fileNameDisplay.textContent = '';
                        fileSizeDisplay.textContent = '';
                        filePreview.classList.add('hidden');

                    } else {
                        alert('Error al subir documento: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error al subir documento:', error);
                    alert('Error de red o del servidor al subir documento.');
                } finally {
                    uploadSubmitBtn.innerHTML = '<i class="fas fa-upload"></i> <span>Subir Documento</span>';
                    uploadSubmitBtn.disabled = false;
                    cancelUploadBtn.disabled = false;
                }
            });

            // Handle Cancel Upload Button
            cancelUploadBtn.addEventListener('click', () => {
                documentUploadForm.reset();
                removeFile();
            });

            // Handle Document Delete (delegated event listener)
            documentListContainer.addEventListener('click', async (e) => {
                if (e.target.classList.contains('delete-doc-btn') || e.target.closest('.delete-doc-btn')) {
                    const deleteButton = e.target.closest('.delete-doc-btn');
                    const docItem = deleteButton.closest('.document-item');
                    if (!docItem) return;

                    const docIdToDelete = docItem.getAttribute('data-doc-id');
                    const docName = docItem.getAttribute('data-doc-name') || `documento ID ${docIdToDelete}`; // Use data-doc-name

                    if (!confirm(`¿Estás seguro de que quieres eliminar "${docName}"?`)) {
                        return;
                    }

                    // Add fade out animation
                    docItem.style.transition = 'all 0.3s ease';
                    docItem.style.transform = 'translateX(100%)';
                    docItem.style.opacity = '0';

                    const formData = new FormData();
                    formData.append('_document_action', 'delete_document');
                    formData.append('document_id', docIdToDelete);

                    try {
                        const response = await fetch('editar.php?id=<?php echo $person_id; ?>', {
                            method: 'POST',
                            body: formData,
                        });
                        const result = await response.json();

                        if (result.success) {
                            alert(result.message);
                            setTimeout(() => { // Wait for fade out animation
                                docItem.remove();
                                // Show "No documents" message if list is empty
                                if (documentListContainer.children.length === 0 || (documentListContainer.children.length === 1 && documentListContainer.firstElementChild.id === 'no-documents-message')) {
                                    if (noDocumentsMessage) noDocumentsMessage.classList.remove('hidden');
                                    else documentListContainer.innerHTML = '<div id="no-documents-message" class="text-center py-12"><div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-folder-open text-gray-400 text-2xl"></i></div><p class="text-gray-500 text-lg">No hay documentos adjuntos.</p><p class="text-gray-400 text-sm mt-1">Sube tu primer documento usando el formulario de abajo</p></div>';
                                }
                            }, 300); // Same duration as CSS transition
                        } else {
                            alert('Error al eliminar documento: ' + result.message);
                            // If error, revert animation
                            docItem.style.transform = 'translateX(0)';
                            docItem.style.opacity = '1';
                        }
                    } catch (error) {
                        console.error('Error al eliminar documento:', error);
                        alert('Error de red o del servidor al eliminar documento.');
                        // If error, revert animation
                        docItem.style.transform = 'translateX(0)';
                        docItem.style.opacity = '1';
                    }
                }
            });

            // Refresh the document list on page load (already done by PHP echo)
            // If you want JS to manage it fully after initial load, you'd fetch docs here.

        });
    </script>

</body>

</html>
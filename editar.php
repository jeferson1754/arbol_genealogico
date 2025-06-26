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

// Verificar si se recibió un ID de persona
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirigir o mostrar un mensaje de error si no hay ID válido
    header('Location: index.html'); // O a una página de error
    exit('ID de persona no válido.');
}

$person_id = (int)$_GET['id'];

$person_data = null;

// Obtener los datos de la persona desde la base de datos
$stmt = $conn->prepare("SELECT * FROM people WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $person_data = $result->fetch_assoc();
    $stmt->close();
}

// Si la persona no se encontró, redirigir
if (!$person_data) {
    header('Location: index.html'); // O mostrar un error
    exit('Persona no encontrada.');
}

// Preparar los datos para rellenar el formulario
$name = htmlspecialchars($person_data['name'] ?? '');
$relationship = htmlspecialchars($person_data['relationship'] ?? '');
$rut = htmlspecialchars($person_data['rut'] ?? '');
$gender = htmlspecialchars($person_data['gender'] ?? 'Masculino');
$dob = htmlspecialchars($person_data['dob'] ?? '');
$dom = htmlspecialchars($person_data['dom'] ?? '');
$dod = htmlspecialchars($person_data['dod'] ?? '');
$current_photo_value = htmlspecialchars($person_data['photo'] ?? ''); // Este será el valor actual de 'photo' en la DB

// Convertir '0000-00-00' a cadena vacía para los inputs de tipo date
if ($dob === '0000-00-00') $dob = '';
if ($dom === '0000-00-00') $dom = '';
if ($dod === '0000-00-00') $dod = '';


// Manejar el envío del formulario (cuando se guarda la edición)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recolectar datos del formulario
    $updated_name = !empty($_POST['name']) ? $_POST['name'] : null;
    $updated_relationship = !empty($_POST['relationship']) ? $_POST['relationship'] : null;
    $updated_rut = !empty($_POST['rut']) ? $_POST['rut'] : null;
    $updated_gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $updated_dob = (!empty($_POST['dob']) && $_POST['dob'] !== '0000-00-00') ? $_POST['dob'] : null;
    $updated_dom = (!empty($_POST['dom']) && $_POST['dom'] !== '0000-00-00') ? $_POST['dom'] : null;
    $updated_dod = (!empty($_POST['dod']) && $_POST['dod'] !== '0000-00-00') ? $_POST['dod'] : null;
    
    // El spouse_id no se gestiona directamente en este formulario de edición simple.
    // Se mantiene el spouse_id actual de la persona.
    $current_spouse_id = $person_data['spouse_id'] ?? null;

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
        "UPDATE people SET name=?, relationship=?, rut=?, gender=?, dob=?, dom=?, dod=?, photo=?, spouse_id=? WHERE id=?"
    );

    if ($stmt_update) {
        // Asignar variables directamente para bind_param
        $bind_name = $updated_name;
        $bind_relationship = $updated_relationship;
        $bind_rut = $updated_rut;
        $bind_gender = $updated_gender;
        $bind_dob = $updated_dob;
        $bind_dom = $updated_dom;
        $bind_dod = $updated_dod;
        $bind_photo = $photo_to_save; // Usamos el valor decidido por la lógica de imagen
        $bind_spouse_id = $current_spouse_id;
        $bind_person_id = $person_id; // ID para la cláusula WHERE

        // Construir los tipos de bind_param dinámicamente y las referencias
        // Asegúrate de que si spouse_id es null, se bindea como 's' para mysqli
        // Si tienes la columna spouse_id como INT NULL en la DB, puedes forzar el tipo 'i'.
        // Pero para ser super seguro con NULLs en mysqli, podrías usar:
        // $types = "ssssssss" . ($bind_spouse_id === null ? "s" : "i") . "i";
        // Y pasar $bind_spouse_id directamente.
        
        // Mantendremos "ssssssssii" asumiendo que spouse_id es INT NULL y que mysqli lo maneja.
        // Si sigues teniendo warnings con 'i' para NULL, esa línea de types debe cambiar a 's'.
        $types = "ssssssssi"; // 8 strings (hasta photo), 1 integer (spouse_id)

        // Aquí pasamos las referencias para cada parámetro
        $params = [
            &$bind_name,
            &$bind_relationship,
            &$bind_rut,
            &$bind_gender,
            &$bind_dob,
            &$bind_dom,
            &$bind_dod,
            &$bind_photo,
            &$bind_spouse_id, // ¡Asegúrate de que esta variable sea la misma que $spouse_id del POST!
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
        
        // Si el warning persiste, revisa que $person_data['spouse_id'] se inicialice correctamente.
        // También, si la columna 'spouse_id' es INT NOT NULL, deberías darle un valor por defecto (ej. 0)
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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .input-group label {
            font-weight: 600;
            color: #4a5568;
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
            display: block; /* Para centrar la imagen */
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-lg">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 text-center">Editar Persona</h1>
        
        <form method="POST" action="editar.php?id=<?php echo $person_id; ?>" enctype="multipart/form-data">
            <input type="hidden" name="person_id" value="<?php echo $person_id; ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" name="name" placeholder="Nombre Completo*" value="<?php echo $name; ?>" required>
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" name="relationship" placeholder="Relación (Ej: Tío, Prima)" value="<?php echo $relationship; ?>">
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="text" name="rut" placeholder="RUT" value="<?php echo $rut; ?>">
                <select name="gender" class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline">
                    <option value="Masculino" <?php echo ($gender === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                    <option value="Femenino" <?php echo ($gender === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                    <option value="Otro" <?php echo ($gender === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                </select>
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" name="dob" placeholder="Fecha de Nacimiento" value="<?php echo $dob; ?>">
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" name="dom" placeholder="Fecha de Matrimonio" value="<?php echo $dom; ?>">
                <input class="w-full bg-gray-100 text-gray-900 p-3 rounded-lg focus:outline-none focus:shadow-outline" type="date" name="dod" placeholder="Fecha de Fallecimiento" value="<?php echo $dod; ?>">
                
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
        });
    </script>

</body>
</html>
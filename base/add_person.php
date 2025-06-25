<?php
include 'db.php';

$id = $_GET['id'] ?? null;
$parent_id = $_GET['parent_id'] ?? null;

$errors = [];
$success = false;

// Initialize variables for form fields
$full_name = '';
$rut = '';
$birth_date = '';
$marriage_date = '';
$death_date = '';
$gender = 'masculino';
$photo = '';
$existingPhoto = '';

// If editing, load existing data
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM persons WHERE id = ?");
    $stmt->execute([$id]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($person) {
        $full_name = $person['full_name'];
        $rut = $person['rut'];
        $birth_date = $person['birth_date'];
        $marriage_date = $person['marriage_date'];
        $death_date = $person['death_date'];
        $gender = $person['gender'];
        $existingPhoto = $person['photo'];
        $parent_id = $person['parent_id'];
    } else {
        $errors[] = "Persona no encontrada.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario POST
    $full_name = trim($_POST['full_name'] ?? '');
    $rut = trim($_POST['rut'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $marriage_date = $_POST['marriage_date'] ?? null;
    $death_date = $_POST['death_date'] ?? null;
    $gender = $_POST['gender'] ?? 'masculino';
    $parent_id = $_POST['parent_id'] ?? null;

    // Validaciones básicas
    if (!$full_name) {
        $errors[] = "El nombre completo es obligatorio.";
    }
    if (!$rut) {
        $errors[] = "El RUT es obligatorio.";
    }
    if (!$birth_date) {
        $errors[] = "La fecha de nacimiento es obligatoria.";
    }

    // Manejo de subida de foto
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($ext, $allowed)) {
                $errors[] = "Formato de foto no permitido. Solo jpg, jpeg, png, gif.";
            } else {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $_FILES['photo']['name']);
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                    $photo = $filename;
                } else {
                    $errors[] = "Error al subir la foto.";
                }
            }
        } else {
            $errors[] = "Error en la subida de la foto.";
        }
    }

    if (empty($errors)) {
        if ($id) {
            // Actualizar persona
            if ($photo) {
                // borrar foto anterior si existe
                if ($existingPhoto && file_exists(__DIR__.'/uploads/'.$existingPhoto)) {
                    unlink(__DIR__.'/uploads/'.$existingPhoto);
                }
                $stmtUpd = $conn->prepare("UPDATE persons SET full_name=?, rut=?, birth_date=?, marriage_date=?, death_date=?, gender=?, photo=?, parent_id=? WHERE id=?");
                $result = $stmtUpd->execute([$full_name, $rut, $birth_date, $marriage_date, $death_date, $gender, $photo, $parent_id, $id]);
            } else {
                $stmtUpd = $conn->prepare("UPDATE persons SET full_name=?, rut=?, birth_date=?, marriage_date=?, death_date=?, gender=?, parent_id=? WHERE id=?");
                $result = $stmtUpd->execute([$full_name, $rut, $birth_date, $marriage_date, $death_date, $gender, $parent_id, $id]);
            }
        } else {
            // Insertar nueva persona
            $stmtIns = $conn->prepare("INSERT INTO persons (full_name, rut, birth_date, marriage_date, death_date, gender, photo, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmtIns->execute([$full_name, $rut, $birth_date, $marriage_date, $death_date, $gender, $photo, $parent_id]);
        }
        if ($result) {
            $success = true;
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Error al guardar en la base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? 'Editar Persona' : 'Añadir Persona'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5e2e;
            --secondary-color: #81a584;
            --accent-color: #a8b8a3;
            --light-bg: #f8f9f6;
            --wood-color: #8b4513;
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #e8f5e8 100%);
            font-family: 'Georgia', serif;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--accent-color);
            transition: border-color 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 94, 46, 0.25);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-user-plus"></i> <?php echo $id ? 'Editar Persona' : 'Añadir Persona'; ?></h1>
        </div>
    </div>

    <div class="container my-5">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="parent_id" value="<?php echo htmlspecialchars($parent_id); ?>">
            <div class="mb-3">
                <label for="full_name" class="form-label">
                    <i class="fas fa-user"></i> Nombre Completo <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
            </div>
            <div class="mb-3">
                <label for="rut" class="form-label">
                    <i class="fas fa-id-card"></i> RUT <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control" id="rut" name="rut" value="<?php echo htmlspecialchars($rut); ?>" required>
            </div>
            <div class="mb-3">
                <label for="birth_date" class="form-label">
                    <i class="fas fa-birthday-cake"></i> Fecha de Nacimiento <span class="text-danger">*</span>
                </label>
                <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>" required>
            </div>
            <div class="mb-3">
                <label for="marriage_date" class="form-label">
                    <i class="fas fa-heart"></i> Fecha de Matrimonio (opcional)
                </label>
                <input type="date" class="form-control" id="marriage_date" name="marriage_date" value="<?php echo htmlspecialchars($marriage_date); ?>">
            </div>
            <div class="mb-3">
                <label for="death_date" class="form-label">
                    <i class="fas fa-cross"></i> Fecha de Muerte (opcional)
                </label>
                <input type="date" class="form-control" id="death_date" name="death_date" value="<?php echo htmlspecialchars($death_date); ?>">
            </div>
            <div class="mb-3">
                <label for="gender" class="form-label">
                    <i class="fas fa-venus-mars"></i> Género <span class="text-danger">*</span>
                </label>
                <select class="form-select" id="gender" name="gender" required>
                    <option value="masculino" <?php if($gender === 'masculino') echo 'selected'; ?>>Masculino</option>
                    <option value="femenino" <?php if($gender === 'femenino') echo 'selected'; ?>>Femenino</option>
                    <option value="otro" <?php if($gender === 'otro') echo 'selected'; ?>>Otro</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="photo" class="form-label">
                    <i class="fas fa-image"></i> Fotografía
                </label>
                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                <?php if($existingPhoto): ?>
                    <small class="form-text text-muted">Foto actual: <img src="uploads/<?php echo htmlspecialchars($existingPhoto); ?>" alt="Foto actual" width="120" class="mb-2 rounded"></small>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="files" class="form-label">
                    <i class="fas fa-paperclip"></i> Archivos Adjuntos
                </label>
                <input type="file" class="form-control" id="files" name="files" multiple>
                <small class="form-text text-muted">Puedes adjuntar documentos, imágenes, PDFs, etc.</small>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo $id ? 'Actualizar' : 'Crear'; ?>
            </button>
            <a href="index.php" class="btn btn-secondary ml-2">Cancelar</a>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>

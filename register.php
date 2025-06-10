<?php
session_start();
require 'config/db_connection.php'; // Include the database connection file

$errors = ''; // Error message container
$uploadDir = 'uploads/'; // Directory to save uploaded profile pics

// Fetch roles and specialties for dropdowns
try {
    $rolesStmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

    $specStmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $specialties = $specStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur serveur: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = intval($_POST['role_id'] ?? 0);
    $specialty_id = isset($_POST['specialty_id']) && $_POST['specialty_id'] !== '' ? intval($_POST['specialty_id']) : null;
    $profile_pic_name = null;

    // Basic validations
    if (!$name || !$email || !$password || !$role_id) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } else {
        // Validate role
        $roleExists = false;
        foreach ($roles as $role) {
            if ($role['id'] == $role_id) {
                $roleExists = true;
                break;
            }
        }
        if (!$roleExists) {
            $error = "Le rôle sélectionné est invalide.";
        }

        // Validate specialty if provided
        if ($specialty_id !== null) {
            $specialtyExists = false;
            foreach ($specialties as $spec) {
                if ($spec['id'] == $specialty_id) {
                    $specialtyExists = true;
                    break;
                }
            }
            if (!$specialtyExists) {
                $error = "La spécialité sélectionnée est invalide.";
            }
        }
    }

    // Handle file upload if no errors yet
    if (!$error && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['profile_pic']['type'];
        if (!in_array($fileType, $allowedTypes)) {
            $error = "Le format de l'image doit être JPG, PNG ou GIF.";
        } elseif ($_FILES['profile_pic']['size'] > 2 * 1024 * 1024) { // max 2MB
            $error = "La taille de l'image ne doit pas dépasser 2MB.";
        } else {
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $profile_pic_name = uniqid('profile_', true) . '.' . $ext;
            $targetPath = $uploadDir . $profile_pic_name;

            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
                $error = "Erreur lors du téléchargement de l'image.";
            }
        }
    }

    if (!$error) {
        // Check if email already used
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cette adresse email est déjà utilisée.";
        } else {
            // Insert new user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, role_id, specialty_id, profile_pic) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->execute([$name, $email, $hashedPassword, $role_id, $specialty_id, $profile_pic_name]);

            // Redirect or success message
            header("Location: login.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 bg-white p-4 rounded shadow">
            <h2 class="mb-4 text-center">Créer un compte</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php" enctype="multipart/form-data" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Nom complet *</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mot de passe *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="mb-3">
                    <label for="role_id" class="form-label">Rôle *</label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">-- Choisir un rôle --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="specialty_id" class="form-label">Spécialité (optionnel)</label>
                    <select class="form-select" id="specialty_id" name="specialty_id">
                        <option value="">-- Choisir une spécialité --</option>
                        <?php foreach ($specialties as $spec): ?>
                            <option value="<?= $spec['id'] ?>" <?= (isset($_POST['specialty_id']) && $_POST['specialty_id'] == $spec['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($spec['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="profile_pic" class="form-label">Photo de profil (optionnelle)</label>
                    <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept="image/*" />
                </div>

                <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
            </form>

            <p class="mt-3 text-center">
                Déjà un compte ? <a href="login.php">Connectez-vous ici</a>.
            </p>
        </div>
    </div>
</div>
</body>
</html>

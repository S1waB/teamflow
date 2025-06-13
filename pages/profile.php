<?php
session_start();
require '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.profile_pic, u.specialty_id, 
           s.name AS specialty_name, r.name AS role_name
    FROM users u
    LEFT JOIN specialties s ON u.specialty_id = s.id
    JOIN roles r ON u.role_id = r.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch specialties for dropdown
$specialties = $pdo->query("SELECT id, name FROM specialties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $specialty_id = $_POST['specialty_id'] !== '' ? (int)$_POST['specialty_id'] : null;
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($name)) {
        $error = "Le nom est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'email n'est pas valide.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            $pdo->beginTransaction();

            // Handle profile picture upload
            $profile_pic = $user['profile_pic'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/profile_pics/';

                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Generate unique filename
                $fileExt = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $fileExt;
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                    // Delete old profile pic if exists
                    if ($profile_pic && file_exists($uploadDir . $profile_pic)) {
                        unlink($uploadDir . $profile_pic);
                    }
                    $profile_pic = $filename;
                }
            }

            // Update user data
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, specialty_id = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $specialty_id, $profile_pic, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, specialty_id = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $email, $specialty_id, $profile_pic, $user_id]);
            }

            $pdo->commit();
            $success = "Profil mis à jour avec succès!";

            // Refresh user data
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.profile_pic, u.specialty_id, 
                       s.name AS specialty_name, r.name AS role_name
                FROM users u
                LEFT JOIN specialties s ON u.specialty_id = s.id
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update session data
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['profile_pic'] = $user['profile_pic'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
        }
    }
}

$pageTitle = "Mon Profil";
include '../layouts/header.php';
?>


<div class="row">
    <div class="col-lg-2">
        <?php include '../layouts/sidebar.php'; ?>
    </div>

    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-person-circle me-2"></i>Mon Profil</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body text-center">
                        <div class="position-relative d-inline-block">
                            <?php if ($user['profile_pic']): ?>
                                <img src="../uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>"
                                    alt="Profile" class="rounded-circle mb-3" width="150" height="150">
                            <?php else: ?>
                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mb-3"
                                    style="width: 150px; height: 150px;">
                                    <i class="bi bi-person text-white" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="position-absolute bottom-0 end-0 bg-primary rounded-circle p-2">
                                <i class="bi bi-camera text-white"></i>
                            </div>
                        </div>

                        <h3 class="mb-0"><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="text-muted mb-1"><?= htmlspecialchars($user['email']) ?></p>

                        <div class="d-flex justify-content-center mt-3">
                            <span class="badge bg-primary me-2">
                                <i class="bi bi-person-badge me-1"></i>
                                <?= htmlspecialchars($user['role_name']) ?>
                            </span>

                            <?php if ($user['specialty_name']): ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-star me-1"></i>
                                    <?= htmlspecialchars($user['specialty_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Edit Profile Form -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Modifier le profil</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Nom complet <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label for="specialty_id" class="form-label">Spécialité</label>
                                    <select class="form-select" id="specialty_id" name="specialty_id">
                                        <option value="">-- Aucune spécialité --</option>
                                        <?php foreach ($specialties as $specialty): ?>
                                            <option value="<?= $specialty['id'] ?>"
                                                <?= $user['specialty_id'] == $specialty['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($specialty['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="profile_pic" class="form-label">Photo de profil</label>
                                    <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept="image/*">
                                    <div class="form-text">Formats acceptés: JPG, PNG, GIF. Max 2MB.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <div class="form-text">Laissez vide pour ne pas changer</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i> Enregistrer les modifications
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>




            </div>
        </div>
    </div>
</div>


<style>
    .card {
        border-radius: 10px;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        padding: 15px 20px;
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        padding: 10px 15px;
    }

    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
    }

    .badge {
        border-radius: 20px;
        padding: 8px 15px;
        font-weight: 500;
    }

    .profile-stats {
        display: flex;
        justify-content: space-around;
        text-align: center;
        margin-top: 20px;
    }

    .profile-stats div {
        flex: 1;
    }

    .profile-stats h4 {
        margin-bottom: 5px;
    }

    .profile-stats p {
        color: #6c757d;
        margin-bottom: 0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password visibility toggle
        const togglePassword = document.querySelector('#togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const password = document.querySelector('#password');
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('bi-eye');
                this.classList.toggle('bi-eye-slash');
            });
        }

        // Confirmation for dangerous actions
        const deleteBtn = document.querySelector('#deleteAccountBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.')) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>
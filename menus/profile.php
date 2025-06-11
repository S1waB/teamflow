<?php
session_start();
require_once '../config/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Récupération des informations de l'utilisateur
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name, s.name as specialty_name 
                       FROM users u 
                       LEFT JOIN roles r ON u.role_id = r.id 
                       LEFT JOIN specialties s ON u.specialty_id = s.id 
                       WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profilePicPath = $user['profile_pic'] ? '/uploads/profile_pics/' . $user['profile_pic'] : '/uploads/profile_pics/default-avatar.png';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --text-color: #1f2937;
            --bg-color: #f8fafc;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .profile-pic-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-pic-wrapper img {
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-pic-wrapper .btn {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../layouts/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Mon Profil</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="profile-pic-wrapper mb-4">
                            <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Photo de profil" 
                                 class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;"
                                 onerror="this.src='/uploads/profile_pics/default-avatar.png'">
                            <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#changePhotoModal">
                                <i class="bi bi-camera"></i> Changer la photo
                            </button>
                        </div>
                        <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                        <p class="text-muted mb-3">
                            <span class="badge bg-primary"><?= htmlspecialchars($user['role_name']) ?></span>
                            <?php if ($user['specialty_name']): ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($user['specialty_name']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informations du profil</h5>
                    </div>
                    <div class="card-body">
                        <div id="profileMessage" class="alert d-none" role="alert"></div>
                        <form id="profileForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="name" 
                                           value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Changer le mot de passe</h5>
                    </div>
                    <div class="card-body">
                        <div id="passwordMessage" class="alert d-none" role="alert"></div>
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="currentPassword" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="newPassword" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirmPassword" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour changer la photo de profil -->
    <div class="modal fade" id="changePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Changer la photo de profil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="photoMessage" class="alert d-none" role="alert"></div>
                    <form id="photoForm" class="text-center">
                        <div class="mb-4">
                            <div class="profile-pic-preview mb-3">
                                <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Aperçu" 
                                     class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;"
                                     id="photoPreview">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sélectionner une image</label>
                                <input type="file" class="form-control" id="profilePhoto" accept="image/*" required
                                       onchange="previewImage(this)">
                                <small class="form-text text-muted">Formats acceptés : JPG, PNG, GIF. Taille maximale : 2MB</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="savePhoto">
                        <i class="bi bi-cloud-upload"></i> Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonctions utilitaires
        function showMessage(elementId, message, type = 'success') {
            const msg = document.getElementById(elementId);
            msg.className = `alert alert-${type}`;
            msg.textContent = message;
        }

        // Gestion du formulaire de profil
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value
            };

            try {
                const response = await fetch('api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors de la mise à jour du profil');
                }
                
                showMessage('profileMessage', 'Profil mis à jour avec succès');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } catch (error) {
                showMessage('profileMessage', error.message, 'danger');
            }
        });

        // Gestion du formulaire de mot de passe
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                showMessage('passwordMessage', 'Les mots de passe ne correspondent pas', 'danger');
                return;
            }

            const formData = {
                currentPassword: document.getElementById('currentPassword').value,
                newPassword: newPassword
            };

            try {
                const response = await fetch('api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ...formData, action: 'change_password' })
                });
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du changement de mot de passe');
                }
                
                showMessage('passwordMessage', 'Mot de passe changé avec succès');
                document.getElementById('passwordForm').reset();
            } catch (error) {
                showMessage('passwordMessage', error.message, 'danger');
            }
        });

        // Fonction pour prévisualiser l'image
        function previewImage(input) {
            const preview = document.getElementById('photoPreview');
            const message = document.getElementById('photoMessage');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Vérifier la taille du fichier (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    message.className = 'alert alert-danger';
                    message.textContent = 'Le fichier est trop volumineux (max 2MB)';
                    input.value = '';
                    return;
                }

                // Vérifier le type de fichier
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    message.className = 'alert alert-danger';
                    message.textContent = 'Type de fichier non autorisé (JPG, PNG, GIF uniquement)';
                    input.value = '';
                    return;
                }

                // Afficher l'aperçu
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    message.className = 'alert d-none';
                };
                reader.readAsDataURL(file);
            }
        }

        // Gestion du changement de photo
        document.getElementById('savePhoto').addEventListener('click', async () => {
            const fileInput = document.getElementById('profilePhoto');
            const message = document.getElementById('photoMessage');
            
            if (!fileInput.files.length) {
                message.className = 'alert alert-danger';
                message.textContent = 'Veuillez sélectionner une image';
                return;
            }

            const formData = new FormData();
            formData.append('photo', fileInput.files[0]);

            try {
                const response = await fetch('api/profile.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du changement de photo');
                }
                
                message.className = 'alert alert-success';
                message.textContent = 'Photo de profil mise à jour avec succès';
                
                // Fermer le modal après 1.5 secondes
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('changePhotoModal'));
                    modal.hide();
                    window.location.reload();
                }, 1500);
            } catch (error) {
                message.className = 'alert alert-danger';
                message.textContent = error.message;
            }
        });

        // Réinitialiser le formulaire quand le modal est fermé
        document.getElementById('changePhotoModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('photoForm').reset();
            document.getElementById('photoPreview').src = '<?= htmlspecialchars($profilePicPath) ?>';
            document.getElementById('photoMessage').className = 'alert d-none';
        });
    </script>
</body>
</html> 
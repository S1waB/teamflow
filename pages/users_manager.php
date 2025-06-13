<?php
require '../config/db_connection.php';

// Fetch all users
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = isset($_GET['filter_role']) ? (int) $_GET['filter_role'] : 0;

$sql = "
    SELECT u.id, u.name, u.email, r.name AS role_name, s.name AS specialty_name, 
           u.role_id, u.profile_pic, u.specialty_id
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN specialties s ON u.specialty_id = s.id
    WHERE 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_role > 0) {
    $sql .= " AND u.role_id = ?";
    $params[] = $filter_role;
}

$sql .= " ORDER BY u.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch roles and specialties
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$specialties = $pdo->query("SELECT id, name FROM specialties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get error if redirected with error message
$error = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "Gestion des utilisateurs";
include '../layouts/header.php';

// Get Chef Projet role ID
$chefProjetRoleId = 0;
foreach ($roles as $role) {
    if (strtolower($role['name']) === 'chef_projet') {
        $chefProjetRoleId = $role['id'];
        break;
    }
}

// Fetch projects
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-2">
            <?php include '../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-10">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs</h3>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter un utilisateur
                    </button>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Filter/Search Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-5">
                            <label for="search" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control"
                                       placeholder="Nom ou Email" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="filter_role" class="form-label">Filtrer par rôle</label>
                            <select id="filter_role" name="filter_role" class="form-select">
                                <option value="0">-- Tous les rôles --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $filter_role == $role['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel-fill"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- User Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Liste des utilisateurs</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Photo</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Spécialité</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td>
                                        <?php if ($user['profile_pic']): ?>
                                            <img src="../uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>" 
                                                 alt="Profile" class="rounded-circle" width="40" height="40">
                                        <?php else: ?>
                                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role_name']) ?></span></td>
                                    <td><?= htmlspecialchars($user['specialty_name'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editUserModal"
                                                data-user='<?= json_encode($user) ?>'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="../controllers/users_controller.php?delete_user_id=<?= $user['id'] ?>"
                                           onclick="return confirm('Supprimer cet utilisateur ?');"
                                           class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/users_controller.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="bi bi-person-plus"></i> Ajouter un nouvel utilisateur
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nameAdd" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nameAdd" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="emailAdd" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="emailAdd" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="passwordAdd" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="passwordAdd" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label for="passwordConfirm" class="form-label">Confirmer mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="passwordConfirm" name="password_confirm" required>
                        </div>
                        <div class="col-md-6">
                            <label for="roleAdd" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="roleAdd" name="role_id" required>
                                <option value="" selected>-- Choisir un rôle --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="specialtyAdd" class="form-label">Spécialité (optionnelle)</label>
                            <select class="form-select" id="specialtyAdd" name="specialty_id">
                                <option value="" selected>-- Aucune --</option>
                                <?php foreach ($specialties as $spec): ?>
                                    <option value="<?= $spec['id'] ?>"><?= htmlspecialchars($spec['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Project Selection (only for Chef Projet) -->
                        <div class="col-md-12" id="addProjectField" style="display: none;">
                            <label for="addProjectSelect" class="form-label">Projet assigné <span class="text-danger">*</span></label>
                            <select class="form-select" id="addProjectSelect" name="project_id">
                                <option value="" selected>-- Sélectionner un projet --</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Profile Picture -->
                        <div class="col-md-12">
                            <label for="profilePicAdd" class="form-label">Photo de profil</label>
                            <input class="form-control" type="file" id="profilePicAdd" name="profile_pic" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_user" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/users_controller.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="bi bi-pencil-square"></i> Modifier l'utilisateur
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editName" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editRole" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_id" id="editRole" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editSpecialty" class="form-label">Spécialité</label>
                            <select class="form-select" name="specialty_id" id="editSpecialty">
                                <option value="">Aucune</option>
                                <?php foreach ($specialties as $spec): ?>
                                    <option value="<?= $spec['id'] ?>"><?= htmlspecialchars($spec['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Project Selection (only for Chef Projet) -->
                        <div class="col-md-12" id="editProjectField" style="display: none;">
                            <label for="editProjectSelect" class="form-label">Projet assigné <span class="text-danger">*</span></label>
                            <select class="form-select" id="editProjectSelect" name="project_id">
                                <option value="" selected>-- Sélectionner un projet --</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Profile Picture -->
                        <div class="col-md-12">
                            <label for="profilePicEdit" class="form-label">Photo de profil</label>
                            <input class="form-control" type="file" id="profilePicEdit" name="profile_pic" accept="image/*">
                            
                            <!-- Current Profile Picture -->
                            <div class="mt-2" id="currentProfilePicContainer">
                                <small class="text-muted">Photo actuelle :</small>
                                <div id="currentProfilePic" class="mt-1"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="editPassword" class="form-label">Nouveau mot de passe (laissez vide pour ne pas changer)</label>
                            <input type="password" class="form-control" name="password" id="editPassword">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chefProjetRoleId = <?= $chefProjetRoleId ?>;
        
        // Edit User Modal Functionality
        const editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const userData = JSON.parse(button.getAttribute('data-user'));
                
                document.getElementById('editUserId').value = userData.id;
                document.getElementById('editName').value = userData.name;
                document.getElementById('editEmail').value = userData.email;
                document.getElementById('editRole').value = userData.role_id;
                document.getElementById('editSpecialty').value = userData.specialty_id || '';
                document.getElementById('editPassword').value = '';
                
                // Show profile picture if exists
                const currentProfilePic = document.getElementById('currentProfilePic');
                if (userData.profile_pic) {
                    currentProfilePic.innerHTML = `
                        <img src="../uploads/profile_pics/${userData.profile_pic}" 
                             alt="Current Profile" class="img-thumbnail" width="100">
                    `;
                } else {
                    currentProfilePic.innerHTML = '<div class="text-muted">Aucune photo</div>';
                }
                
                // Show project field if Chef Projet role
                const editProjectField = document.getElementById('editProjectField');
                if (parseInt(userData.role_id) === chefProjetRoleId) {
                    editProjectField.style.display = 'block';
                    // Set current project if available
                    document.getElementById('editProjectSelect').value = userData.project_id || '';
                } else {
                    editProjectField.style.display = 'none';
                }
            });
        });
        
        // Add Modal Role Change Listener
        const addRoleSelect = document.getElementById('roleAdd');
        const addProjectField = document.getElementById('addProjectField');
        
        addRoleSelect.addEventListener('change', function() {
            if (parseInt(this.value) === chefProjetRoleId) {
                addProjectField.style.display = 'block';
            } else {
                addProjectField.style.display = 'none';
            }
        });
        
        // Edit Modal Role Change Listener
        const editRoleSelect = document.getElementById('editRole');
        const editProjectField = document.getElementById('editProjectField');
        
        editRoleSelect.addEventListener('change', function() {
            if (parseInt(this.value) === chefProjetRoleId) {
                editProjectField.style.display = 'block';
            } else {
                editProjectField.style.display = 'none';
            }
        });
        
        // Password Confirmation for Add Modal
        const addForm = document.querySelector('#addUserModal form');
        addForm.addEventListener('submit', (e) => {
            const password = document.getElementById('passwordAdd').value;
            const confirmPassword = document.getElementById('passwordConfirm').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas !');
                document.getElementById('passwordConfirm').focus();
            }
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>
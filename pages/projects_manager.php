<?php
// projects_manager.php
require '../config/db_connection.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user role and ID from session
$user_role = $_SESSION['role'];
$current_user_id = $_SESSION['user_id'];

// Fetch all projects
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_manager = isset($_GET['filter_manager']) ? (int) $_GET['filter_manager'] : 0;

$sql = "
    SELECT p.id, p.name, p.description, p.start_date, p.end_date, p.progress, 
           u.name AS manager_name, u.id AS manager_id
    FROM projects p
    JOIN users u ON p.manager_id = u.id
    WHERE 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_manager > 0) {
    $sql .= " AND p.manager_id = ?";
    $params[] = $filter_manager;
}

$sql .= " ORDER BY p.start_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch managers
$managers = $pdo->query("SELECT id, name FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'chef_projet')")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Gestion des projets";
include '../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-2">
            <?php include '../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-10">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-kanban me-2"></i>Gestion des projets</h3>
                    <?php if ($user_role === 'admin'): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter un projet
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter/Search Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-5">
                            <label for="search" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="Nom ou description" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="filter_manager" class="form-label">Filtrer par manager</label>
                            <select id="filter_manager" name="filter_manager" class="form-select">
                                <option value="0">-- Tous les managers --</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>" <?= $filter_manager == $manager['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($manager['name']) ?>
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

            <!-- Projects Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Liste des projets</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php if ($user_role === 'admin'): ?>
                                <th>ID</th>
                                <?php endif; ?>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Manager</th>
                                <th>Dates</th>
                                <th>Progrès</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project):
                                // Restreindre les projets selon le rôle
                                if ($user_role === 'membre') {
                                    // Vérifier si le membre est assigné au projet
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND member_id = ?");
                                    $stmt->execute([$project['id'], $current_user_id]);
                                    $is_member = $stmt->fetchColumn() > 0;
                                    
                                    if (!$is_member) {
                                        continue; // Passer au projet suivant si le membre n'est pas assigné
                                    }
                                } else if ($user_role === 'chef_projet' && $project['manager_id'] !== $current_user_id) {
                                    continue; // Passer au projet suivant si ce n'est pas le sien
                                }
                                $progress = (float)$project['progress'];
                                $progressClass = $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : ($progress >= 30 ? 'bg-warning' : 'bg-danger'));
                            ?>
                                <tr>
                                    <?php if ($user_role === 'admin'): ?>
                                    <td><?= $project['id'] ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?= htmlspecialchars($project['name']) ?></strong>
                                    </td>
                                    <td><?= strlen($project['description']) > 50 ? substr($project['description'], 0, 50) . '...' : $project['description'] ?></td>
                                    <td><?= htmlspecialchars($project['manager_name']) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($project['start_date'])) ?> -
                                        <?= date('d/m/Y', strtotime($project['end_date'])) ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?= $progressClass ?>"
                                                role="progressbar"
                                                style="width: <?= $progress ?>%;"
                                                aria-valuenow="<?= $progress ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?= $progress ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="project_view.php?id=<?= $project['id'] ?>"
                                            class="btn btn-sm btn-outline-primary me-2"
                                            title="Voir les détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($user_role === 'admin'): ?>
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-project-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editProjectModal"
                                            data-project='<?= json_encode($project) ?>'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="../controllers/projects_controller.php?delete_project_id=<?= $project['id'] ?>"
                                            onclick="return confirm('Supprimer ce projet ?');"
                                            class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                        <?php endif; ?>     
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

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/projects_controller.php" method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addProjectModalLabel">
                        <i class="bi bi-plus-circle"></i> Ajouter un nouveau projet
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="projectName" class="form-label">Nom du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="projectName" name="name" required>
                        </div>
                        <div class="col-md-12">
                            <label for="projectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="projectDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="startDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="startDate" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="endDate" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="endDate" name="end_date" required>
                        </div>
                        <div class="col-md-12">
                            <label for="managerSelect" class="form-label">Manager <span class="text-danger">*</span></label>
                            <select class="form-select" id="managerSelect" name="manager_id" required>
                                <option value="" selected>-- Choisir un manager --</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                       
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_project" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/projects_controller.php" method="POST">
                <input type="hidden" name="project_id" id="editProjectId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editProjectModalLabel">
                        <i class="bi bi-pencil-square"></i> Modifier le projet
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="editProjectName" class="form-label">Nom du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editProjectName" name="name" required>
                        </div>
                        <div class="col-md-12">
                            <label for="editProjectDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editProjectDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="editStartDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editEndDate" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editEndDate" name="end_date" required>
                        </div>
                        <div class="col-md-12">
                            <label for="editManagerSelect" class="form-label">Manager <span class="text-danger">*</span></label>
                            <select class="form-select" id="editManagerSelect" name="manager_id" required>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="editProgressInput" class="form-label">Progrès <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center">
                                <input type="range" class="form-range flex-grow-1 me-2" id="editProgressInput" name="progress" min="0" max="100" step="1" required>
                                <span id="editProgressValue" class="badge bg-primary">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_project" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Edit Project Modal Functionality
        const editButtons = document.querySelectorAll('.edit-project-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const projectData = JSON.parse(button.getAttribute('data-project'));
                
                // Remplir tous les champs du formulaire
                document.getElementById('editProjectId').value = projectData.id;
                document.getElementById('editProjectName').value = projectData.name;
                document.getElementById('editProjectDescription').value = projectData.description;
                document.getElementById('editStartDate').value = projectData.start_date;
                document.getElementById('editEndDate').value = projectData.end_date;
                document.getElementById('editManagerSelect').value = projectData.manager_id;
                document.getElementById('editProgressInput').value = projectData.progress;
                document.getElementById('editProgressValue').textContent = `${projectData.progress}%`;
            });
        });

        // Mettre à jour l'affichage du pourcentage lors du déplacement du curseur
        const editProgressInput = document.getElementById('editProgressInput');
        const editProgressValue = document.getElementById('editProgressValue');

        editProgressInput.addEventListener('input', () => {
            editProgressValue.textContent = `${editProgressInput.value}%`;
        });
    });
</script>
<?php include '../layouts/footer.php'; ?>
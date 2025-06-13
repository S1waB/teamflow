<?php
require '../config/db_connection.php';

// Récupérer l'utilisateur connecté et son rôle
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

// Fetch all tasks
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_project = isset($_GET['filter_project']) ? (int) $_GET['filter_project'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$sql = "
    SELECT t.id, t.title, t.description, t.status, t.progress, t.start_date, t.due_date,
           p.name AS project_name, u.name AS assigned_to_name, t.assigned_to
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE 1
";

// Initialize params array
$params = [];

// Si l'utilisateur est un membre, ne montrer que ses tâches assignées
if ($user_role === 'membre') {
    $sql .= " AND t.assigned_to = :user_id";
    $params[':user_id'] = $user_id;
}

if ($search !== '') {
    $sql .= " AND (t.title LIKE :search OR t.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter_project > 0) {
    $sql .= " AND t.project_id = :project_id";
    $params[':project_id'] = $filter_project;
}

if ($filter_status !== '') {
    $sql .= " AND t.status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " ORDER BY t.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects - Pour les membres, ne montrer que les projets auxquels ils sont assignés
if ($user_role === 'membre') {
    $sql_projects = "
        SELECT DISTINCT p.id, p.name 
        FROM projects p
        JOIN tasks t ON p.id = t.project_id
        WHERE t.assigned_to = :user_id
        ORDER BY p.name
    ";
    $stmt_projects = $pdo->prepare($sql_projects);
    $stmt_projects->execute([':user_id' => $user_id]);
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);
} else {
    $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Vérifier si la table project_members existe
$tableExists = $pdo->query("SHOW TABLES LIKE 'project_members'")->rowCount() > 0;

if (!$tableExists) {
    try {
        // Temporaire: Supprimer la table si elle existe pour s'assurer de la bonne structure
        $pdo->exec("DROP TABLE IF EXISTS project_members");

        // Créer la table project_members
        $pdo->exec("CREATE TABLE project_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            member_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_project_user (project_id, member_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Ajouter des membres de test
        $all_projects = $pdo->query("SELECT id FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        $all_users = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($all_projects) && !empty($all_users)) {
            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, member_id) VALUES (?, ?)");
            
            foreach ($all_projects as $project_id) {
                // Sélectionner 2-3 utilisateurs aléatoires pour chaque projet
                $random_users = array_rand($all_users, min(3, count($all_users)));
                if (!is_array($random_users)) {
                    $random_users = [$random_users];
                }
                
                foreach ($random_users as $user_index) {
                    $user_id = $all_users[$user_index];
                    $stmt->execute([$project_id, $user_id]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la création de la table project_members: " . $e->getMessage());
    }
}

// Récupérer les membres de chaque projet
$project_members = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id as project_id, u.id as user_id, u.name as user_name
        FROM projects p
        LEFT JOIN project_members pm ON p.id = pm.project_id
        LEFT JOIN users u ON pm.member_id = u.id
        WHERE u.id IS NOT NULL
        ORDER BY p.id, u.name
    ");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($project_members[$row['project_id']])) {
            $project_members[$row['project_id']] = [];
        }
        $project_members[$row['project_id']][] = [
            'id' => $row['user_id'],
            'name' => $row['user_name']
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des membres des projets: " . $e->getMessage());
    $project_members = [];
}

// Fetch users - Pour les membres, ne montrer que leur propre profil
if ($user_role === 'membre') {
    $sql_users = "SELECT id, name FROM users WHERE id = :user_id";
    $stmt_users = $pdo->prepare($sql_users);
    $stmt_users->execute([':user_id' => $user_id]);
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} else {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}

// Get error if redirected with error message
$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

$pageTitle = "Gestion des tâches";
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
                    <h3 class="card-title mb-0"><i class="bi bi-list-task me-2"></i>Gestion des tâches</h3>
                    <?php if ($user_role === 'admin' || $user_role === 'chef_projet'): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter une tâche
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Filter/Search Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control"
                                       placeholder="Titre ou description" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_project" class="form-label">Projet</label>
                            <select id="filter_project" name="filter_project" class="form-select">
                                <option value="0">-- Tous les projets --</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>" <?= $filter_project == $project['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($project['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="filter_status" class="form-label">Statut</label>
                            <select id="filter_status" name="filter_status" class="form-select">
                                <option value="">-- Tous les statuts --</option>
                                <option value="To-do" <?= $filter_status === 'To-do' ? 'selected' : '' ?>>À faire</option>
                                <option value="in progress" <?= $filter_status === 'in progress' ? 'selected' : '' ?>>En cours</option>
                                <option value="finished" <?= $filter_status === 'finished' ? 'selected' : '' ?>>Terminé</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel-fill"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tasks Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Liste des tâches</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Tâche</th>
                                <th>Projet</th>
                                <th>Assigné à</th>
                                <th>Dates</th>
                                <th>Statut</th>
                                <th>Progrès</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): 
                                $progress = (float)$task['progress'];
                                $progressClass = $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : ($progress >= 30 ? 'bg-warning' : 'bg-danger'));
                                
                                $statusClass = $task['status'] === 'finished' ? 'bg-success' : 
                                              ($task['status'] === 'in progress' ? 'bg-primary' : 'bg-secondary');
                                
                                $today = new DateTime();
                                $due_date = new DateTime($task['due_date']);
                                $isOverdue = $due_date < $today && $task['status'] !== 'finished';
                            ?>
                                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                    <td><?= $task['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($task['title']) ?></strong>
                                        <div class="text-muted small"><?= htmlspecialchars(substr($task['description'], 0, 50)) ?>...</div>
                                    </td>
                                    <td><?= htmlspecialchars($task['project_name']) ?></td>
                                    <td><?= $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : 'Non assigné' ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($task['start_date'])) ?> - 
                                        <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger ms-1">En retard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= $task['status'] === 'To-do' ? 'À faire' : 
                                               ($task['status'] === 'in progress' ? 'En cours' : 'Terminé') ?>
                                        </span>
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
                                        <?php if ($user_role === 'admin' || $user_role === 'chef_projet'): ?>
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-task-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTaskModal"
                                                data-task='<?= json_encode($task) ?>'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                            <a href="../controllers/tasks_controller.php?delete_task_id=<?= $task['id'] ?>"
                                               onclick="return confirm('Supprimer cette tâche ?');"
                                               class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_role === 'admin' || $user_role === 'chef_projet' || 
                                                 ($user_role === 'membre' && $task['assigned_to'] == $user_id)): ?>
                                        <button class="btn btn-sm btn-outline-info me-2 quick-update-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quickUpdateModal"
                                                data-task-id="<?= $task['id'] ?>"
                                                data-task-status="<?= $task['status'] ?>"
                                                data-task-progress="<?= $task['progress'] ?>">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Bouton Voir l'historique -->
                                        <button class="btn btn-sm btn-outline-secondary me-2 view-history-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewHistoryModal"
                                                data-task-id="<?= $task['id'] ?>"
                                                title="Voir l'historique">
                                            <i class="bi bi-clock-history"></i>
                                        </button>

                                        <!-- Bouton Commentaire (visible pour tous les utilisateurs) -->
                                        <button class="btn btn-sm btn-outline-success comment-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#addCommentModal"
                                                data-task-id="<?= $task['id'] ?>"
                                                title="Ajouter un commentaire">
                                            <i class="bi bi-chat-dots"></i>
                                        </button>
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

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/tasks_controller.php" method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addTaskModalLabel">
                        <i class="bi bi-plus-circle"></i> Ajouter une nouvelle tâche
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="titleAdd" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titleAdd" name="title" required>
                        </div>
                    
                        <div class="col-md-12">
                            <label for="descriptionAdd" class="form-label">Description</label>
                            <textarea class="form-control" id="descriptionAdd" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="startDateAdd" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="startDateAdd" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="dueDateAdd" class="form-label">Date d'échéance <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dueDateAdd" name="due_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="projectAdd" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="projectAdd" name="project_id" required>
                                <option value="" selected>-- Choisir un projet --</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="assignedToAdd" class="form-label">Assigner à</label>
                            <select class="form-select" id="assignedToAdd" name="assigned_to">
                                <option value="" selected>-- Non assigné --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_task" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="../controllers/tasks_controller.php" method="POST">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editTaskModalLabel">
                        <i class="bi bi-pencil-square"></i> Modifier la tâche
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="editTitle" class="form-label">Titre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editStatus" class="form-label">Statut</label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="To-do">À faire</option>
                                <option value="in progress">En cours</option>
                                <option value="finished">Terminé</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="editStartDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editDueDate" class="form-label">Date d'échéance <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editDueDate" name="due_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editProject" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="editProject" name="project_id" required>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editAssignedTo" class="form-label">Assigner à</label>
                            <select class="form-select" id="editAssignedTo" name="assigned_to">
                                <option value="">-- Non assigné --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="editProgress" class="form-label">Progrès (%)</label>
                            <input type="range" class="form-range" id="editProgress" name="progress" min="0" max="100" value="0">
                            <div class="d-flex justify-content-between">
                                <small>0%</small>
                                <small id="editProgressValue">0%</small>
                                <small>100%</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_task" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Update Modal -->
<div class="modal fade" id="quickUpdateModal" tabindex="-1" aria-labelledby="quickUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/tasks_controller.php" method="POST">
                <input type="hidden" name="task_id" id="quickTaskId">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="quickUpdateModalLabel">
                        <i class="bi bi-arrow-repeat"></i> Mise à jour rapide
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quickStatus" class="form-label">Statut <span class="text-danger">*</span></label>
                        <select class="form-select" id="quickStatus" name="status" required>
                            <option value="To-do">À faire</option>
                            <option value="in progress">En cours</option>
                            <option value="finished">Terminé</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="quickProgress" class="form-label">Progrès (%)</label>
                        <input type="range" class="form-range" id="quickProgress" name="progress" min="0" max="100" value="0">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <small id="quickProgressValue">0%</small>
                            <small>100%</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_status" class="btn btn-info">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir l'historique des commentaires -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="viewHistoryModalLabel">
                    <i class="bi bi-clock-history"></i> Historique des commentaires
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div id="commentsHistory" class="comments-list">
                    <div class="no-comments text-center py-4 text-muted">
                        <i class="bi bi-chat-square-text fs-1"></i>
                        <p class="mt-2">Aucun commentaire pour le moment</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un commentaire -->
<div class="modal fade" id="addCommentModal" tabindex="-1" aria-labelledby="addCommentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/tasks_controller.php" method="POST">
                <input type="hidden" name="task_id" id="commentTaskId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addCommentModalLabel">
                        <i class="bi bi-chat-dots"></i> Ajouter un commentaire
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="commentText" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentText" name="comment" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_comment" class="btn btn-success">Publier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Gestionnaire pour le changement de projet dans le modal d'ajout
        const projectSelect = document.getElementById('projectAdd');
        const assignedToSelect = document.getElementById('assignedToAdd');
        
        // Fonction pour mettre à jour les options du select "Assigner à"
        function updateAssignedToOptions(projectId) {
            // Vider le select
            assignedToSelect.innerHTML = '<option value="" selected>-- Non assigné --</option>';
            
            if (!projectId) {
                // Si aucun projet n'est sélectionné, désactiver le select
                assignedToSelect.disabled = true;
                return;
            }
            
            // Activer le select
            assignedToSelect.disabled = false;
            
            // Charger les membres du projet via AJAX
            fetch(`../controllers/tasks_controller.php?get_project_members=${projectId}`)
                .then(response => response.json())
                .then(members => {
                    if (Array.isArray(members)) {
                        members.forEach(member => {
                            const option = document.createElement('option');
                            option.value = member.id;
                            option.textContent = member.name;
                            assignedToSelect.appendChild(option);
                        });
                    } else {
                        console.error('Erreur lors du chargement des membres:', members.error);
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des membres:', error);
                });
        }
        
        // Écouter les changements sur le select de projet
        projectSelect.addEventListener('change', function() {
            updateAssignedToOptions(this.value);
        });
        
        // Initialiser l'état du select "Assigner à"
        updateAssignedToOptions(projectSelect.value);
        
        // Progress slider for add modal
        const progressAdd = document.getElementById('progressAdd');
        const progressValueAdd = document.getElementById('progressValueAdd');
        progressAdd.addEventListener('input', () => {
            progressValueAdd.textContent = `${progressAdd.value}%`;
        });
        
        // Progress slider for edit modal
        const editProgress = document.getElementById('editProgress');
        const editProgressValue = document.getElementById('editProgressValue');
        editProgress.addEventListener('input', () => {
            editProgressValue.textContent = `${editProgress.value}%`;
        });
        
        // Progress slider for quick update modal
        const quickProgress = document.getElementById('quickProgress');
        const quickProgressValue = document.getElementById('quickProgressValue');
        quickProgress.addEventListener('input', () => {
            quickProgressValue.textContent = `${quickProgress.value}%`;
        });
        
        // Edit Task Modal Functionality
        const editButtons = document.querySelectorAll('.edit-task-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                const taskData = JSON.parse(button.getAttribute('data-task'));
                
                // Remplir tous les champs avec les données existantes
                document.getElementById('editTaskId').value = taskData.id;
                document.getElementById('editTitle').value = taskData.title;
                document.getElementById('editDescription').value = taskData.description;
                document.getElementById('editStartDate').value = taskData.start_date;
                document.getElementById('editDueDate').value = taskData.due_date;
                document.getElementById('editProject').value = taskData.project_id;
                document.getElementById('editAssignedTo').value = taskData.assigned_to || '';
                document.getElementById('editStatus').value = taskData.status;
                document.getElementById('editProgress').value = taskData.progress;
                document.getElementById('editProgressValue').textContent = `${taskData.progress}%`;

                // S'assurer que les dates sont au bon format YYYY-MM-DD
                const formatDate = (dateString) => {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    return date.toISOString().split('T')[0];
                };

                document.getElementById('editStartDate').value = formatDate(taskData.start_date);
                document.getElementById('editDueDate').value = formatDate(taskData.due_date);
            });
        });
        
        // Quick Update Modal Functionality
        const quickUpdateButtons = document.querySelectorAll('.quick-update-btn');
        quickUpdateButtons.forEach(button => {
            button.addEventListener('click', () => {
                const taskId = button.getAttribute('data-task-id');
                const taskStatus = button.getAttribute('data-task-status');
                const taskProgress = button.getAttribute('data-task-progress');
                
                document.getElementById('quickTaskId').value = taskId;
                document.getElementById('quickStatus').value = taskStatus;
                document.getElementById('quickProgress').value = taskProgress;
                quickProgressValue.textContent = `${taskProgress}%`;

                // Gérer les restrictions de statut
                const statusSelect = document.getElementById('quickStatus');
                const options = statusSelect.options;

                // Réinitialiser toutes les options
                for (let i = 0; i < options.length; i++) {
                    options[i].disabled = false;
                }

                // Désactiver les options en fonction du statut actuel
                if (taskStatus === 'finished') {
                    // Si terminé, on ne peut pas revenir en arrière
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].value === 'To-do' || options[i].value === 'in progress') {
                            options[i].disabled = true;
                        }
                    }
                } else if (taskStatus === 'in progress') {
                    // Si en cours, on ne peut pas revenir à "à faire"
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].value === 'To-do') {
                            options[i].disabled = true;
                        }
                    }
                }

                // Empêcher la diminution du progrès
                const progressInput = document.getElementById('quickProgress');
                progressInput.min = taskProgress; // Définir la valeur minimale comme le progrès actuel
                
                // Ajouter un événement pour empêcher la diminution manuelle
                progressInput.addEventListener('input', function() {
                    if (parseInt(this.value) < parseInt(taskProgress)) {
                        this.value = taskProgress;
                        quickProgressValue.textContent = `${taskProgress}%`;
                    }
                });
            });
        });
        
        // Set today's date as default for start date in add modal
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDateAdd').value = today;
        
        // Set tomorrow's date as default for due date in add modal
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('dueDateAdd').value = tomorrow.toISOString().split('T')[0];

        // Gestionnaire pour le bouton de commentaire
        document.querySelectorAll('.comment-btn').forEach(button => {
            button.addEventListener('click', () => {
                const taskId = button.getAttribute('data-task-id');
                document.getElementById('commentTaskId').value = taskId;
            });
        });

        // Gestionnaire pour le bouton d'historique
        document.querySelectorAll('.view-history-btn').forEach(button => {
            button.addEventListener('click', () => {
                const taskId = button.getAttribute('data-task-id');
                const commentsContainer = document.getElementById('commentsHistory');
                
                // Charger les commentaires via AJAX
                fetch(`../controllers/tasks_controller.php?get_comments=${taskId}`)
                    .then(response => response.json())
                    .then(comments => {
                        commentsContainer.innerHTML = comments.map(comment => `
                            <div class="comment-item mb-3 p-3 border rounded">
                                <div class="d-flex justify-content-between">
                                    <strong>${comment.user_name}</strong>
                                    <small class="text-muted">${comment.created_at}</small>
                                </div>
                                <p class="mb-0 mt-2">${comment.comment}</p>
                            </div>
                        `).join('');
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des commentaires:', error);
                        commentsContainer.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des commentaires</div>';
                    });
            });
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>
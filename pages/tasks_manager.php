<?php
require '../config/db_connection.php';

// Fetch all tasks
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_project = isset($_GET['filter_project']) ? (int) $_GET['filter_project'] : 0;
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$sql = "
    SELECT t.id, t.title, t.description, t.status, t.progress, t.start_date, t.due_date,
           p.name AS project_name, u.name AS assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE 1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_project > 0) {
    $sql .= " AND t.project_id = ?";
    $params[] = $filter_project;
}

if ($filter_status !== '') {
    $sql .= " AND t.status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY t.due_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch projects
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch users
$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter une tâche
                    </button>
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
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-task-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTaskModal"
                                                data-task='<?= json_encode($task) ?>'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info me-2 quick-update-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quickUpdateModal"
                                                data-task-id="<?= $task['id'] ?>"
                                                data-task-status="<?= $task['status'] ?>"
                                                data-task-progress="<?= $task['progress'] ?>">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <a href="../controllers/tasks_controller.php?delete_task_id=<?= $task['id'] ?>"
                                           onclick="return confirm('Supprimer cette tâche ?');"
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
                        <div class="col-md-4">
                            <label for="statusAdd" class="form-label">Statut</label>
                            <select class="form-select" id="statusAdd" name="status">
                                <option value="To-do" selected>À faire</option>
                                <option value="in progress">En cours</option>
                                <option value="finished">Terminé</option>
                            </select>
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
                        <div class="col-md-12">
                            <label for="progressAdd" class="form-label">Progrès (%)</label>
                            <input type="range" class="form-range" id="progressAdd" name="progress" min="0" max="100" value="0">
                            <div class="d-flex justify-content-between">
                                <small>0%</small>
                                <small id="progressValueAdd">0%</small>
                                <small>100%</small>
                            </div>
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
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
                
                document.getElementById('editTaskId').value = taskData.id;
                document.getElementById('editTitle').value = taskData.title;
                document.getElementById('editDescription').value = taskData.description;
                document.getElementById('editStartDate').value = taskData.start_date;
                document.getElementById('editDueDate').value = taskData.due_date;
                document.getElementById('editProject').value = taskData.project_id;
                document.getElementById('editAssignedTo').value = taskData.assigned_to || '';
                document.getElementById('editStatus').value = taskData.status;
                document.getElementById('editProgress').value = taskData.progress;
                editProgressValue.textContent = `${taskData.progress}%`;
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
            });
        });
        
        // Set today's date as default for start date in add modal
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDateAdd').value = today;
        
        // Set tomorrow's date as default for due date in add modal
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('dueDateAdd').value = tomorrow.toISOString().split('T')[0];
    });
</script>

<?php include '../layouts/footer.php'; ?>
<?php
// project_view.php
require '../config/db_connection.php';

if (!isset($_GET['id']) ){
    header("Location: projects_manager.php");
    exit;
}

$project_id = (int) $_GET['id'];

// Fetch project details
$stmt = $pdo->prepare("
    SELECT p.*, u.name AS manager_name 
    FROM projects p
    JOIN users u ON p.manager_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: projects_manager.php");
    exit;
}

// Calculate days remaining
$today = new DateTime();
$end_date = new DateTime($project['end_date']);
$interval = $today->diff($end_date);
$days_remaining = $interval->invert ? 0 : $interval->days;

// Progress bar styling
$progress = (float)$project['progress'];
$progressClass = $progress >= 80 ? 'bg-success' : ($progress >= 50 ? 'bg-info' : ($progress >= 30 ? 'bg-warning' : 'bg-danger'));

// Fetch project members
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.profile_pic, s.name AS specialty
    FROM project_members pm
    JOIN users u ON pm.member_id = u.id
    LEFT JOIN specialties s ON u.specialty_id = s.id
    WHERE pm.project_id = ?
");
$stmt->execute([$project_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available members to add
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM users u
    LEFT JOIN project_members pm ON u.id = pm.member_id AND pm.project_id = ?
    WHERE pm.member_id IS NULL AND u.role_id = (SELECT id FROM roles WHERE name = 'membre')
");
$stmt->execute([$project_id]);
$availableMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch project tasks
$stmt = $pdo->prepare("
    SELECT t.*, u.name AS assigned_to_name 
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = ?
    ORDER BY t.status, t.due_date ASC
");
$stmt->execute([$project_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get error if redirected with error message
$error = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "Détails du projet: " . $project['name'];
include '../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-2">
            <?php include '../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <i class="bi bi-kanban"></i> 
                    <?= htmlspecialchars($project['name']) ?>
                </h1>
                <a href="projects_manager.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Retour aux projets
                </a>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Project Details -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h3 class="card-title">Description</h3>
                            <p class="card-text"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h5>Dates</h5>
                                    <p>
                                        <i class="bi bi-calendar-event me-2"></i>
                                        <strong>Début:</strong> <?= date('d/m/Y', strtotime($project['start_date'])) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-calendar-check me-2"></i>
                                        <strong>Fin:</strong> <?= date('d/m/Y', strtotime($project['end_date'])) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-clock me-2"></i>
                                        <strong>Jours restants:</strong> <?= $days_remaining ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Responsables</h5>
                                    <p>
                                        <i class="bi bi-person-badge me-2"></i>
                                        <strong>Manager:</strong> <?= htmlspecialchars($project['manager_name']) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-people me-2"></i>
                                        <strong>Membres:</strong> <?= count($members) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h5>Progrès du projet</h5>
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar <?= $progressClass ?>" 
                                     role="progressbar" 
                                     style="width: <?= $progress ?>%;" 
                                     aria-valuenow="<?= $progress ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?= $progress ?>%
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                    <i class="bi bi-plus-circle me-1"></i> Ajouter une tâche
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                    <i class="bi bi-person-plus me-1"></i> Ajouter un membre
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Members Section -->
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Membres de l'équipe</h5>
                </div>
                <div class="card-body">
                    <?php if (count($members) > 0): ?>
                        <div class="row">
                            <?php foreach ($members as $member): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body d-flex align-items-center">
                                            <?php if ($member['profile_pic']): ?>
                                                <img src="../uploads/profile_pics/<?= htmlspecialchars($member['profile_pic']) ?>" 
                                                     alt="Profile" class="rounded-circle me-3" width="50" height="50">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($member['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($member['specialty'] ?? 'Aucune spécialité') ?></small>
                                            </div>
                                            <div class="ms-auto">
                                                <a href="../controllers/projects_controller.php?remove_member&project_id=<?= $project_id ?>&member_id=<?= $member['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Retirer ce membre du projet ?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Aucun membre n'est encore assigné à ce projet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-task"></i> Tâches du projet</h5>
                </div>
                <div class="card-body">
                    <?php if (count($tasks) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Tâche</th>
                                        <th>Assigné à</th>
                                        <th>Dates</th>
                                        <th>Statut</th>
                                        <th>Progrès</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): 
                                        $taskProgress = (float)$task['progress'];
                                        $taskProgressClass = $taskProgress >= 80 ? 'bg-success' : ($taskProgress >= 50 ? 'bg-info' : ($taskProgress >= 30 ? 'bg-warning' : 'bg-danger'));
                                        
                                        $statusClass = $task['status'] === 'finished' ? 'bg-success' : 
                                                      ($task['status'] === 'in progress' ? 'bg-primary' : 'bg-secondary');
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                                <div class="text-muted small"><?= htmlspecialchars(substr($task['description'], 0, 50)) ?>...</div>
                                            </td>
                                            <td>
                                                <?= $task['assigned_to_name'] ? htmlspecialchars($task['assigned_to_name']) : 'Non assigné' ?>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($task['start_date'])) ?> - 
                                                <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $statusClass ?>">
                                                    <?= $task['status'] === 'To-do' ? 'À faire' : 
                                                       ($task['status'] === 'in progress' ? 'En cours' : 'Terminé') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?= $taskProgressClass ?>" 
                                                         role="progressbar" 
                                                         style="width: <?= $taskProgress ?>%;" 
                                                         aria-valuenow="<?= $taskProgress ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?= $taskProgress ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Aucune tâche n'a encore été créée pour ce projet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/projects_controller.php" method="POST">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addMemberModalLabel">
                        <i class="bi bi-person-plus"></i> Ajouter un membre au projet
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="memberSelect" class="form-label">Sélectionner un membre <span class="text-danger">*</span></label>
                        <select class="form-select" id="memberSelect" name="member_id" required>
                            <option value="" selected>-- Choisir un membre --</option>
                            <?php foreach ($availableMembers as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_member" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/tasks_controller.php" method="POST">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTaskModalLabel">
                        <i class="bi bi-plus-circle"></i> Ajouter une nouvelle tâche
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="taskTitle" class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="taskTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="taskStartDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="taskStartDate" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="taskDueDate" class="form-label">Date d'échéance <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="taskDueDate" name="due_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="assignTo" class="form-label">Assigner à</label>
                        <select class="form-select" id="assignTo" name="assigned_to">
                            <option value="">-- Non assigné --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskStatus" class="form-label">Statut</label>
                        <select class="form-select" id="taskStatus" name="status">
                            <option value="To-do">À faire</option>
                            <option value="in progress">En cours</option>
                            <option value="finished">Terminé</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="taskProgress" class="form-label">Progrès (%)</label>
                        <input type="range" class="form-range" id="taskProgress" name="progress" min="0" max="100" value="0">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <small id="taskProgressValue">0%</small>
                            <small>100%</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_task" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Task progress slider
        const taskProgress = document.getElementById('taskProgress');
        const taskProgressValue = document.getElementById('taskProgressValue');
        
        taskProgress.addEventListener('input', () => {
            taskProgressValue.textContent = `${taskProgress.value}%`;
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>
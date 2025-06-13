<?php
// project_view.php
require '../config/db_connection.php';

// Vérification de la session
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Gestion de l'ajout de tâche
if (isset($_POST['add_task']) && isset($_POST['project_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (project_id, title, description, start_date, due_date, progress, assigned_to, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'To-do')");
        if ($stmt->execute([
            $_POST['project_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['due_date'],
            $_POST['progress'],
            $_POST['assigned_to'] ?: null
        ])) {
            header("Location: project_view.php?id=" . $_POST['project_id'] . "&success=Tâche ajoutée avec succès");
        } else {
            throw new Exception("Erreur lors de l'ajout de la tâche");
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout de la tâche: " . $e->getMessage());
        header("Location: project_view.php?id=" . $_POST['project_id'] . "&error=" . urlencode($e->getMessage()));
    }
    exit;
}

// Gestion de l'ajout de commentaire
if (isset($_POST['add_comment']) && isset($_POST['task_id']) && isset($_POST['comment'])) {
    try {
        // Vérifier si la table task_comments existe, sinon la créer
        $pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
        )");

        // Vérifier si la tâche existe
        $check_task = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
        $check_task->execute([$_POST['task_id']]);
        if (!$check_task->fetch()) {
            throw new Exception("La tâche n'existe pas");
        }

        $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        if ($stmt->execute([$_POST['task_id'], $_SESSION['user_id'], $_POST['comment']])) {
            // Redirection vers la même page pour éviter la soumission multiple du formulaire
            header("Location: project_view.php?id=" . $_POST['project_id'] . "&success=Commentaire ajouté avec succès");
        } else {
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout du commentaire: " . $e->getMessage());
        header("Location: project_view.php?id=" . $_POST['project_id'] . "&error=" . urlencode($e->getMessage()));
    }
    exit;
}

// Gestion de la modification de tâche
if (isset($_POST['edit_task']) && isset($_POST['task_id'])) {
    try {
        // Vérifier si la tâche existe
        $check_task = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND project_id = ?");
        $check_task->execute([$_POST['task_id'], $_POST['project_id']]);
        if (!$check_task->fetch()) {
            throw new Exception("La tâche n'existe pas ou n'appartient pas à ce projet");
        }

        // Mettre à jour la tâche
        $stmt = $pdo->prepare("UPDATE tasks SET 
            title = ?, 
            description = ?, 
            start_date = ?, 
            due_date = ?, 
            progress = ?, 
            assigned_to = ? 
            WHERE id = ? AND project_id = ?");
            
        if ($stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['start_date'],
            $_POST['due_date'],
            $_POST['progress'],
            $_POST['assigned_to'] ?: null,
            $_POST['task_id'],
            $_POST['project_id']
        ])) {
            header("Location: project_view.php?id=" . $_POST['project_id'] . "&success=Tâche modifiée avec succès");
        } else {
            throw new Exception("Erreur lors de la modification de la tâche");
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la modification de la tâche: " . $e->getMessage());
        header("Location: project_view.php?id=" . $_POST['project_id'] . "&error=" . urlencode($e->getMessage()));
    }
    exit;
}

// Récupération du rôle de l'utilisateur
$stmt = $pdo->prepare("SELECT r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();

// Check if project ID is set and valid
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    header("Location: projects_manager.php");
    exit;
}

$project_id = (int)$_GET['id'];

try {
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

} catch (PDOException $e) {
    $error = "Erreur de base de données : " . htmlspecialchars($e->getMessage());
}

// Get error if redirected with error message
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

$pageTitle = "Détails du projet : " . htmlspecialchars($project['name']);
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

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
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
                                        <strong>Début :</strong> <?= date('d/m/Y', strtotime($project['start_date'])) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-calendar-check me-2"></i>
                                        <strong>Fin :</strong> <?= date('d/m/Y', strtotime($project['end_date'])) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-clock me-2"></i>
                                        <strong>Jours restants :</strong> <?= $days_remaining ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Responsables</h5>
                                    <p>
                                        <i class="bi bi-person-badge me-2"></i>
                                        <strong>Manager :</strong> <?= htmlspecialchars($project['manager_name']) ?>
                                    </p>
                                    <p>
                                        <i class="bi bi-people me-2"></i>
                                        <strong>Membres :</strong> <?= count($members) ?>
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
                            <?php if ($user_role === 'admin' || $user_role === 'chef_projet'): ?>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                        <i class="bi bi-plus-circle me-1"></i> Ajouter une tâche
                                    </button>
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                        <i class="bi bi-person-plus me-1"></i> Ajouter un membre
                                    </button>
                                </div>
                            <?php endif; ?>
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
                                            <?php if ($user_role === 'admin' || $user_role === 'chef_projet'): ?>
                                                <div class="ms-auto">
                                                    <a href="../controllers/projects_controller.php?action=remove_member&project_id=<?= $project_id ?>&member_id=<?= $member['id'] ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       title="Retirer du projet"
                                                       onclick="return confirm('Voulez-vous retirer ce membre du projet ?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
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
                                                <?php if ($user_role === 'admin' || $user_role === 'chef_projet'): ?>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editTaskModal"
                                                            data-task-id="<?= $task['id'] ?>"
                                                            data-task-title="<?= htmlspecialchars($task['title']) ?>"
                                                            data-task-description="<?= htmlspecialchars($task['description']) ?>"
                                                            data-task-start-date="<?= $task['start_date'] ?>"
                                                            data-task-due-date="<?= $task['due_date'] ?>"
                                                            data-task-assigned-to="<?= $task['assigned_to'] ?>"
                                                            data-task-progress="<?= $taskProgress ?>"
                                                            title="Modifier la tâche">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <a href="../controllers/tasks_controller.php?action=delete_task&task_id=<?= $task['id'] ?>&project_id=<?= $project_id ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       title="Supprimer la tâche"
                                                       onclick="return confirm('Voulez-vous supprimer cette tâche ?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Bouton Voir l'historique -->
                                                <button class="btn btn-sm btn-outline-secondary me-2 view-history-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#viewHistoryModal"
                                                        data-task-id="<?= $task['id'] ?>"
                                                        title="Voir l'historique">
                                                    <i class="bi bi-clock-history"></i>
                                                </button>

                                                <!-- Bouton Commentaire -->
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
                <input type="hidden" name="add_member" value="1">
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
                            <option value="">-- Choisir un membre --</option>
                            <?php foreach ($availableMembers as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="project_view.php?id=<?= $project_id ?>" method="POST">
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
                            <input type="date" class="form-control" id="taskStartDate" name="start_date" 
                                   min="<?= htmlspecialchars($project['start_date']) ?>" 
                                   max="<?= htmlspecialchars($project['end_date']) ?>" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="taskDueDate" class="form-label">Date d'échéance <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="taskDueDate" name="due_date" 
                                   min="<?= htmlspecialchars($project['start_date']) ?>" 
                                   max="<?= htmlspecialchars($project['end_date']) ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="taskProgress" class="form-label">Progrès initial (%)</label>
                        <input type="range" class="form-range" id="taskProgress" name="progress" min="0" max="100" value="0">
                        <span id="taskProgressValue">0%</span>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_task" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="project_view.php?id=<?= $project_id ?>" method="POST">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
                <input type="hidden" name="task_id" id="editTaskId">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editTaskModalLabel">
                        <i class="bi bi-pencil"></i> Modifier la tâche
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editTaskTitle" class="form-label">Titre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTaskTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editTaskDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editTaskStartDate" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editTaskStartDate" name="start_date" 
                                   min="<?= htmlspecialchars($project['start_date']) ?>" 
                                   max="<?= htmlspecialchars($project['end_date']) ?>" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="editTaskDueDate" class="form-label">Date d'échéance <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="editTaskDueDate" name="due_date" 
                                   min="<?= htmlspecialchars($project['start_date']) ?>" 
                                   max="<?= htmlspecialchars($project['end_date']) ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editTaskProgress" class="form-label">Progrès (%)</label>
                        <input type="range" class="form-range" id="editTaskProgress" name="progress" min="0" max="100" value="0">
                        <span id="editTaskProgressValue">0%</span>
                    </div>
                    <div class="mb-3">
                        <label for="editAssignTo" class="form-label">Assigner à</label>
                        <select class="form-select" id="editAssignTo" name="assigned_to">
                            <option value="">-- Non assigné --</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_task" class="btn btn-warning">Modifier</button>
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
                    <!-- Les commentaires seront chargés ici dynamiquement -->
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
            <form action="project_view.php?id=<?= $project_id ?>" method="POST">
                <input type="hidden" name="task_id" id="commentTaskId">
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
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
// Initialize tooltips
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

// Task progress slider for Add Task Modal
const taskProgress = document.getElementById('taskProgress');
const taskProgressValue = document.getElementById('taskProgressValue');
taskProgress.addEventListener('input', () => {
    taskProgressValue.textContent = `${taskProgress.value}%`;
});

// Task progress slider for Edit Task Modal
const editTaskProgress = document.getElementById('editTaskProgress');
const editTaskProgressValue = document.getElementById('editTaskProgressValue');
editTaskProgress.addEventListener('input', () => {
    editTaskProgressValue.textContent = `${editTaskProgress.value}%`;
});

// Populate Edit Task Modal with task data
document.querySelectorAll('[data-bs-target="#editTaskModal"]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('editTaskId').value = button.dataset.taskId;
        document.getElementById('editTaskTitle').value = button.dataset.taskTitle;
        document.getElementById('editTaskDescription').value = button.dataset.taskDescription;
        document.getElementById('editTaskStartDate').value = button.dataset.taskStartDate;
        document.getElementById('editTaskDueDate').value = button.dataset.taskDueDate;
        document.getElementById('editTaskProgress').value = button.dataset.taskProgress;
        document.getElementById('editTaskProgressValue').textContent = `${button.dataset.taskProgress}%`;
        document.getElementById('editAssignTo').value = button.dataset.taskAssignedTo || '';
    });
});

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
</script>

<?php include '../layouts/footer.php'; ?>
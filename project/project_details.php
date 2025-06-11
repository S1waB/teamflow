<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// Vérification de l'authentification
if (!isAuthenticated()) {
    header('Location: /login.php');
    exit();
}

// Vérification du rôle
if (!in_array($_SESSION['role'], ['admin', 'chef_projet', 'membre'])) {
    header('Location: /login.php');
    exit();
}

// Récupération de l'ID du projet
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$projectId) {
    header('Location: /project/projects.php');
    exit();
}

// Récupération des détails du projet
$stmt = $pdo->prepare("
    SELECT p.*, u.name as manager_name 
    FROM projects p 
    LEFT JOIN users u ON p.manager_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: /project/projects.php');
    exit();
}

// Récupération des tâches du projet
$stmt = $pdo->prepare("
    SELECT t.*, u.name as assigned_to_name 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    WHERE t.project_id = ? 
    ORDER BY t.due_date ASC
");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Détails du Projet - " . htmlspecialchars($project['name']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
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
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
        }

        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 4px;
        }

        .task-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s ease;
        }

        .task-item:hover {
            background-color: #f8fafc;
        }

        .task-item:last-child {
            border-bottom: none;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-badge.success {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-badge.warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge.danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-badge.info {
            background-color: #dbeafe;
            color: #1e40af;
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
                <div>
                    <h1 class="h3 mb-2"><?= htmlspecialchars($project['name']) ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/project/projects.php">Projets</a></li>
                            <li class="breadcrumb-item active">Détails du projet</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="/project/projects.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Retour
                    </a>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chef_projet'): ?>
                    <button class="btn btn-primary" onclick="editProject(<?= $project['id'] ?>)">
                        <i class="bi bi-pencil"></i> Modifier
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Informations du projet -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Informations du projet</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Description</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($project['description'])) ?: 'Aucune description' ?></p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-2">Date de début</h6>
                                <p class="mb-0"><?= date('d/m/Y', strtotime($project['start_date'])) ?></p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-2">Date de fin prévue</h6>
                                <p class="mb-0"><?= date('d/m/Y', strtotime($project['end_date'])) ?></p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Progression</h6>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: <?= $project['progress'] ?>%"
                                         aria-valuenow="<?= $project['progress'] ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <span class="text-muted"><?= $project['progress'] ?>%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Chef de projet</h6>
                            <p class="mb-0">
                                <i class="bi bi-person-circle me-2"></i>
                                <?= htmlspecialchars($project['manager_name']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Liste des tâches -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Tâches du projet</h5>
                        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chef_projet'): ?>
                        <button class="btn btn-primary btn-sm" onclick="addTask(<?= $project['id'] ?>)">
                            <i class="bi bi-plus-lg"></i> Nouvelle tâche
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($tasks)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Aucune tâche n'a été créée pour ce projet</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <div class="task-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($task['title']) ?></h6>
                                    <p class="text-muted mb-2"><?= htmlspecialchars($task['description']) ?></p>
                                    <div class="d-flex gap-3">
                                        <small class="text-muted">
                                            <i class="bi bi-person me-1"></i>
                                            <?= htmlspecialchars($task['assigned_to_name'] ?? 'Non assigné') ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php
                                    $statusClass = '';
                                    switch($task['status']) {
                                        case 'completed':
                                            $statusClass = 'success';
                                            break;
                                        case 'in_progress':
                                            $statusClass = 'info';
                                            break;
                                        case 'pending':
                                            $statusClass = 'warning';
                                            break;
                                        default:
                                            $statusClass = 'danger';
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                    </span>
                                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chef_projet'): ?>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editTask(<?= $task['id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistiques et activités -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Statistiques</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Tâches</h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="mb-0"><?= count($tasks) ?></h3>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= count(array_filter($tasks, fn($t) => $t['status'] === 'completed')) ?></h3>
                                    <small class="text-muted">Terminées</small>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?= count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress')) ?></h3>
                                    <small class="text-muted">En cours</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted mb-2">Temps restant</h6>
                            <?php
                            $endDate = new DateTime($project['end_date']);
                            $today = new DateTime();
                            $interval = $today->diff($endDate);
                            $daysLeft = $interval->days;
                            ?>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock me-2 text-primary"></i>
                                <span><?= $daysLeft ?> jours restants</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProject(id) {
            window.location.href = `projects.php?edit=${id}`;
        }

        function addTask(projectId) {
            window.location.href = `tasks.php?project_id=${projectId}&action=add`;
        }

        function editTask(taskId) {
            window.location.href = `tasks.php?id=${taskId}&action=edit`;
        }
    </script>
</body>
</html> 
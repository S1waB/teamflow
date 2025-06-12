<?php
require '../config/db_connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];

// 1. User statistics by role - Admin only
$roleStats = [];
if ($currentUserRole === 'admin') {
    try {
        $stmt = $pdo->prepare("
            SELECT roles.name AS role_name, COUNT(users.id) AS user_count
            FROM roles
            LEFT JOIN users ON users.role_id = roles.id
            GROUP BY roles.id
        ");
        $stmt->execute();
        $roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Error handling
    }
}

// 2. Recent projects - Different based on role
$recentProjects = [];
try {
    if ($currentUserRole === 'admin') {
        $stmt = $pdo->prepare("
            SELECT p.name, p.start_date, u.name AS manager_name
            FROM projects p
            JOIN users u ON p.manager_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE r.name = 'chef_projet'
            ORDER BY p.start_date DESC
            LIMIT 5
        ");
    } elseif ($currentUserRole === 'chef_projet') {
        $stmt = $pdo->prepare("
            SELECT p.name, p.start_date, u.name AS manager_name
            FROM projects p
            JOIN users u ON p.manager_id = u.id
            WHERE p.manager_id = :userId
            ORDER BY p.start_date DESC
            LIMIT 5
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
    } else { // Member
        $stmt = $pdo->prepare("
            SELECT p.name, p.start_date, u.name AS manager_name
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            JOIN users u ON p.manager_id = u.id
            WHERE pm.member_id = :userId
            ORDER BY p.start_date DESC
            LIMIT 5
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handling
}

// 3. Recent tasks - Different based on role
$recentTasks = [];
try {
    if ($currentUserRole === 'admin') {
        $stmt = $pdo->prepare("
            SELECT t.title, t.status, t.start_date, u.name AS assigned_to
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE r.name = 'membre'
            ORDER BY t.start_date DESC
            LIMIT 5
        ");
    } elseif ($currentUserRole === 'chef_projet') {
        $stmt = $pdo->prepare("
            SELECT t.title, t.status, t.start_date, u.name AS assigned_to
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            JOIN users u ON t.assigned_to = u.id
            WHERE p.manager_id = :userId
            ORDER BY t.start_date DESC
            LIMIT 5
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
    } else { // Member
        $stmt = $pdo->prepare("
            SELECT t.title, t.status, t.start_date, u.name AS assigned_to
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE t.assigned_to = :userId
            ORDER BY t.start_date DESC
            LIMIT 5
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handling
}

// 4. Projects per project manager - Admin only
$projectPerChef = [];
if ($currentUserRole === 'admin') {
    try {
        $stmt = $pdo->prepare("
            SELECT u.name AS manager_name, COUNT(p.id) AS project_count
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN projects p ON p.manager_id = u.id
            WHERE r.name = 'chef_projet'
            GROUP BY u.id
        ");
        $stmt->execute();
        $projectPerChef = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Error handling
    }
}

// NEW STATISTICS: Role-specific totals
$totalUsers = 0;
$totalProjects = 0;
$totalTasks = 0;

try {
    if ($currentUserRole === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $totalUsers = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_projects FROM projects");
        $totalProjects = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_tasks FROM tasks");
        $totalTasks = $stmt->fetchColumn();
    } elseif ($currentUserRole === 'chef_projet') {
        // Chef projet sees only their own projects
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_projects FROM projects WHERE manager_id = :userId");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalProjects = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_tasks 
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE p.manager_id = :userId
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalTasks = $stmt->fetchColumn();
        
        // Members in their projects
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pm.member_id) as total_users 
            FROM project_members pm
            JOIN projects p ON pm.project_id = p.id
            WHERE p.manager_id = :userId
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalUsers = $stmt->fetchColumn();
    } else { // Member
        // Projects they're part of
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT project_id) as total_projects FROM project_members WHERE member_id = :userId");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalProjects = $stmt->fetchColumn();
        
        // Tasks assigned to them
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_tasks FROM tasks WHERE assigned_to = :userId");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalTasks = $stmt->fetchColumn();
        
        // Users they work with (in same projects)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id) as total_users 
            FROM users u
            JOIN project_members pm ON u.id = pm.member_id
            WHERE pm.project_id IN (
                SELECT project_id 
                FROM project_members 
                WHERE member_id = :userId
            )
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $totalUsers = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Error handling
}

// NEW STATISTICS: Tasks by status - Role specific
$tasksByStatus = [];
try {
    if ($currentUserRole === 'admin') {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM tasks 
            GROUP BY status
        ");
        $stmt->execute();
    } elseif ($currentUserRole === 'chef_projet') {
        $stmt = $pdo->prepare("
            SELECT t.status, COUNT(*) as count 
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE p.manager_id = :userId
            GROUP BY t.status
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
    } else { // Member
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM tasks 
            WHERE assigned_to = :userId
            GROUP BY status
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
    }
    $tasksByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error handling
}

// NEW STATISTICS: Average projects per manager - Admin only
$avgProjectsPerManager = 0;
if ($currentUserRole === 'admin' && count($projectPerChef)) {
    $totalProjectsByManagers = array_sum(array_column($projectPerChef, 'project_count'));
    $avgProjectsPerManager = round($totalProjectsByManagers / count($projectPerChef), 1);
}

// ========== NEW CHART DATA FETCH ========== //
// Fetch data for charts based on role
$projectsProgress = [];
$tasksStatusData = [];

if ($currentUserRole === 'chef_projet') {
    // Projects progress for chef's projects
    try {
        $stmt = $pdo->prepare("
            SELECT name, progress 
            FROM projects 
            WHERE manager_id = :userId
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $projectsProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Projects progress error: " . $e->getMessage());
    }

    // Task status distribution for chef's projects
    try {
        $stmt = $pdo->prepare("
            SELECT t.status, COUNT(*) as count
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE p.manager_id = :userId
            GROUP BY t.status
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $tasksStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Tasks status error: " . $e->getMessage());
    }
} elseif ($currentUserRole === 'membre') {
    // Projects progress for member's projects
    try {
        $stmt = $pdo->prepare("
            SELECT p.name, p.progress 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.member_id = :userId
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $projectsProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Projects progress error: " . $e->getMessage());
    }

    // Task status distribution for member's tasks
    try {
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM tasks 
            WHERE assigned_to = :userId
            GROUP BY status
        ");
        $stmt->bindParam(':userId', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        $tasksStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Tasks status error: " . $e->getMessage());
    }
}

// Prepare data for Chart.js
$chartData = [
    'projects' => [
        'labels' => [],
        'progress' => []
    ],
    'tasks' => [
        'labels' => ['To-do', 'in progress', 'finished'],
        'data' => [0, 0, 0]
    ]
];

foreach ($projectsProgress as $project) {
    $chartData['projects']['labels'][] = $project['name'];
    $chartData['projects']['progress'][] = $project['progress'];
}

foreach ($tasksStatusData as $status) {
    $index = array_search($status['status'], $chartData['tasks']['labels']);
    if ($index !== false) {
        $chartData['tasks']['data'][$index] = (int)$status['count'];
    }
}

$pageTitle = "Dashboard";
include '../layouts/header.php';
?>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 col-lg-2 d-md-block sidebar collapse bg-primary">
            <?php include '../layouts/sidebar.php'; ?>
        </div>

        <div class="col-md-10 ms-sm-auto main-content">
            <div class="dashboard-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <br><h1 class="welcome-text">Bienvenue dans le Dashboard !</h1><br>
                        <p class="text-muted mb-0">Aperçu des activités et statistiques récentes</p><br>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row">
                <!-- Total Users Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        <?= ($currentUserRole === 'admin') ? 'Total Utilisateurs' : 'Collègues' ?>
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalUsers ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Projects Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Projets
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalProjects ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Tasks Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Tâches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalTasks ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks by Status Card -->
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        État des Tâches
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $statuses = ['To-do' => 0, 'in progress' => 0, 'finished' => 0];
                                        foreach ($tasksByStatus as $status) {
                                            $statuses[$status['status']] = $status['count'];
                                        }
                                        echo "{$statuses['To-do']} / {$statuses['in progress']} / {$statuses['finished']}";
                                        ?>
                                    </div>
                                    <div class="mt-2 text-muted">
                                        <small>À faire / En cours / Terminé</small>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== NEW CHARTS SECTION ========== -->
            <?php if (in_array($currentUserRole, ['chef_projet', 'membre']) && (!empty($projectsProgress) || !empty($tasksStatusData))): ?>
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?= $currentUserRole === 'chef_projet' ? 'Progression des projets' : 'Progression de vos projets' ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="projectsProgressChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <?= $currentUserRole === 'chef_projet' ? 'Statut des tâches' : 'Statut de vos tâches' ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="tasksStatusChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Utilisateurs par Rôle (Admin only) -->
                    <?php if ($currentUserRole === 'admin' && !empty($roleStats)): ?>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Utilisateurs par Rôle</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($roleStats as $stat): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center p-3 bg-light rounded">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded p-3 me-3">
                                                <i class="fas fa-user fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="h5 mb-0"><?= (int)$stat['user_count'] ?></div>
                                                <div class="text-muted"><?= htmlspecialchars($stat['role_name']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Projets par Chef de Projet (Admin only) -->
                    <?php if ($currentUserRole === 'admin' && !empty($projectPerChef)): ?>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Projets par Chef de Projet</h5></div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($projectPerChef as $chef): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div><i class="fas fa-user-tie me-2 text-primary"></i><?= htmlspecialchars($chef['manager_name']) ?></div>
                                        <span class="badge bg-primary rounded-pill"><?= $chef['project_count'] ?> projet(s)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <!-- Projets Récents -->
                    <?php if (!empty($recentProjects)): ?>
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Projets Récents</h5></div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($recentProjects as $proj): ?>
                                    <li class="list-group-item">
                                        <h6><?= htmlspecialchars($proj['name']) ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($proj['manager_name']) ?>
                                            &bull;
                                            <i class="fas fa-calendar me-1"></i> <?= $proj['start_date'] ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Tâches Récentes -->
                    <?php if (!empty($recentTasks)): ?>
                    <div class="card mt-4">
                        <div class="card-header"><h5 class="mb-0">Tâches Récentes</h5></div>
                        <div class="card-body">
                            <ul class="list-group">
                                <?php foreach ($recentTasks as $task): ?>
                                    <li class="list-group-item">
                                        <h6><?= htmlspecialchars($task['title']) ?> <small class="badge bg-secondary ms-2"><?= $task['status'] ?></small></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> <?= htmlspecialchars($task['assigned_to']) ?>
                                            &bull;
                                            <i class="fas fa-calendar me-1"></i> <?= $task['start_date'] ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== NEW CHART SCRIPT ========== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Projects Progress Chart
    const projectsCtx = document.getElementById('projectsProgressChart');
    if (projectsCtx) {
        new Chart(projectsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartData['projects']['labels']) ?>,
                datasets: [{
                    label: 'Progression (%)',
                    data: <?= json_encode($chartData['projects']['progress']) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // Tasks Status Chart
    const tasksCtx = document.getElementById('tasksStatusChart');
    if (tasksCtx) {
        new Chart(tasksCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($chartData['tasks']['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($chartData['tasks']['data']) ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',   // To-do
                        'rgba(255, 206, 86, 0.7)',    // In progress
                        'rgba(75, 192, 192, 0.7)'     // Finished
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} tâches`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
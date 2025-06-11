<?php
session_start();
require '../config/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch total users count with role distribution
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN r.name = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN r.name = 'chef_projet' THEN 1 ELSE 0 END) as manager_count,
    SUM(CASE WHEN r.name = 'membre' THEN 1 ELSE 0 END) as member_count
    FROM users u 
    JOIN roles r ON u.role_id = r.id");
$userStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalUsers = $userStats['total_users'];

// Fetch total projects count with status distribution
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_projects,
    SUM(CASE WHEN progress < 25 THEN 1 ELSE 0 END) as not_started,
    SUM(CASE WHEN progress >= 25 AND progress < 50 THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN progress >= 50 AND progress < 75 THEN 1 ELSE 0 END) as almost_done,
    SUM(CASE WHEN progress >= 75 THEN 1 ELSE 0 END) as completed
    FROM projects");
$projectStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalProjects = $projectStats['total_projects'];

// Fetch total tasks count with status distribution
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'To-do' THEN 1 ELSE 0 END) as todo_count,
    SUM(CASE WHEN status = 'in progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN status = 'finished' THEN 1 ELSE 0 END) as finished_count
    FROM tasks");
$taskStats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalTasks = $taskStats['total_tasks'];

// Fetch recent projects with more details
$stmt = $pdo->query("SELECT p.*, 
    u.name AS manager_name,
    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as total_tasks,
    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'finished') as completed_tasks
    FROM projects p 
    JOIN users u ON p.manager_id = u.id 
    ORDER BY p.start_date DESC LIMIT 5");
$recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users list with roles and activity status
$stmt = $pdo->query("SELECT 
    u.id, u.name, u.email,
    r.name AS role_name,
    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id) as assigned_tasks,
    (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status = 'finished') as completed_tasks
    FROM users u 
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch project statistics for charts
$stmt = $pdo->query("SELECT 
    DATE_FORMAT(start_date, '%Y-%m') as month,
    COUNT(*) as project_count
    FROM projects 
    GROUP BY DATE_FORMAT(start_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6");
$projectTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = "Admin Dashboard";
include '../layouts/header.php';
?>

<!-- Add required CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="dashboard-wrapper">
    <div class="row">
        <div class="col-3">
            <?php include '../layouts/sidebar.php'; ?>
        </div>
        <div class="col-9">
            <div class="container-fluid mt-4">
                <!-- Header Section -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></h1>
                            <p class="text-muted">Here's what's happening with your projects today.</p>
                        </div>
                        <div class="date-badge">
                            <i class="fas fa-calendar-alt"></i>
                            <?= date('F d, Y') ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card primary">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Total Utilisateurs</h6>
                                    <h3 class="stat-card-value"><?= $totalUsers ?></h3>
                                    <p class="stat-card-desc">
                                        <span>Admin: <?= $userStats['admin_count'] ?></span> |
                                        <span>Chefs: <?= $userStats['manager_count'] ?></span> |
                                        <span>Membres: <?= $userStats['member_count'] ?></span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Total Projets</h6>
                                    <h3 class="stat-card-value"><?= $totalProjects ?></h3>
                                    <p class="stat-card-desc">
                                        <span>Terminés: <?= $projectStats['completed'] ?></span> |
                                        <span>En cours: <?= $projectStats['in_progress'] + $projectStats['almost_done'] ?></span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Total Tâches</h6>
                                    <h3 class="stat-card-value"><?= $totalTasks ?></h3>
                                    <p class="stat-card-desc">
                                        <span>Terminées: <?= $taskStats['finished_count'] ?></span> |
                                        <span>En cours: <?= $taskStats['in_progress_count'] ?></span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Taux de Complétion</h6>
                                    <h3 class="stat-card-value">
                                        <?= $totalTasks > 0 ? round(($taskStats['finished_count'] / $totalTasks) * 100) : 0 ?>%
                                    </h3>
                                    <p class="stat-card-desc">
                                        <span>Projets: <?= $totalProjects > 0 ? round(($projectStats['completed'] / $totalProjects) * 100) : 0 ?>%</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Project Progress Overview</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="projectProgressChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Project Status</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="projectStatusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Recent Projects</h6>
                        <button class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Project
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Manager</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProjects as $project): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="project-icon">
                                                        <i class="fas fa-folder"></i>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h6 class="mb-0"><?= htmlspecialchars($project['name']) ?></h6>
                                                        <small class="text-muted">Project ID: #<?= $project['id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <span class="ml-2"><?= htmlspecialchars($project['manager_name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($project['start_date']) ?></td>
                                            <td><?= htmlspecialchars($project['end_date']) ?></td>
                                            <td>
                                                <div class="progress-wrapper">
                                                    <div class="progress">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= $project['progress'] ?>%"
                                                             aria-valuenow="<?= $project['progress'] ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small class="progress-label"><?= round($project['progress'], 1) ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                if ($project['progress'] >= 75) $statusClass = 'success';
                                                elseif ($project['progress'] >= 50) $statusClass = 'info';
                                                elseif ($project['progress'] >= 25) $statusClass = 'warning';
                                                else $statusClass = 'danger';
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= round($project['progress'], 1) ?>% Complete
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Users List</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar">
                                                        <i class="fas fa-user-circle"></i>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h6 class="mb-0"><?= htmlspecialchars($user['name']) ?></h6>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-envelope text-muted mr-2"></i>
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge">
                                                    <?= htmlspecialchars($user['role_name']) ?>
                                                </span>
                                            </td>
                                          
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-icon btn-info" onclick="viewUser(<?= $user['id'] ?>)" title="Voir le profil">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-primary" onclick="editUser(<?= $user['id'] ?>)" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-danger" onclick="deleteUser(<?= $user['id'] ?>)" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
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
    </div>
</div>

<style>
/* Modern Dashboard Styles */
:root {
    --primary-color: #4e73df;
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --text-color: #5a5c69;
    --border-radius: 0.35rem;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

body {
    font-family: 'Inter', sans-serif;
    background-color: #f8f9fc;
    color: var(--text-color);
}

.dashboard-wrapper {
    padding: 1.5rem;
}

/* Header Styles */
.dashboard-header {
    margin-bottom: 2rem;
}

.date-badge {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    font-weight: 500;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease-in-out;
    border-left: 4px solid;
    margin-bottom: 1.5rem;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.primary { border-left-color: var(--primary-color); }
.stat-card.success { border-left-color: var(--success-color); }
.stat-card.warning { border-left-color: var(--warning-color); }

.stat-card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card-info {
    flex: 1;
}

.stat-card-title {
    color: var(--text-color);
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-card-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.stat-card-desc {
    font-size: 0.875rem;
    color: #858796;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(78, 115, 223, 0.1);
    color: var(--primary-color);
    font-size: 1.5rem;
}

/* Card Styles */
.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #e3e6f0;
    padding: 1rem 1.25rem;
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fc;
    border-bottom: 2px solid #e3e6f0;
}

.table td {
    vertical-align: middle;
    padding: 1rem;
}

/* Progress Bar */
.progress-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.progress {
    flex: 1;
    height: 8px;
    background-color: #eaecf4;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    background-color: var(--primary-color);
    border-radius: 4px;
}

.progress-label {
    min-width: 45px;
    text-align: right;
    font-size: 0.875rem;
    color: var(--text-color);
}

/* Badges */
.status-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.warning {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.status-badge.danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.role-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    background-color: rgba(78, 115, 223, 0.1);
    color: var(--primary-color);
}

/* Avatar */
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #eaecf4;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-color);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.btn-icon:hover {
    transform: translateY(-2px);
}

/* Project Icon */
.project-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(78, 115, 223, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.25rem;
}
</style>

<script>
// Project Progress Chart
const projectProgressCtx = document.getElementById('projectProgressChart').getContext('2d');
new Chart(projectProgressCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column(array_reverse($projectTrends), 'month')) ?>,
        datasets: [{
            label: 'Nouveaux Projets',
            data: <?= json_encode(array_column(array_reverse($projectTrends), 'project_count')) ?>,
            borderColor: '#4e73df',
            tension: 0.4,
            fill: true,
            backgroundColor: 'rgba(78, 115, 223, 0.1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Project Status Chart
const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
new Chart(projectStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Non démarré', 'En cours', 'Presque terminé', 'Terminé'],
        datasets: [{
            data: [
                <?= $projectStats['not_started'] ?>,
                <?= $projectStats['in_progress'] ?>,
                <?= $projectStats['almost_done'] ?>,
                <?= $projectStats['completed'] ?>
            ],
            backgroundColor: [
                '#e74a3b',
                '#f6c23e',
                '#4e73df',
                '#1cc88a'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Fonctions de gestion des utilisateurs
async function viewUser(id) {
    window.location.href = `user_details.php?id=${id}`;
}

async function editUser(id) {
    try {
        const response = await fetch(`../api/users.php?id=${id}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erreur lors du chargement des données utilisateur');
        }
        
        const user = data.data;
        document.getElementById('userId').value = user.id;
        document.getElementById('userName').value = user.name;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userRole').value = user.role_id;
        
        // Afficher le modal d'édition
        const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
        editUserModal.show();
    } catch (error) {
        showError(error.message);
    }
}

async function deleteUser(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) {
        try {
            const response = await fetch('../api/users.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Erreur lors de la suppression de l\'utilisateur');
            }
            
            // Recharger la page pour mettre à jour la liste
            window.location.reload();
        } catch (error) {
            showError(error.message);
        }
    }
}

// Gestionnaire d'événements pour le formulaire d'édition
document.getElementById('saveUserBtn')?.addEventListener('click', async () => {
    const userId = document.getElementById('userId').value;
    const formData = {
        id: userId || null,
        name: document.getElementById('userName').value,
        email: document.getElementById('userEmail').value,
        role_id: document.getElementById('userRole').value
    };

    try {
        const response = await fetch('../api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erreur lors de l\'enregistrement de l\'utilisateur');
        }
        
        // Fermer le modal et recharger la page
        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
        window.location.reload();
    } catch (error) {
        showError(error.message);
    }
});
</script>

<!-- Modal d'édition utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Modifier l'utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalMessage" class="alert d-none" role="alert"></div>
                <form id="editUserForm">
                    <input type="hidden" id="userId">
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" id="userName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="userEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôle</label>
                        <select class="form-select" id="userRole" required>
                            <?php
                            $roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($roles as $role) {
                                echo "<option value='" . $role['id'] . "'>" . htmlspecialchars($role['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveUserBtn">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
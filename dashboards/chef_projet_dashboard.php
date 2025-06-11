<?php
session_start();
require '../config/db_connection.php';

// Check if user is logged in and is project manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef_projet') {
    header('Location: login.php');
    exit;
}

// Fetch manager's projects
$stmt = $pdo->prepare("SELECT p.*, 
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as total_tasks,
                       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completed') as completed_tasks
                       FROM projects p 
                       WHERE p.manager_id = ? 
                       ORDER BY p.start_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate project statistics
$totalProjects = count($projects);
$activeProjects = count(array_filter($projects, function($project) {
    return $project['status'] === 'active';
}));
$completedProjects = count(array_filter($projects, function($project) {
    return $project['status'] === 'completed';
}));

// Fetch team members
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name 
                       FROM users u 
                       JOIN roles r ON u.role_id = r.id 
                       WHERE u.manager_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent activities
$stmt = $pdo->prepare("SELECT a.*, p.name as project_name, u.name as user_name 
                       FROM activities a 
                       JOIN projects p ON a.project_id = p.id 
                       JOIN users u ON a.user_id = u.id 
                       WHERE p.manager_id = ? 
                       ORDER BY a.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = "Project Manager Dashboard";
include '../layouts/header.php';
?>

<!-- Add required CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                            <h1 class="h3 mb-0 text-gray-800">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Project Manager') ?></h1>
                            <p class="text-muted">Here's an overview of your projects and team.</p>
                        </div>
                        <div class="date-badge">
                            <i class="fas fa-calendar-alt"></i>
                            <?= date('F d, Y') ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card primary">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Total Projects</h6>
                                    <h3 class="stat-card-value"><?= $totalProjects ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-project-diagram"></i>
                                        <span>Under your management</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card success">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Active Projects</h6>
                                    <h3 class="stat-card-value"><?= $activeProjects ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-spinner"></i>
                                        <span>Currently in progress</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Team Members</h6>
                                    <h3 class="stat-card-value"><?= count($teamMembers) ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-users"></i>
                                        <span>Working with you</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Progress Charts -->
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

                <!-- Projects Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">My Projects</h6>
                        <button class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Project
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Progress</th>
                                        <th>Tasks</th>
                                        <th>Team</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="project-icon">
                                                        <i class="fas fa-folder"></i>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h6 class="mb-0"><?= htmlspecialchars($project['name']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($project['description']) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
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
                                                <div class="task-stats">
                                                    <span class="completed-tasks"><?= $project['completed_tasks'] ?></span>
                                                    <span class="separator">/</span>
                                                    <span class="total-tasks"><?= $project['total_tasks'] ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="team-avatars">
                                                    <?php
                                                    $stmt = $pdo->prepare("SELECT u.name FROM users u 
                                                                         JOIN project_members pm ON u.id = pm.user_id 
                                                                         WHERE pm.project_id = ? LIMIT 3");
                                                    $stmt->execute([$project['id']]);
                                                    $team = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    foreach ($team as $member): ?>
                                                        <div class="avatar" title="<?= htmlspecialchars($member['name']) ?>">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php if (count($team) > 3): ?>
                                                        <div class="avatar more">+<?= count($team) - 3 ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch($project['status']) {
                                                    case 'active':
                                                        $statusClass = 'success';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'info';
                                                        break;
                                                    case 'on_hold':
                                                        $statusClass = 'warning';
                                                        break;
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-icon btn-info" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-success" title="View Details">
                                                        <i class="fas fa-eye"></i>
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

                <!-- Team Members -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Team Members</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($teamMembers as $member): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="team-member-card">
                                        <div class="member-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="member-info">
                                            <h6 class="mb-1"><?= htmlspecialchars($member['name']) ?></h6>
                                            <p class="text-muted mb-2"><?= htmlspecialchars($member['role_name']) ?></p>
                                            <div class="member-stats">
                                                <div class="stat">
                                                    <span class="stat-value">5</span>
                                                    <span class="stat-label">Tasks</span>
                                                </div>
                                                <div class="stat">
                                                    <span class="stat-value">80%</span>
                                                    <span class="stat-label">Completion</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold">Recent Activities</h6>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?= $activity['type'] === 'task' ? 'tasks' : 'comment' ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <h6 class="mb-1"><?= htmlspecialchars($activity['description']) ?></h6>
                                        <p class="text-muted mb-0">
                                            <small>
                                                <i class="fas fa-user mr-1"></i>
                                                <?= htmlspecialchars($activity['user_name']) ?> •
                                                <i class="fas fa-project-diagram mr-1"></i>
                                                <?= htmlspecialchars($activity['project_name']) ?> •
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= date('M d, Y H:i', strtotime($activity['created_at'])) ?>
                                            </small>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

/* Task Stats */
.task-stats {
    font-size: 0.875rem;
    font-weight: 600;
}

.completed-tasks {
    color: var(--success-color);
}

.separator {
    color: #858796;
    margin: 0 0.25rem;
}

.total-tasks {
    color: var(--text-color);
}

/* Team Avatars */
.team-avatars {
    display: flex;
    gap: 0.5rem;
}

.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #eaecf4;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-color);
    font-size: 0.875rem;
}

.avatar.more {
    background-color: var(--primary-color);
    color: white;
    font-size: 0.75rem;
}

/* Status Badge */
.status-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.info {
    background-color: rgba(78, 115, 223, 0.1);
    color: var(--primary-color);
}

.status-badge.warning {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
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

/* Team Member Card */
.team-member-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s;
}

.team-member-card:hover {
    transform: translateY(-2px);
}

.member-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: rgba(78, 115, 223, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
}

.member-info {
    flex: 1;
}

.member-stats {
    display: flex;
    gap: 1rem;
    margin-top: 0.5rem;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-weight: 600;
    color: var(--primary-color);
}

.stat-label {
    font-size: 0.75rem;
    color: #858796;
}

/* Activity Timeline */
.activity-timeline {
    position: relative;
    padding-left: 2rem;
}

.activity-item {
    position: relative;
    padding-bottom: 1.5rem;
    padding-left: 1.5rem;
    border-left: 2px solid #e3e6f0;
}

.activity-item:last-child {
    padding-bottom: 0;
}

.activity-icon {
    position: absolute;
    left: -1rem;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background-color: white;
    border: 2px solid var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.activity-content {
    background-color: white;
    padding: 1rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
}
</style>

<script>
// Project Progress Chart
const projectProgressCtx = document.getElementById('projectProgressChart').getContext('2d');
new Chart(projectProgressCtx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Project Progress',
            data: [30, 45, 60, 75, 85, 90],
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

// Project Status Chart
const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
new Chart(projectStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Completed', 'On Hold'],
        datasets: [{
            data: [<?= $activeProjects ?>, <?= $completedProjects ?>, <?= $totalProjects - $activeProjects - $completedProjects ?>],
            backgroundColor: [
                '#1cc88a',
                '#4e73df',
                '#f6c23e'
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
</script>

<?php include '../layouts/footer.php'; ?>

<?php
session_start();
require '../config/db_connection.php';

// Check if user is logged in and is member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'member') {
    header('Location: login.php');
    exit;
}

// Fetch user's assigned tasks
$stmt = $pdo->prepare("SELECT t.*, p.name as project_name 
                       FROM tasks t 
                       JOIN projects p ON t.project_id = p.id 
                       WHERE t.assigned_to = ? 
                       ORDER BY t.due_date ASC");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's recent activities
$stmt = $pdo->prepare("SELECT a.*, p.name as project_name 
                       FROM activities a 
                       JOIN projects p ON a.project_id = p.id 
                       WHERE a.user_id = ? 
                       ORDER BY a.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate task statistics
$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, function($task) {
    return $task['status'] === 'completed';
}));
$pendingTasks = $totalTasks - $completedTasks;
$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
?>

<?php
$pageTitle = "Member Dashboard";
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
                            <h1 class="h3 mb-0 text-gray-800">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Member') ?></h1>
                            <p class="text-muted">Here's an overview of your tasks and activities.</p>
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
                                    <h6 class="stat-card-title">Total Tasks</h6>
                                    <h3 class="stat-card-value"><?= $totalTasks ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-tasks"></i>
                                        <span>Assigned to you</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card success">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Completed Tasks</h6>
                                    <h3 class="stat-card-value"><?= $completedTasks ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Successfully completed</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card warning">
                            <div class="stat-card-content">
                                <div class="stat-card-info">
                                    <h6 class="stat-card-title">Pending Tasks</h6>
                                    <h3 class="stat-card-value"><?= $pendingTasks ?></h3>
                                    <p class="stat-card-desc">
                                        <i class="fas fa-clock"></i>
                                        <span>Awaiting completion</span>
                                    </p>
                                </div>
                                <div class="stat-card-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Task Progress Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Task Completion Progress</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="taskProgressChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Task Status</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="taskStatusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">My Tasks</h6>
                        <button class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Task
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Due Date</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="task-icon">
                                                        <i class="fas fa-tasks"></i>
                                                    </div>
                                                    <div class="ml-3">
                                                        <h6 class="mb-0"><?= htmlspecialchars($task['title']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($task['description']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="project-badge">
                                                    <i class="fas fa-project-diagram mr-2"></i>
                                                    <?= htmlspecialchars($task['project_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-calendar text-muted mr-2"></i>
                                                    <?= htmlspecialchars($task['due_date']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $priorityClass = '';
                                                switch($task['priority']) {
                                                    case 'high':
                                                        $priorityClass = 'danger';
                                                        break;
                                                    case 'medium':
                                                        $priorityClass = 'warning';
                                                        break;
                                                    case 'low':
                                                        $priorityClass = 'success';
                                                        break;
                                                }
                                                ?>
                                                <span class="priority-badge <?= $priorityClass ?>">
                                                    <?= ucfirst($task['priority']) ?>
                                                </span>
                                            </td>
                                            <td>
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
                                                }
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-icon btn-info" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-success" title="Complete">
                                                        <i class="fas fa-check"></i>
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

/* Task Icon */
.task-icon {
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

/* Project Badge */
.project-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    background-color: rgba(78, 115, 223, 0.1);
    color: var(--primary-color);
}

/* Priority Badge */
.priority-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 50rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-badge.danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.priority-badge.warning {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.priority-badge.success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
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
// Task Progress Chart
const taskProgressCtx = document.getElementById('taskProgressChart').getContext('2d');
new Chart(taskProgressCtx, {
    type: 'line',
    data: {
        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        datasets: [{
            label: 'Task Completion Rate',
            data: [30, 45, 60, <?= $completionRate ?>],
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

// Task Status Chart
const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
new Chart(taskStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'In Progress', 'Pending'],
        datasets: [{
            data: [<?= $completedTasks ?>, <?= $pendingTasks ?>, <?= $totalTasks - $completedTasks - $pendingTasks ?>],
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

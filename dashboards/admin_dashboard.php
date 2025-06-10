<?php
session_start();
require '../config/db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch total users count
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

// Fetch total projects count
$stmt = $pdo->query("SELECT COUNT(*) FROM projects");
$totalProjects = $stmt->fetchColumn();

// Fetch recent projects (limit 5)
$stmt = $pdo->query("SELECT p.*, u.name AS manager_name 
                     FROM projects p 
                     JOIN users u ON p.manager_id = u.id 
                     ORDER BY p.start_date DESC LIMIT 5");
$recentProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users list with roles
$stmt = $pdo->query("SELECT u.name, u.email, r.name AS role_name 
                     FROM users u 
                     JOIN roles r ON u.role_id = r.id
                     ORDER BY u.name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = "Admin Dashboard";
include '../layouts/header.php';
?>
<div class="row">
    <div class="col-3">
        <?php include '../layouts/sidebar.php'; ?>
    </div>
    <div class="col-9">
        <div class="container mt-4">
            <h1 class="mb-4">Dashboard Overview</h1>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <p class="card-text fs-2"><?= $totalUsers ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Projects</h5>
                            <p class="card-text fs-2"><?= $totalProjects ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <h2>Recent Projects</h2>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Manager</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Progress (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentProjects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['name']) ?></td>
                            <td><?= htmlspecialchars($project['manager_name']) ?></td>
                            <td><?= htmlspecialchars($project['start_date']) ?></td>
                            <td><?= htmlspecialchars($project['end_date']) ?></td>
                            <td><?= round($project['progress'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="mt-5">Users</h2>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    <?php include '../layouts/footer.php'; ?>
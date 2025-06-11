<?php
session_start();
require_once '../config/database.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}

// Handle delete specialty request
if (isset($_GET['delete_specialty_id'])) {
    $delete_id = (int) $_GET['delete_specialty_id'];
    $stmt = $pdo->prepare("DELETE FROM specialties WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: specialties.php");
    exit;
}

// Handle add specialty form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_specialty'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO specialties (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        header("Location: specialties.php");
        exit;
    } else {
        $error = "Veuillez remplir tous les champs requis.";
    }
}

// Fetch all specialties with member count
$stmt = $pdo->query("
    SELECT s.*, COUNT(u.id) as member_count 
    FROM specialties s 
    LEFT JOIN users u ON s.id = u.specialty_id 
    GROUP BY s.id 
    ORDER BY s.id ASC
");
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Gestion des Spécialités";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des spécialités</title>
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            vertical-align: middle;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .action-buttons .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        .specialty-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
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
                <h1 class="h3 mb-0">Gestion des spécialités</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecialtyModal">
                    <i class="bi bi-plus-lg"></i> Ajouter une spécialité
                </button>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Membres</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($specialties as $specialty): ?>
                            <tr>
                                <td><?= $specialty['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-code-square specialty-icon me-2"></i>
                                        <span class="badge bg-primary"><?= htmlspecialchars($specialty['name']) ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($specialty['description'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= $specialty['member_count'] ?></span>
                                </td>
                                <td class="action-buttons">
                                    <a href="?delete_specialty_id=<?= $specialty['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette spécialité ?');">
                                        <i class="bi bi-trash"></i>
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

    <!-- Add Specialty Modal -->
    <div class="modal fade" id="addSpecialtyModal" tabindex="-1" aria-labelledby="addSpecialtyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSpecialtyModalLabel">Ajouter une spécialité</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="specialties.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de la spécialité</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_specialty" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
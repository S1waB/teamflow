<?php
session_start();
require_once '../config/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Vérification de l'ID de la tâche
if (!isset($_GET['id'])) {
    header('Location: tasks.php');
    exit();
}

$taskId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Vérification des permissions
try {
    $checkPermissionsQuery = "SELECT t.assigned_to, p.manager_id 
                             FROM tasks t 
                             JOIN projects p ON t.project_id = p.id 
                             WHERE t.id = ?";
    $stmt = $pdo->prepare($checkPermissionsQuery);
    $stmt->execute([$taskId]);
    $taskData = $stmt->fetch(PDO::FETCH_ASSOC);

    $canComment = false;
    if ($taskData) {
        $canComment = ($userId == $taskData['assigned_to'] || $userId == $taskData['manager_id']);
    }
} catch (PDOException $e) {
    // En cas d'erreur, on définit canComment à false par défaut
    $canComment = false;
    error_log("Erreur de base de données : " . $e->getMessage());
}

// Traitement de l'ajout de commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if ($canComment) {
        try {
            $content = trim($_POST['content']);
            if (!empty($content)) {
                $insertCommentQuery = "INSERT INTO task_comments (task_id, user_id, content) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($insertCommentQuery);
                $stmt->execute([$taskId, $userId, $content]);
                
                // Redirection pour éviter la soumission multiple du formulaire
                header("Location: task_details.php?id=" . $taskId);
                exit();
            }
        } catch (PDOException $e) {
            error_log("Erreur lors de l'ajout du commentaire : " . $e->getMessage());
        }
    }
}

// Récupération des commentaires
try {
    $commentsQuery = "SELECT tc.*, u.name as author_name 
                     FROM task_comments tc 
                     JOIN users u ON tc.user_id = u.id 
                     WHERE tc.task_id = ? 
                     ORDER BY tc.created_at DESC";
    $stmt = $pdo->prepare($commentsQuery);
    $stmt->execute([$taskId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $comments = [];
    error_log("Erreur lors de la récupération des commentaires : " . $e->getMessage());
}

$pageTitle = "Détails de la Tâche";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la tâche</title>
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

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .task-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .task-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
        }

        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-todo {
            background-color: #e5e7eb;
            color: #374151;
        }

        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-finished {
            background-color: #dcfce7;
            color: #166534;
        }

        .task-description {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .task-comments {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
        }

        .comment {
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
        }

        .comment:last-child {
            border-bottom: none;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .comment-author {
            font-weight: 500;
            color: var(--text-color);
        }

        .comment-date {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .comment-content {
            color: #4b5563;
            white-space: pre-wrap;
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
                <h1 class="h3 mb-0">Détails de la tâche</h1>
                <a href="tasks.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <div id="taskDetails">
            <!-- Les détails de la tâche seront chargés ici dynamiquement -->
        </div>

        <div class="task-comments">
            <h3 class="h5 mb-4">Commentaires</h3>
            <div id="commentsList">
                <?php if (empty($comments)): ?>
                    <p class="text-muted">Aucun commentaire pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo htmlspecialchars($comment['author_name']); ?></span>
                                <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($canComment): ?>
            <div class="mt-4">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_comment">
                    <div class="mb-3">
                        <textarea class="form-control" name="content" rows="3" placeholder="Ajouter un commentaire..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Publier</button>
                </form>
            </div>
            <?php else: ?>
            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle"></i> Seuls le propriétaire de la tâche et le chef de projet peuvent ajouter des commentaires.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const config = {
            apiUrl: 'api/tasks.php',
            taskId: <?php echo $taskId; ?>,
            canComment: <?php echo $canComment ? 'true' : 'false'; ?>
        };

        // Fonctions utilitaires
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('fr-FR');
        }

        function showError(message) {
            alert(message);
        }

        // Chargement des détails de la tâche
        async function loadTaskDetails() {
            try {
                const response = await fetch(`${config.apiUrl}?id=${config.taskId}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des détails de la tâche');
                }
                
                renderTaskDetails(data.data);
            } catch (error) {
                showError(error.message);
            }
        }

        function renderTaskDetails(task) {
            const taskDetails = document.getElementById('taskDetails');
            // Vérification et normalisation du statut
            const taskStatus = (task.status || 'To-do').toLowerCase().replace(' ', '-');
            const statusText = task.status === 'To-do' ? 'À faire' : 
                             task.status === 'in progress' ? 'En cours' : 
                             task.status === 'finished' ? 'Terminée' : 'À faire';

            taskDetails.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="task-header">
                            <h2 class="task-title">${task.title || 'Sans titre'}</h2>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" onclick="editTask(${task.id})">
                                    <i class="bi bi-pencil"></i> Modifier
                                </button>
                                <button class="btn btn-danger" onclick="deleteTask(${task.id})">
                                    <i class="bi bi-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                        
                        <div class="task-meta">
                            <div class="meta-item">
                                <i class="bi bi-person"></i>
                                <span>Assigné à: ${task.assignee_name || 'Non assigné'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-circle"></i>
                                <span>Statut: 
                                    <span class="status-badge status-${taskStatus}">
                                        ${statusText}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="task-description">
                            <h3 class="h5 mb-3">Description</h3>
                            <p>${task.description || 'Aucune description disponible.'}</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="meta-item mb-2">
                                    <i class="bi bi-calendar"></i>
                                    <span>Date de début: ${task.start_date ? formatDate(task.start_date) : 'Non définie'}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="meta-item mb-2">
                                    <i class="bi bi-calendar-check"></i>
                                    <span>Date d'échéance: ${task.due_date ? formatDate(task.due_date) : 'Non définie'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Fonctions de gestion de la tâche
        function editTask(id) {
            window.location.href = `tasks.php?edit=${id}`;
        }

        async function deleteTask(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
                try {
                    const response = await fetch(config.apiUrl, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Erreur lors de la suppression de la tâche');
                    }
                    
                    window.location.href = 'tasks.php';
                } catch (error) {
                    showError(error.message);
                }
            }
        }

        // Chargement initial
        loadTaskDetails();
    </script>
</body>
</html> 
<?php
require '../config/db_connection.php';

// Initialize result array
$response = [
    'success' => false,
    'error' => '',
    'redirect' => '../pages/tasks_manager.php',
];

// Delete task
if (isset($_GET['delete_task_id'])) {
    $delete_id = (int) $_GET['delete_task_id'];
    
    // Récupérer le project_id de la tâche avant de la supprimer
    $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
    $stmt->execute([$delete_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            // Mettre à jour le progrès du projet après la suppression de la tâche
            updateProjectProgress($pdo, $task['project_id']);
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to delete task.";
        }
    } else {
        $response['error'] = "Task not found.";
    }
    
    header("Location: " . $response['redirect']);
    exit;
}

// Fonction pour mettre à jour le progrès du projet
function updateProjectProgress($pdo, $project_id) {
    // Calculer la moyenne du progrès des tâches
    $stmt = $pdo->prepare("
        SELECT AVG(progress) as avg_progress, COUNT(*) as total_tasks
        FROM tasks 
        WHERE project_id = ?
    ");
    $stmt->execute([$project_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mettre à jour le progrès du projet
    if ($result['total_tasks'] > 0) {
        $progress = round($result['avg_progress'], 2);
        $stmt = $pdo->prepare("UPDATE projects SET progress = ? WHERE id = ?");
        $stmt->execute([$progress, $project_id]);
    }
}

// Add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $project_id = (int) $_POST['project_id'];
    $assigned_to = $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
    $status = $_POST['status'] ?? 'To-do';
    $progress = (float) ($_POST['progress'] ?? 0);

    if ($title && $start_date && $due_date && $project_id) {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, status, progress, start_date, due_date, project_id, assigned_to) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $status, $progress, $start_date, $due_date, $project_id, $assigned_to])) {
            // Mettre à jour le progrès du projet après l'ajout d'une tâche
            updateProjectProgress($pdo, $project_id);
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to add task.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/tasks_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Edit task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id = (int) $_POST['task_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $due_date = $_POST['due_date'];
    $project_id = (int) $_POST['project_id'];
    $assigned_to = $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
    $status = $_POST['status'] ?? 'To-do';
    $progress = (float) ($_POST['progress'] ?? 0);

    if ($task_id && $title && $start_date && $due_date && $project_id) {
        $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, status = ?, progress = ?, 
                             start_date = ?, due_date = ?, project_id = ?, assigned_to = ? 
                             WHERE id = ?");
        if ($stmt->execute([$title, $description, $status, $progress, $start_date, $due_date, $project_id, $assigned_to, $task_id])) {
            // Mettre à jour le progrès du projet après la modification d'une tâche
            updateProjectProgress($pdo, $project_id);
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to update task.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/tasks_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Update task status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = (int) $_POST['task_id'];
    $status = $_POST['status'];
    $progress = (float) ($_POST['progress'] ?? 0);

    if ($task_id && $status) {
        // Récupérer le project_id de la tâche
        $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task) {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?");
            if ($stmt->execute([$status, $progress, $task_id])) {
                // Mettre à jour le progrès du projet après la mise à jour du statut
                updateProjectProgress($pdo, $task['project_id']);
                $response['success'] = true;
                $response['redirect'] = "../pages/tasks_manager.php?success=Status+mis+à+jour";
            } else {
                $response['error'] = "Failed to update task status.";
            }
        } else {
            $response['error'] = "Task not found.";
        }
    } else {
        $response['error'] = "Données manquantes.";
    }

    if ($response['error']) {
        header("Location: ../pages/tasks_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Gestion des commentaires
if (isset($_POST['add_comment'])) {
    // Vérifier si la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../pages/tasks_manager.php?error=Vous devez être connecté pour ajouter un commentaire");
        exit;
    }

    $task_id = (int)$_POST['task_id'];
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if (empty($comment)) {
        header("Location: ../pages/tasks_manager.php?error=Le commentaire ne peut pas être vide");
        exit;
    }

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
        $check_task->execute([$task_id]);
        if (!$check_task->fetch()) {
            throw new Exception("La tâche n'existe pas");
        }

        $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        if ($stmt->execute([$task_id, $user_id, $comment])) {
            header("Location: ../pages/tasks_manager.php?success=Commentaire ajouté avec succès");
        } else {
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de l'ajout du commentaire: " . $e->getMessage());
        header("Location: ../pages/tasks_manager.php?error=Erreur lors de l'ajout du commentaire: " . urlencode($e->getMessage()));
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout du commentaire: " . $e->getMessage());
        header("Location: ../pages/tasks_manager.php?error=" . urlencode($e->getMessage()));
    }
    exit;
}

// Récupération des commentaires (pour AJAX)
if (isset($_GET['get_comments'])) {
    $task_id = (int)$_GET['get_comments'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, u.name as user_name 
            FROM task_comments tc
            JOIN users u ON tc.user_id = u.id
            WHERE tc.task_id = ?
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute([$task_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les dates pour l'affichage
        foreach ($comments as &$comment) {
            $comment['created_at'] = date('d/m/Y H:i', strtotime($comment['created_at']));
        }
        
        header('Content-Type: application/json');
        echo json_encode($comments);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erreur lors de la récupération des commentaires']);
    }
    exit;
}

// Récupérer les membres d'un projet
if (isset($_GET['get_project_members'])) {
    $project_id = (int)$_GET['get_project_members'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name
            FROM users u
            JOIN project_members pm ON u.id = pm.member_id
            WHERE pm.project_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$project_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($members);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erreur lors de la récupération des membres du projet']);
    }
    exit;
}
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
    // Récupérer toutes les tâches du projet
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tasks,
               SUM(CASE WHEN status = 'finished' THEN 1 ELSE 0 END) as finished_tasks,
               SUM(progress) as total_progress
        FROM tasks 
        WHERE project_id = ?
    ");
    $stmt->execute([$project_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculer le progrès du projet
    if ($result['total_tasks'] > 0) {
        if ($result['finished_tasks'] == $result['total_tasks']) {
            // Si toutes les tâches sont terminées, le progrès est à 100%
            $progress = 100;
        } else {
            // Sinon, calculer la moyenne du progrès des tâches
            $progress = round($result['total_progress'] / $result['total_tasks'], 2);
        }
        
        // Mettre à jour le progrès du projet
        $stmt = $pdo->prepare("UPDATE projects SET progress = ? WHERE id = ?");
        $stmt->execute([$progress, $project_id]);
    }
}

// Fonction pour mettre à jour le progrès d'une tâche en fonction de son statut
function updateTaskProgress($pdo, $task_id, $status) {
    $stmt = $pdo->prepare("SELECT progress FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $current_progress = $stmt->fetchColumn();

    $new_progress = $current_progress;
    switch ($status) {
        case 'finished':
            $new_progress = 100;
            break;
        case 'in progress':
            if ($current_progress < 50) {
                $new_progress = 50;
            }
            break;
        case 'To-do':
            if ($current_progress > 0) {
                $new_progress = 0;
            }
            break;
    }

    if ($new_progress != $current_progress) {
        $stmt = $pdo->prepare("UPDATE tasks SET progress = ? WHERE id = ?");
        $stmt->execute([$new_progress, $task_id]);
    }

    return $new_progress;
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
        // Récupérer le progrès actuel
        $stmt = $pdo->prepare("SELECT progress FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $current_progress = $stmt->fetchColumn();

        // Empêcher la diminution du progrès
        if ($progress < $current_progress) {
            $progress = $current_progress;
        }

        // Mettre à jour le progrès en fonction du statut
        $progress = updateTaskProgress($pdo, $task_id, $status);

        $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, status = ?, progress = ?, 
                             start_date = ?, due_date = ?, project_id = ?, assigned_to = ? 
                             WHERE id = ?");
        if ($stmt->execute([$title, $description, $status, $progress, $start_date, $due_date, $project_id, $assigned_to, $task_id])) {
            // Mettre à jour le progrès du projet
            updateProjectProgress($pdo, $project_id);
            $response['success'] = true;
            $response['redirect'] = "../pages/tasks_manager.php?success=Tâche+mise+à+jour";
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
    try {
        // Log des données reçues
        error_log("Données POST reçues : " . print_r($_POST, true));

        // Validation des données
        if (!isset($_POST['task_id']) || !isset($_POST['status'])) {
            throw new Exception("Données manquantes : ID de la tâche et statut requis.");
        }

        $task_id = (int) $_POST['task_id'];
        $status = $_POST['status'];
        $progress = (float) ($_POST['progress'] ?? 0);

        // Log des valeurs après conversion
        error_log("task_id: " . $task_id . ", status: " . $status . ", progress: " . $progress);

        // Validation du statut
        $valid_statuses = ['To-do', 'in progress', 'finished'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception("Statut invalide.");
        }

        // Validation du progrès
        if ($progress < 0 || $progress > 100) {
            throw new Exception("Le progrès doit être entre 0 et 100.");
        }

        // Récupérer le project_id et le progrès actuel de la tâche
        $stmt = $pdo->prepare("SELECT project_id, progress FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log du résultat de la requête
        error_log("Résultat de la requête task : " . print_r($task, true));

        if (!$task) {
            throw new Exception("Tâche non trouvée (ID: " . $task_id . ").");
        }

        // Empêcher la diminution du progrès
        if ($progress < $task['progress']) {
            $progress = $task['progress'];
        }

        // Mettre à jour le statut et le progrès de la tâche
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?");
        if (!$stmt->execute([$status, $progress, $task_id])) {
            throw new Exception("Erreur lors de la mise à jour de la tâche.");
        }

        // Mettre à jour le progrès du projet
        updateProjectProgress($pdo, $task['project_id']);

        // Succès
        header("Location: ../pages/tasks_manager.php?success=Statut+mis+à+jour+avec+succès");
        exit;

    } catch (Exception $e) {
        error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
        header("Location: ../pages/tasks_manager.php?error=" . urlencode($e->getMessage()));
        exit;
    }
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
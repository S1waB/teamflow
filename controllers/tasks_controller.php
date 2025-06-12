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
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $response['success'] = true;
    } else {
        $response['error'] = "Failed to delete task.";
    }
    header("Location: " . $response['redirect']);
    exit;
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
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?");
        if ($stmt->execute([$status, $progress, $task_id])) {
            $response['success'] = true;
            $response['redirect'] = "../pages/tasks_manager.php?success=Status+mis+à+jour";
        } else {
            $response['error'] = "Failed to update task status.";
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
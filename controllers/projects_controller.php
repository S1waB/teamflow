<?php
// projects_controller.php
require '../config/db_connection.php';

// Initialize result array
$response = [
    'success' => false,
    'error' => '',
    'redirect' => '../pages/projects_manager.php',
];

// Delete project
if (isset($_GET['delete_project_id'])) {
    $delete_id = (int) $_GET['delete_project_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete related tasks
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE project_id = ?");
        $stmt->execute([$delete_id]);
        
        // Delete project members
        $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ?");
        $stmt->execute([$delete_id]);
        
        // Delete the project
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        $pdo->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['error'] = "Failed to delete project: " . $e->getMessage();
    }
    
    header("Location: " . $response['redirect']);
    exit;
}

// Add project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $manager_id = (int) $_POST['manager_id'];
    $progress = (float) $_POST['progress'];

    if ($name && $start_date && $end_date && $manager_id) {
        $stmt = $pdo->prepare("INSERT INTO projects (name, description, start_date, end_date, progress, manager_id) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $description, $start_date, $end_date, $progress, $manager_id])) {
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to add project.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/projects_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Edit project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
    $project_id = (int) $_POST['project_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $manager_id = (int) $_POST['manager_id'];
    $progress = (float) $_POST['progress'];

    if ($project_id && $name && $start_date && $end_date && $manager_id) {
        $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, start_date = ?, end_date = ?, progress = ?, manager_id = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $start_date, $end_date, $progress, $manager_id, $project_id])) {
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to update project.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/projects_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Add member to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $project_id = (int) $_POST['project_id'];
    $member_id = (int) $_POST['member_id'];

    if ($project_id && $member_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, member_id) VALUES (?, ?)");
            $stmt->execute([$project_id, $member_id]);
            $response['success'] = true;
            $response['redirect'] = "../pages/project_view.php?id=$project_id";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $response['error'] = "Ce membre est déjà dans ce projet.";
            } else {
                $response['error'] = "Failed to add member: " . $e->getMessage();
            }
        }
    } else {
        $response['error'] = "Données manquantes.";
    }

    if ($response['error']) {
        header("Location: ../pages/project_view.php?id=$project_id&error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Remove member from project
if (isset($_GET['remove_member'])) {
    $project_id = (int) $_GET['project_id'];
    $member_id = (int) $_GET['member_id'];

    if ($project_id && $member_id) {
        $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND member_id = ?");
        if ($stmt->execute([$project_id, $member_id])) {
            $response['success'] = true;
            $response['redirect'] = "../pages/project_view.php?id=$project_id";
        } else {
            $response['error'] = "Failed to remove member.";
        }
    } else {
        $response['error'] = "Données manquantes.";
    }

    if ($response['error']) {
        header("Location: ../pages/project_view.php?id=$project_id&error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}
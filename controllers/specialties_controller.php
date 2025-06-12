<?php
require '../config/db_connection.php';

// Initialize result array
$response = [
    'success' => false,
    'error' => '',
    'redirect' => '../pages/specialties_manager.php',
];

// Delete specialty
if (isset($_GET['delete_specialty_id'])) {
    $delete_id = (int) $_GET['delete_specialty_id'];
    
    // Check if any users are assigned to this specialty
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE specialty_id = ?");
    $stmt->execute([$delete_id]);
    $userCount = $stmt->fetchColumn();
    
    if ($userCount > 0) {
        $response['error'] = "Impossible de supprimer cette spécialité car elle est attribuée à des utilisateurs.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM specialties WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to delete specialty.";
        }
    }
    
    if ($response['error']) {
        header("Location: " . $response['redirect'] . "?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Add specialty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_specialty'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO specialties (name, description) VALUES (?, ?)");
        if ($stmt->execute([$name, $description])) {
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to add specialty.";
        }
    } else {
        $response['error'] = "Le nom de la spécialité est requis.";
    }

    if ($response['error']) {
        header("Location: " . $response['redirect'] . "?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Edit specialty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_specialty'])) {
    $specialty_id = (int) $_POST['specialty_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if ($specialty_id && $name) {
        $stmt = $pdo->prepare("UPDATE specialties SET name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $specialty_id])) {
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to update specialty.";
        }
    } else {
        $response['error'] = "Le nom de la spécialité est requis.";
    }

    if ($response['error']) {
        header("Location: " . $response['redirect'] . "?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}
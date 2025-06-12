<?php
require '../config/db_connection.php';

// Initialize result array
$response = [
    'success' => false,
    'error' => '',
    'redirect' => '../pages/users_manager.php',
];

// Function to handle file uploads
function handleFileUpload($file, $userId) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profile_pics/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . $userId . '_' . time() . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $filename;
        }
    }
    return null;
}

// Delete user
if (isset($_GET['delete_user_id'])) {
    $delete_id = (int) $_GET['delete_user_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $response['success'] = true;
    } else {
        $response['error'] = "Failed to delete user.";
    }
    header("Location: " . $response['redirect']);
    exit;
}

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role_id = (int) $_POST['role_id'];
    $specialty_id = $_POST['specialty_id'] !== '' ? (int) $_POST['specialty_id'] : null;
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : null;
    
    // Chef Projet role ID
    $chefProjetRoleId = 0;
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'chef_projet'");
    $role = $stmt->fetch();
    if ($role) {
        $chefProjetRoleId = $role['id'];
    }

    if ($name && $email && $password && $role_id) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user without profile pic initially
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id, specialty_id) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $hashed_password, $role_id, $specialty_id])) {
            $userId = $pdo->lastInsertId();
            
            // Handle file upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $profilePic = handleFileUpload($_FILES['profile_pic'], $userId);
                if ($profilePic) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$profilePic, $userId]);
                }
            }
            
            // Handle project assignment for Chef Projet
            if ($role_id == $chefProjetRoleId && $project_id) {
                // Update project manager
                $stmt = $pdo->prepare("UPDATE projects SET manager_id = ? WHERE id = ?");
                $stmt->execute([$userId, $project_id]);
            }
            
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to add user.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/users_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}

// Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int) $_POST['id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role_id = (int) $_POST['role_id'];
    $specialty_id = $_POST['specialty_id'] !== '' ? (int) $_POST['specialty_id'] : null;
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : null;
    
    // Chef Projet role ID
    $chefProjetRoleId = 0;
    $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'chef_projet'");
    $role = $stmt->fetch();
    if ($role) {
        $chefProjetRoleId = $role['id'];
    }

    if ($user_id && $name && $email && $role_id) {
        // Handle file upload
        $profilePic = null;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $profilePic = handleFileUpload($_FILES['profile_pic'], $user_id);
        }
        
        // Build SQL based on whether password and profile pic are updated
        $params = [$name, $email, $role_id, $specialty_id];
        $sql = "UPDATE users SET name = ?, email = ?, role_id = ?, specialty_id = ?";
        
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
        }
        
        if ($profilePic) {
            $sql .= ", profile_pic = ?";
            $params[] = $profilePic;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $user_id;
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
        
        if ($success) {
            // Handle project assignment for Chef Projet
            if ($role_id == $chefProjetRoleId && $project_id) {
                // Update project manager
                $stmt = $pdo->prepare("UPDATE projects SET manager_id = ? WHERE id = ?");
                $stmt->execute([$user_id, $project_id]);
            }
            
            $response['success'] = true;
        } else {
            $response['error'] = "Failed to update user.";
        }
    } else {
        $response['error'] = "Veuillez remplir tous les champs requis.";
    }

    if ($response['error']) {
        header("Location: ../pages/users_manager.php?error=" . urlencode($response['error']));
    } else {
        header("Location: " . $response['redirect']);
    }
    exit;
}
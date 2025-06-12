<?php
session_start();
require '../config/db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $specialty_id = $_POST['specialty_id'] !== '' ? (int)$_POST['specialty_id'] : null;
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    $error = '';
    if (empty($name)) {
        $error = "Le nom est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'email n'est pas valide.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    }
    
    if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Handle profile picture upload
            $profile_pic = $_SESSION['profile_pic'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/profile_pics/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExt = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $fileExt;
                $destination = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                    // Delete old profile pic if exists
                    if ($profile_pic && file_exists($uploadDir . $profile_pic)) {
                        unlink($uploadDir . $profile_pic);
                    }
                    $profile_pic = $filename;
                }
            }
            
            // Update user data
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, specialty_id = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $email, $hashed_password, $specialty_id, $profile_pic, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, specialty_id = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$name, $email, $specialty_id, $profile_pic, $user_id]);
            }
            
            $pdo->commit();
            
            // Update session data
            $_SESSION['user_name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['profile_pic'] = $profile_pic;
            
            header("Location: profile.php?success=Profil mis à jour avec succès");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
            header("Location: profile.php?error=" . urlencode($error));
            exit;
        }
    } else {
        header("Location: profile.php?error=" . urlencode($error));
        exit;
    }
}

// If not a POST request, redirect to profile page
header("Location: profile.php");
exit;
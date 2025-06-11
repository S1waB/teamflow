<?php
session_start();
require_once '../../config/database.php';

// Désactiver l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

// Définir le type de contenu
header('Content-Type: application/json');

// Fonction pour envoyer une réponse JSON
function sendJsonResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // Gérer les requêtes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérifier si c'est une requête de changement de mot de passe
        if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
            $currentPassword = $_POST['currentPassword'] ?? '';
            $newPassword = $_POST['newPassword'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                sendJsonResponse(false, 'Tous les champs sont requis');
            }

            // Vérifier l'ancien mot de passe
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!password_verify($currentPassword, $user['password'])) {
                sendJsonResponse(false, 'Mot de passe actuel incorrect');
            }

            // Mettre à jour le mot de passe
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

            sendJsonResponse(true, 'Mot de passe mis à jour avec succès');
        }
        // Vérifier si c'est une requête de mise à jour du profil
        else if (isset($_POST['name']) || isset($_POST['email'])) {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';

            if (empty($name) || empty($email)) {
                sendJsonResponse(false, 'Tous les champs sont requis');
            }

            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                sendJsonResponse(false, 'Cet email est déjà utilisé');
            }

            // Mettre à jour le profil
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);

            // Mettre à jour la session
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;

            sendJsonResponse(true, 'Profil mis à jour avec succès');
        }
        // Vérifier si c'est une requête de changement de photo
        else if (isset($_FILES['photo'])) {
            $file = $_FILES['photo'];
            
            // Vérifier les erreurs de téléchargement
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP',
                    UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
                    UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
                    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                    UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
                    UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement du fichier'
                ];
                $errorMessage = $errorMessages[$file['error']] ?? 'Erreur inconnue lors du téléchargement';
                sendJsonResponse(false, $errorMessage);
            }

            // Vérifier le type de fichier
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mimeType, $allowedTypes)) {
                sendJsonResponse(false, 'Type de fichier non autorisé (JPG, PNG, GIF uniquement)');
            }

            // Vérifier la taille du fichier (2MB max)
            if ($file['size'] > 2 * 1024 * 1024) {
                sendJsonResponse(false, 'Le fichier est trop volumineux (max 2MB)');
            }

            // Créer le dossier s'il n'existe pas
            $uploadDir = '../../uploads/profile_pics';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    sendJsonResponse(false, 'Impossible de créer le dossier de destination');
                }
            }

            // Vérifier les permissions du dossier
            if (!is_writable($uploadDir)) {
                sendJsonResponse(false, 'Le dossier de destination n\'est pas accessible en écriture');
            }

            // Générer un nom de fichier unique
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('profile_') . '.' . $extension;
            $uploadPath = $uploadDir . '/' . $filename;

            // Supprimer l'ancienne photo si elle existe
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $oldPhoto = $stmt->fetchColumn();

            if ($oldPhoto && file_exists($uploadDir . '/' . $oldPhoto)) {
                unlink($uploadDir . '/' . $oldPhoto);
            }

            // Déplacer le fichier
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                sendJsonResponse(false, 'Erreur lors de l\'enregistrement du fichier');
            }

            // Mettre à jour le profil
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$filename, $_SESSION['user_id']]);

            sendJsonResponse(true, 'Photo de profil mise à jour avec succès', [
                'filename' => $filename,
                'url' => '/uploads/profile_pics/' . $filename
            ]);
        }
        else {
            sendJsonResponse(false, 'Requête invalide');
        }
    }
    else {
        sendJsonResponse(false, 'Méthode non autorisée');
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Une erreur est survenue: ' . $e->getMessage());
} 
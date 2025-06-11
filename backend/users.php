<?php
ob_start(); // Start output buffering
// Désactiver l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Vérification de l'authentification
if (!isAuthenticated()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

// Configuration de l'en-tête pour JSON
header('Content-Type: application/json');

// Fonction pour gérer les erreurs et renvoyer une réponse JSON
function sendErrorResponse($message) {
    ob_end_clean(); // Clean any previous output before sending JSON
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// Fonction pour envoyer une réponse JSON de succès
function sendSuccessResponse($data) {
    ob_end_clean(); // Clean any previous output before sending JSON
    echo json_encode(['success' => true, 'data' => $data]);
    exit();
}

try {
    // Seuls les utilisateurs avec les rôles 'admin' ou 'chef_projet' peuvent voir tous les utilisateurs.
    // Les 'membre' peuvent voir les utilisateurs pour assignation si nécessaire.
    // Pour le but de cette liste d'assignation, on va autoriser tous les rôles authentifiés.
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

        if ($projectId) {
            // Récupérer les utilisateurs membres d'un projet spécifique
            $query = "SELECT u.id, u.name 
                      FROM users u 
                      JOIN project_members pm ON u.id = pm.member_id 
                      WHERE pm.project_id = ? 
                      ORDER BY u.name ASC";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$projectId]);
        } else {
            // Récupérer tous les utilisateurs (pour la liste d'assignation générale si aucun projet n'est sélectionné)
            $query = "SELECT id, name FROM users ORDER BY name ASC";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendSuccessResponse($users);
    } else {
        sendErrorResponse('Méthode non autorisée');
    }

} catch (PDOException $e) {
    sendErrorResponse('Erreur de base de données : ' . $e->getMessage());
} catch (Exception $e) {
    sendErrorResponse('Erreur inattendue : ' . $e->getMessage());
}
?>

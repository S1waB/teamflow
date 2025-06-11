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

// Vérification du rôle
if (!in_array($_SESSION['role'], ['admin', 'chef_projet', 'membre'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
    exit();
}

// Configuration de l'en-tête pour JSON
header('Content-Type: application/json');

// Fonction pour gérer les erreurs
function handleError($message) {
    ob_end_clean(); // Clean any previous output before sending JSON
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

// Fonction pour formater la réponse
function sendResponse($data, $pagination = null) {
    ob_end_clean(); // Clean any previous output before sending JSON
    $response = ['success' => true, 'data' => $data];
    if ($pagination) {
        $response['pagination'] = $pagination;
    }
    echo json_encode($response);
    exit();
}

// Gestion des requêtes
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Récupération des projets avec pagination et recherche
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $offset = ($page - 1) * $limit;

        // Construction de la requête
        $where = '';
        $params = [];
        if ($search) {
            $where = "WHERE p.name LIKE ? OR p.description LIKE ?";
            $params = ["%$search%", "%$search%"];
        }

        // Compte total des projets
        $countQuery = "SELECT COUNT(*) FROM projects p $where";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        $totalPages = ceil($total / $limit);

        // Récupération des projets
        $query = "SELECT p.*, u.name as manager_name 
                 FROM projects p 
                 LEFT JOIN users u ON p.manager_id = u.id 
                 $where 
                 ORDER BY p.id DESC 
                 LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Envoi de la réponse
        sendResponse($projects, [
            'page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'items_per_page' => $limit
        ]);
        break;

    case 'POST':
        // Création ou mise à jour d'un projet
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            handleError('Données invalides');
        }

        // Validation des données
        if (empty($data['name']) || empty($data['start_date']) || empty($data['end_date'])) {
            handleError('Tous les champs obligatoires doivent être remplis');
        }

        try {
            if (isset($data['id'])) {
                // Mise à jour
                $stmt = $pdo->prepare("UPDATE projects SET 
                    name = ?, 
                    description = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    progress = ?, 
                    manager_id = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null,
                    $data['start_date'],
                    $data['end_date'],
                    $data['progress'] ?? 0,
                    $data['manager_id'],
                    $data['id']
                ]);
                $message = 'Projet mis à jour avec succès';
            } else {
                // Création
                $stmt = $pdo->prepare("INSERT INTO projects 
                    (name, description, start_date, end_date, progress, manager_id) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null,
                    $data['start_date'],
                    $data['end_date'],
                    $data['progress'] ?? 0,
                    $data['manager_id']
                ]);
                $message = 'Projet créé avec succès';
            }
            
            sendResponse(['message' => $message]);
        } catch (PDOException $e) {
            handleError('Erreur lors de l\'enregistrement du projet');
        }
        break;

    case 'DELETE':
        // Suppression d'un projet
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            handleError('ID du projet manquant');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$data['id']]);
            sendResponse(['message' => 'Projet supprimé avec succès']);
        } catch (PDOException $e) {
            handleError('Erreur lors de la suppression du projet');
        }
        break;

    default:
        handleError('Méthode non autorisée');
} 
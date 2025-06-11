<?php
ob_start(); // Start output buffering
// Désactiver l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);

// Démarrer la session
session_start();

// Configuration de l'en-tête pour JSON
header('Content-Type: application/json');

// Fonction pour envoyer une réponse JSON
function sendJsonResponse($success, $data = null, $error = null) {
    ob_end_clean(); // Clean any previous output before sending JSON
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit();
}

try {
    // Vérification de l'authentification
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, null, 'Non autorisé');
    }

    // Inclusion de la base de données
    require_once '../../config/database.php';

    // Gestion des requêtes GET (liste des tâches)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $where = [];
        $params = [];

        // Filtres
        if (!empty($_GET['project'])) {
            $where[] = "t.project_id = ?";
            $params[] = $_GET['project'];
        }
        if (!empty($_GET['status'])) {
            $where[] = "t.status = ?";
            $params[] = $_GET['status'];
        }

        // Requête de base
        $sql = "SELECT t.*, p.name as project_name, u.name as assignee_name 
                FROM tasks t 
                LEFT JOIN projects p ON t.project_id = p.id 
                LEFT JOIN users u ON t.assigned_to = u.id";

        // Ajout des conditions WHERE si des filtres sont présents
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY t.due_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse(true, $tasks);
    }

    // Gestion des requêtes POST (création/modification de tâche)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            sendJsonResponse(false, null, 'Données invalides');
        }

        // Validation des données requises
        if (empty($data['title']) || empty($data['project_id']) || empty($data['start_date']) || empty($data['due_date'])) {
            sendJsonResponse(false, null, 'Tous les champs obligatoires doivent être remplis');
        }

        // Si c'est une modification
        if (!empty($data['id'])) {
            $sql = "UPDATE tasks SET 
                    title = ?, 
                    description = ?, 
                    project_id = ?, 
                    assigned_to = ?, 
                    start_date = ?, 
                    due_date = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['project_id'],
                $data['assigned_to'] ?? null,
                $data['start_date'],
                $data['due_date'],
                $data['id']
            ]);

            sendJsonResponse(true, null, 'Tâche mise à jour avec succès');
        }
        // Si c'est une création
        else {
            $sql = "INSERT INTO tasks (title, description, project_id, assigned_to, start_date, due_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'To-do')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['project_id'],
                $data['assigned_to'] ?? null,
                $data['start_date'],
                $data['due_date']
            ]);

            sendJsonResponse(true, null, 'Tâche créée avec succès');
        }
    }

    // Gestion des requêtes DELETE (suppression de tâche)
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id'])) {
            sendJsonResponse(false, null, 'ID de tâche manquant');
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$data['id']]);

        sendJsonResponse(true, null, 'Tâche supprimée avec succès');
    }

} catch (PDOException $e) {
    sendJsonResponse(false, null, 'Erreur de base de données: ' . $e->getMessage());
} catch (Exception $e) {
    sendJsonResponse(false, null, 'Erreur: ' . $e->getMessage());
} 
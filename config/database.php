<?php
ob_start(); // Start output buffering
// Désactiver l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);

// Paramètres de connexion à la base de données
$host = 'localhost';
$dbname = 'teamflow';
$username = 'root';
$password = '';

try {
    // Création de la connexion PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configuration des attributs PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch(PDOException $e) {
    // En cas d'erreur, envoyer une réponse JSON d'erreur et arrêter l'exécution
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de connexion à la base de données : ' . $e->getMessage()
    ]);
    exit();
} 
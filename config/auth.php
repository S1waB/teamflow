<?php
// Désactiver l'affichage des erreurs PHP
error_reporting(0);
ini_set('display_errors', 0);
/**
 * Fonctions d'authentification pour l'application
 */

/**
 * Vérifie si l'utilisateur est authentifié
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * @param string|array $roles Rôle(s) à vérifier
 * @return bool
 */
function hasRole($roles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return $_SESSION['role'] === $roles;
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Vérifie si l'utilisateur est chef de projet
 * @return bool
 */
function isProjectManager() {
    return hasRole(['admin', 'chef_projet']);
}

/**
 * Redirige vers la page de connexion si l'utilisateur n'est pas authentifié
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit();
    }
}

/**
 * Redirige vers la page d'accueil si l'utilisateur n'a pas le rôle requis
 * @param string|array $roles Rôle(s) requis
 */
function requireRole($roles) {
    requireAuth();
    
    if (!hasRole($roles)) {
        header('Location: /index.php');
        exit();
    }
}

/**
 * Récupère l'ID de l'utilisateur connecté
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Récupère le rôle de l'utilisateur connecté
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Récupère le nom de l'utilisateur connecté
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['name'] ?? null;
}

/**
 * Définit les informations de session de l'utilisateur
 * @param array $userData Données de l'utilisateur
 */
function setUserSession($userData) {
    $_SESSION['user_id'] = $userData['id'];
    $_SESSION['role'] = $userData['role'];
    $_SESSION['name'] = $userData['name'];
    $_SESSION['email'] = $userData['email'];
}

/**
 * Déconnecte l'utilisateur
 */
function logout() {
    session_unset();
    session_destroy();
    header('Location: /login.php');
    exit();
} 
<?php
// sidebar.php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['role'])) {
    // Rediriger vers la page de connexion si non connecté
    header('Location: /login.php');
    exit;
}

$role = $_SESSION['role'] ?? 'membre'; // Rôle par défaut si non défini
$profilePic = $_SESSION['profile_pic'] ?? 'default-avatar.png';
$profilePicPath = '/uploads/profile_pics/' . $profilePic;

// Debug temporaire pour vérifier le chemin et le nom du fichier image de profil
echo '<!-- Debug: ' . htmlspecialchars($profilePicPath) . ' -->';
echo '<!-- Debug: ' . htmlspecialchars($profilePic) . ' -->';

$menus = [
    'admin' => [
        ['label' => 'Accueil', 'link' => '../dashboards/admin_dashboard.php'],
        ['label' => 'Projects', 'link' => '../project/projects.php'],
        ['label' => 'Users', 'link' => '../backend/users_manager.php'],
        ['label' => 'Roles', 'link' => '../menus/roles.php'],
        ['label' => 'Spécialités', 'link' => '../menus/specialties.php'],
        ['label' => 'Tasks', 'link' => '../menus/tasks.php'],
        ['label' => 'Profile', 'link' => '../menus/profile.php'],
        ['label' => 'Logout', 'link' => '../logout.php'],
    ],
    'chef_projet' => [
        ['label' => 'Accueil', 'link' => '/dashboards/chef_accueil.php'],
        ['label' => 'Projects', 'link' => '/dashboards/projects.php'],
        ['label' => 'Profile', 'link' => '/dashboards/profile.php'],
        ['label' => 'Logout', 'link' => '../logout.php'],
    ],
    'membre' => [
        ['label' => 'Accueil', 'link' => '/pages/member_accueil.php'],
        ['label' => 'Profile', 'link' => 'profile.php'],
        ['label' => 'Tasks', 'link' => '/pages/tasks.php'],
        ['label' => 'Projects', 'link' => '/pages/projects.php'],
        ['label' => 'Logout', 'link' => '../logout.php'],
    ],
];

// Vérifier si le rôle existe dans les menus
if (!isset($menus[$role])) {
    $role = 'membre'; // Rôle par défaut si le rôle n'existe pas
}

$icons = [
    'Accueil' => '<i class="bi bi-house-door"></i>',
    'Projects' => '<i class="bi bi-folder"></i>',
    'Users' => '<i class="bi bi-people"></i>',
    'Roles' => '<i class="bi bi-shield-lock"></i>',
    'Spécialités' => '<i class="bi bi-star"></i>',
    'Tasks' => '<i class="bi bi-check2-square"></i>',
    'Profile' => '<i class="bi bi-person-circle"></i>',
    'Logout' => '<i class="bi bi-box-arrow-right"></i>',
];

// Séparer l'élément logout
$logoutItem = null;
if (isset($menus[$role])) {
    foreach ($menus[$role] as $key => $menuItem) {
        if ($menuItem['label'] === 'Logout') {
            $logoutItem = $menuItem;
            unset($menus[$role][$key]);
            break;
        }
    }
}
?>

<nav class="sidebar d-flex flex-column">
    <!-- Profile Section on top -->
    <div class="profile-section p-4 text-center">
        <div class="profile-pic-wrapper mb-3">
            <div class="profile-pic-inner">
                <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile Picture" class="profile-pic"
                     onerror="this.onerror=null;this.src='/uploads/profile_pics/default-avatar.png';" />
            </div>
            <div class="status-indicator online"></div>
        </div>
        <div class="profile-info">
            <div class="profile-name fw-bold"><?= htmlspecialchars($_SESSION['name'] ?? 'Utilisateur') ?></div>
            <div class="profile-role">
                <span class="role-badge"><?= ucfirst(htmlspecialchars($role)) ?></span>
            </div>
        </div>
    </div>

    <!-- Menu Items -->
    <div class="menu-container">
        <ul class="nav flex-column flex-grow-1 p-3">
            <?php foreach ($menus[$role] as $menuItem): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center gap-3" href="<?= htmlspecialchars($menuItem['link']) ?>">
                        <span class="icon-wrapper"><?= $icons[$menuItem['label']] ?? '' ?></span>
                        <span class="menu-label"><?= htmlspecialchars($menuItem['label']) ?></span>
                        <span class="hover-effect"></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Logout at bottom -->
    <?php if ($logoutItem): ?>
        <div class="logout-section p-3">
            <a class="nav-link d-flex align-items-center gap-3" href="<?= htmlspecialchars($logoutItem['link']) ?>">
                <span class="icon-wrapper"><?= $icons['Logout'] ?></span>
                <span class="menu-label"><?= htmlspecialchars($logoutItem['label']) ?></span>
                <span class="hover-effect"></span>
            </a>
        </div>
    <?php endif; ?>
</nav>


<style>
    :root {
        --primary-color: #2563eb;
        --secondary-color: #1e40af;
        --text-color: #1f2937;
        --hover-color: #dbeafe;
        --sidebar-width: 280px;
        --transition-speed: 0.3s;
    }

    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: #ffffff;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.08);
        transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
    }

    .profile-section {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 2rem 1rem;
        position: relative;
        overflow: hidden;
    }

    .profile-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        z-index: 1;
    }

    .profile-pic-wrapper {
        position: relative;
        width: 90px;
        height: 90px;
        margin: 0 auto;
        z-index: 2;
    }

    .profile-pic-inner {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        padding: 3px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(5px);
        transition: transform 0.3s ease;
    }

    .profile-pic-wrapper:hover .profile-pic-inner {
        transform: scale(1.05);
    }

    .profile-pic {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.8);
    }

    .status-indicator {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid white;
    }

    .status-indicator.online {
        background: #22c55e;
    }

    .profile-info {
        margin-top: 1rem;
        z-index: 2;
    }

    .profile-name {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        font-size: 0.85rem;
        backdrop-filter: blur(5px);
    }

    .menu-container {
        flex: 1;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary-color) transparent;
    }

    .menu-container::-webkit-scrollbar {
        width: 6px;
    }

    .menu-container::-webkit-scrollbar-track {
        background: transparent;
    }

    .menu-container::-webkit-scrollbar-thumb {
        background-color: var(--primary-color);
        border-radius: 3px;
    }

    .nav-link {
        color: var(--text-color);
        padding: 0.9rem 1.2rem;
        border-radius: 12px;
        transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        font-weight: 500;
    }

    .nav-link:hover {
        background: var(--hover-color);
        color: var(--primary-color);
        transform: translateX(5px);
    }

    .hover-effect {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transform: translateX(-100%);
        transition: transform 0.6s ease;
    }

    .nav-link:hover .hover-effect {
        transform: translateX(100%);
    }

    .icon-wrapper {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .nav-link:hover .icon-wrapper {
        transform: scale(1.1);
    }

    .menu-label {
        font-size: 0.95rem;
        transition: transform 0.3s ease;
    }

    .nav-link:hover .menu-label {
        transform: translateX(5px);
    }

    .logout-section {
        border-top: 1px solid rgba(0,0,0,0.05);
        background: #f8fafc;
    }

    .logout-section .nav-link {
        color: #ef4444;
    }

    .logout-section .nav-link:hover {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Active state for current page */
    .nav-link.active {
        background: var(--primary-color);
        color: white;
    }

    .nav-link.active:hover {
        background: var(--secondary-color);
        color: white;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }
    }
</style>
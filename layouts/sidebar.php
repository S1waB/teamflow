<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$role = $_SESSION['role'];
$name = $_SESSION['name'];
$profilePic = $_SESSION['profile_pic'] ?? 'default-avatar.png';

$menus = [
    'admin' => [
        ['label' => 'Accueil', 'link' => '../pages/dashboard.php'],
        ['label' => 'Projects', 'link' => '../pages/projects_manager.php'],
        ['label' => 'Users', 'link' => '../pages/users_manager.php'],
        ['label' => 'Spécialités', 'link' => '../pages/specialties_manager.php'],
        ['label' => 'Tasks', 'link' => '../pages/tasks_manager.php'],
        ['label' => 'Profile', 'link' => '../pages/profile.php'],
        ['label' => 'Logout', 'link' => 'logout.php'],
    ],
    'chef_projet' => [
        ['label' => 'Accueil', 'link' => '../pages/dashboard.php'],
        ['label' => 'Projects', 'link' => '../pages/projects_manager.php'],
        ['label' => 'Profile', 'link' => '../pages/profile.php'],
        ['label' => 'Logout', 'link' => '../pages/logout.php'],
    ],
    'membre' => [
        ['label' => 'Accueil', 'link' => '../pages/dashboard.php'],
        ['label' => 'Profile', 'link' => '../pages/profile.php'],
        ['label' => 'Tasks', 'link' => '../pages/tasks_manager.php'],
        ['label' => 'Projects', 'link' => '../pages/projects_manager.php'],
        ['label' => 'Logout', 'link' => '../pages/logout.php'],
    ],
];

if (!isset($menus[$role])) {
    $role = 'membre';
}

$icons = [
    'Accueil' => '<i class="bi bi-house-door"></i>',
    'Projects' => '<i class="bi bi-folder"></i>',
    'Users' => '<i class="bi bi-people"></i>',
    'Spécialités' => '<i class="bi bi-star"></i>',
    'Tasks' => '<i class="bi bi-check2-square"></i>',
    'Profile' => '<i class="bi bi-person-circle"></i>',
    'Logout' => '<i class="bi bi-box-arrow-right"></i>',
];

$logoutItem = null;
foreach ($menus[$role] as $key => $menuItem) {
    if ($menuItem['label'] === 'Logout') {
        $logoutItem = $menuItem;
        unset($menus[$role][$key]);
        break;
    }
}
?>

<nav class="sidebar d-flex flex-column">
    <div class="profile-section p-4 text-center">
        <div class="profile-pic-wrapper mb-3">
            <div class="profile-pic-inner">
                <img src="../uploads/profile_pics/.<?= htmlspecialchars($profilePicPath) ?>"
                    alt="Profile Picture"
                    class="profile-pic"
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

    <div class="menu-container">
        <ul class="nav flex-column flex-grow-1 p-3">
            <?php foreach ($menus[$role] as $menuItem): ?>
                <li class="nav-item mb-2">
                    <a class="nav-link d-flex align-items-center gap-3"
                        href="<?= htmlspecialchars($menuItem['link']) ?>">
                        <span class="icon-wrapper"><?= $icons[$menuItem['label']] ?? '' ?></span>
                        <span class="menu-label"><?= htmlspecialchars($menuItem['label']) ?></span>
                        <span class="hover-effect"></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($logoutItem): ?>
        <div class="logout-section p-3 mt-auto">
            <a class="nav-link d-flex align-items-center gap-3"
                href="<?= htmlspecialchars($logoutItem['link']) ?>">
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
        background: #fff;
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.08);
        z-index: 1000;
        display: flex;
        flex-direction: column;
    }

    .profile-section {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 2rem 1rem;
        position: relative;
    }

    .profile-section::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        z-index: 1;
    }

    .profile-pic-wrapper {
        position: relative;
        width: 90px;
        height: 90px;
        margin: auto;
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
        background: #22c55e;
        border: 2px solid white;
    }

    .profile-info {
        margin-top: 1rem;
        z-index: 2;
    }

    .profile-name {
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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

    .menu-container::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 3px;
    }

    .nav-link {
        color: var(--text-color);
        padding: 0.9rem 1.2rem;
        border-radius: 12px;
        transition: all var(--transition-speed);
        font-weight: 500;
        position: relative;
    }

    .nav-link:hover {
        background: var(--hover-color);
        color: var(--primary-color);
        transform: translateX(5px);
    }

    .hover-effect {
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
        transform: scale(1.2);
    }
</style>
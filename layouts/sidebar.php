<?php
// sidebar.php
// Assume session is already started and user role is stored in $_SESSION['role']

$role = $_SESSION['role'] ?? '';

$menus = [
    'admin' => [
        ['label' => 'Accueil', 'link' => '/dashboards/admin_dashboard.php'],
        ['label' => 'Projects', 'link' => '/dashboards/projects.php'],
        ['label' => 'Users', 'link' => '../backend/users_manager.php'],
        ['label' => 'Roles', 'link' => '/dashboards/roles.php'],
        ['label' => 'Spécialités', 'link' => '/dashboards/specialties.php'],
        ['label' => 'Tasks', 'link' => '/dashboards/tasks.php'],
        ['label' => 'Profile', 'link' => '/dashboards/profile.php'],
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

// If role is unknown, show minimal menu (optional)
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

// Separate logout from other menu items
$logoutItem = null;
foreach ($menus[$role] as $key => $menuItem) {
    if ($menuItem['label'] === 'Logout') {
        $logoutItem = $menuItem;
        unset($menus[$role][$key]);
        break;
    }
}
?>


<nav class="sidebar bg-light d-flex flex-column" style="height: 100vh; width: 250px;">
    <!-- Profile Section on top -->
    <div class="profile p-3 text-center border-bottom">
        <img src="<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'default-avatar.png') ?>" alt="Profile Picture" />
        <div class="profile-name fw-bold"><?= htmlspecialchars($_SESSION['name']) ?></div>
    </div>

    <!-- Menu Items -->
    <ul class="nav flex-column flex-grow-1 p-3">
        <?php foreach ($menus[$role] as $menuItem): ?>
            <li class="nav-item mb-2">
                <a class="nav-link d-flex align-items-center gap-2" href="<?= htmlspecialchars($menuItem['link']) ?>">
                    <?= $icons[$menuItem['label']] ?? '' ?>
                    <span><?= htmlspecialchars($menuItem['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Logout at bottom -->
    <?php if ($logoutItem): ?>
        <div class="p-3 border-top">
            <a class="nav-link d-flex align-items-center gap-2 text-danger" href="<?= htmlspecialchars($logoutItem['link']) ?>">
                <?= $icons['Logout'] ?>
                <span><?= htmlspecialchars($logoutItem['label']) ?></span>
            </a>
        </div>
    <?php endif; ?>
</nav>

<style>
    /* Simple sidebar styles */
    .sidebar {
        width: 220px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        border-right: 1px solid #ddd;
    }

    .nav-link {
        color: #333;
        font-weight: 500;
    }

    .nav-link:hover {
        background-color: rgb(10, 51, 95);
        color: white;
        border-radius: 4px;
    }
</style>
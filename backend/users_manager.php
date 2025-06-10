<?php
require '../config/db_connection.php';

// Handle delete user request
if (isset($_GET['delete_user_id'])) {
    $delete_id = (int) $_GET['delete_user_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: users_manager.php");
    exit;
}

// Handle add user form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']); // In real app, hash this!
    $role_id = (int) $_POST['role_id'];
    $specialty_id = $_POST['specialty_id'] !== '' ? (int) $_POST['specialty_id'] : null;

    // Simple validation (expand as needed)
    if ($name && $email && $password && $role_id) {
        // Hash password (use password_hash in real app)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id, specialty_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $role_id, $specialty_id]);
        header("Location: users_manager.php");
        exit;
    } else {
        $error = "Please fill all required fields.";
    }
}

// Fetch all users with role and specialty
$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, r.name AS role_name, s.name AS specialty_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN specialties s ON u.specialty_id = s.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch roles and specialties for form selects
$roles = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$specialties = $pdo->query("SELECT id, name FROM specialties ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Gestion des utilisateurs</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #eee; }
        form { margin-top: 20px; }
        input, select { padding: 5px; margin: 5px 0; width: 100%; max-width: 300px; }
        .error { color: red; }
        a.delete { color: red; text-decoration: none; }
    </style>
</head>
<body>
    <h1>Gestion des utilisateurs</h1>

    <?php if (!empty($error)): ?>
        <p class="error"><?=htmlspecialchars($error)?></p>
    <?php endif; ?>

    <h2>Liste des utilisateurs</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Spécialité</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['role_name']) ?></td>
                <td><?= htmlspecialchars($user['specialty_name'] ?? '-') ?></td>
                <td>
                    <a class="delete" href="?delete_user_id=<?= $user['id'] ?>" onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</a>
                    <!-- You can add Edit link here later -->
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Ajouter un utilisateur</h2>
    <form method="post" action="users_manager.php">
        <label>Nom : <input type="text" name="name" required></label><br>
        <label>Email : <input type="email" name="email" required></label><br>
        <label>Mot de passe : <input type="password" name="password" required></label><br>
        <label>Rôle :
            <select name="role_id" required>
                <option value="">-- Choisir un rôle --</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <label>Spécialité (optionnelle) :
            <select name="specialty_id">
                <option value="">-- Aucune --</option>
                <?php foreach ($specialties as $spec): ?>
                    <option value="<?= $spec['id'] ?>"><?= htmlspecialchars($spec['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label><br>
        <button type="submit" name="add_user">Ajouter</button>
    </form>
</body>
</html>

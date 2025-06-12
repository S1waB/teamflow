<?php
require '../config/db_connection.php';

// Fetch all specialties
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM specialties WHERE 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get error if redirected with error message
$error = isset($_GET['error']) ? $_GET['error'] : '';

$pageTitle = "Gestion des spécialités";
include '../layouts/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-2">
            <?php include '../layouts/sidebar.php'; ?>
        </div>

        <div class="col-lg-10">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="bi bi-tags me-2"></i>Gestion des spécialités</h3>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSpecialtyModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter une spécialité
                    </button>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-8">
                            <label for="search" class="form-label">Rechercher</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="search" name="search" class="form-control"
                                       placeholder="Nom ou description" value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel-fill"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Specialties Table -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-table"></i> Liste des spécialités</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($specialties as $specialty): ?>
                                <tr>
                                    <td><?= $specialty['id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($specialty['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($specialty['description'] ?? 'Aucune description') ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-specialty-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editSpecialtyModal"
                                                data-id="<?= $specialty['id'] ?>"
                                                data-name="<?= htmlspecialchars($specialty['name']) ?>"
                                                data-description="<?= htmlspecialchars($specialty['description'] ?? '') ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="../controllers/specialties_controller.php?delete_specialty_id=<?= $specialty['id'] ?>"
                                           onclick="return confirm('Supprimer cette spécialité ?');"
                                           class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Specialty Modal -->
<div class="modal fade" id="addSpecialtyModal" tabindex="-1" aria-labelledby="addSpecialtyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/specialties_controller.php" method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addSpecialtyModalLabel">
                        <i class="bi bi-plus-circle"></i> Ajouter une nouvelle spécialité
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nameAdd" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nameAdd" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="descriptionAdd" class="form-label">Description</label>
                        <textarea class="form-control" id="descriptionAdd" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="add_specialty" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Specialty Modal -->
<div class="modal fade" id="editSpecialtyModal" tabindex="-1" aria-labelledby="editSpecialtyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="../controllers/specialties_controller.php" method="POST">
                <input type="hidden" name="specialty_id" id="editSpecialtyId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editSpecialtyModalLabel">
                        <i class="bi bi-pencil-square"></i> Modifier la spécialité
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="edit_specialty" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Edit Specialty Modal Functionality
        const editButtons = document.querySelectorAll('.edit-specialty-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Get data attributes
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const description = button.getAttribute('data-description');
                
                // Set values in the modal
                document.getElementById('editSpecialtyId').value = id;
                document.getElementById('editName').value = name;
                document.getElementById('editDescription').value = description;
            });
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>
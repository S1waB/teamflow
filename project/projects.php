<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// Vérification de l'authentification
if (!isAuthenticated()) {
    header('Location: /login.php');
    exit();
}

// Vérification du rôle
if (!in_array($_SESSION['role'], ['admin', 'chef_projet', 'membre'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = "Gestion des Projets";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des projets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --text-color: #1f2937;
            --bg-color: #f8fafc;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8fafc;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
        }

        .table td {
            vertical-align: middle;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .action-buttons .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e5e7eb;
        }

        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 4px;
        }

        .search-box {
            min-width: 300px;
            position: relative;
        }

        .search-box .form-control {
            padding-right: 40px;
        }

        .search-box .btn {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            color: var(--primary-color);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../layouts/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Gestion des projets</h1>
                <div class="d-flex gap-2 align-items-center">
                    <div class="search-box">
                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un projet...">
                        <button class="btn btn-outline-secondary" type="button" id="searchButton">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chef_projet'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal" id="openAddProjectModal">
                        <i class="bi bi-plus-lg"></i> Nouveau Projet
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Date de début</th>
                                <th>Date de fin</th>
                                <th>Progression</th>
                                <th>Chef de projet</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="projectsTableBody">
                            <!-- Les projets seront chargés dynamiquement ici -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        Affichage de <span id="currentPage">1</span> à <span id="totalPages">1</span> pages
                    </div>
                    <nav aria-label="Navigation des projets">
                        <ul class="pagination mb-0" id="pagination">
                            <!-- La pagination sera générée dynamiquement ici -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProjectModalLabel">Nouveau Projet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalMessage" class="alert d-none" role="alert"></div>
                    <form id="projectForm">
                        <input type="hidden" id="projectId">
                        <div class="mb-3">
                            <label class="form-label">Nom du projet</label>
                            <input type="text" class="form-control" id="projectName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="projectDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="startDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date de fin prévue</label>
                            <input type="date" class="form-control" id="endDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Progression (%)</label>
                            <input type="number" class="form-control" id="progress" min="0" max="100" value="0">
                        </div>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="mb-3">
                            <label class="form-label">Chef de projet</label>
                            <select class="form-control" id="managerId" required>
                                <?php
                                $query = "SELECT u.id, u.name FROM users u 
                                         JOIN roles r ON u.role_id = r.id 
                                         WHERE r.name IN ('admin', 'chef_projet')";
                                $stmt = $pdo->prepare($query);
                                $stmt->execute();
                                $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($managers as $manager) {
                                    echo "<option value='" . $manager['id'] . "'>" . htmlspecialchars($manager['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="saveProject">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const config = {
            currentPage: 1,
            itemsPerPage: 10,
            searchQuery: '',
            apiUrl: 'api/projects.php'
        };

        // Fonctions utilitaires
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('fr-FR');
        }

        function showError(message) {
            const msg = document.getElementById('modalMessage');
            msg.className = 'alert alert-danger';
            msg.textContent = message;
        }

        // Fonctions de gestion des projets
        async function loadProjects() {
            try {
                const response = await fetch(`${config.apiUrl}?page=${config.currentPage}&limit=${config.itemsPerPage}&search=${config.searchQuery}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des projets');
                }
                
                renderProjects(data.data);
                renderPagination(data.pagination);
            } catch (error) {
                showError(error.message);
            }
        }

        function renderProjects(projects) {
            const tbody = document.getElementById('projectsTableBody');
            tbody.innerHTML = '';
            
            projects.forEach(project => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${project.id}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-folder2-open me-2 text-primary"></i>
                            <span class="fw-medium">${project.name}</span>
                        </div>
                    </td>
                    <td>${project.description || '-'}</td>
                    <td>${formatDate(project.start_date)}</td>
                    <td>${formatDate(project.end_date)}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1">
                                <div class="progress-bar" role="progressbar"
                                     style="width: ${project.progress}%;"
                                     aria-valuenow="${project.progress}"
                                     aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <span class="text-muted">${project.progress}%</span>
                            ${project.progress == 100 ? '<span class="badge bg-success">Terminé</span>' : ''}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-primary">${project.manager_name || '-'}</span>
                    </td>
                    <td class="action-buttons">
                        <button class="btn btn-sm btn-info" onclick="viewProject(${project.id})" title="Voir le projet">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'chef_projet'): ?>
                        <button class="btn btn-sm btn-primary" onclick="editProject(${project.id})" title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteProject(${project.id})" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function renderPagination(pagination) {
            const paginationElement = document.getElementById('pagination');
            paginationElement.innerHTML = '';
            
            document.getElementById('currentPage').textContent = pagination.page;
            document.getElementById('totalPages').textContent = pagination.total_pages;
            
            // Bouton précédent
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${pagination.page === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `
                <button class="page-link" onclick="changePage(${pagination.page - 1})" ${pagination.page === 1 ? 'disabled' : ''}>
                    <i class="bi bi-chevron-left"></i>
                </button>
            `;
            paginationElement.appendChild(prevLi);
            
            // Pages
            for (let i = 1; i <= pagination.total_pages; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === pagination.page ? 'active' : ''}`;
                li.innerHTML = `
                    <button class="page-link" onclick="changePage(${i})">${i}</button>
                `;
                paginationElement.appendChild(li);
            }
            
            // Bouton suivant
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${pagination.page === pagination.total_pages ? 'disabled' : ''}`;
            nextLi.innerHTML = `
                <button class="page-link" onclick="changePage(${pagination.page + 1})" ${pagination.page === pagination.total_pages ? 'disabled' : ''}>
                    <i class="bi bi-chevron-right"></i>
                </button>
            `;
            paginationElement.appendChild(nextLi);
        }

        function changePage(page) {
            config.currentPage = page;
            loadProjects();
        }

        async function viewProject(id) {
            window.location.href = `project_details.php?id=${id}`;
        }

        async function editProject(id) {
            try {
                const response = await fetch(`${config.apiUrl}?id=${id}`);
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement du projet');
                }
                const project = data.data;
                document.getElementById('projectId').value = project.id;
                document.getElementById('projectName').value = project.name;
                document.getElementById('projectDescription').value = project.description;
                document.getElementById('startDate').value = project.start_date;
                document.getElementById('endDate').value = project.end_date;
                document.getElementById('progress').value = project.progress;
                if (document.getElementById('managerId')) {
                    document.getElementById('managerId').value = project.manager_id;
                }
                document.querySelector('#addProjectModal .modal-title').textContent = 'Modifier Projet';
                const msg = document.getElementById('modalMessage');
                msg.className = 'alert d-none';
                msg.textContent = '';
                new bootstrap.Modal(document.getElementById('addProjectModal')).show();
            } catch (error) {
                showError(error.message);
            }
        }

        document.getElementById('openAddProjectModal')?.addEventListener('click', function() {
            document.querySelector('#addProjectModal .modal-title').textContent = 'Nouveau Projet';
            document.getElementById('projectForm').reset();
            document.getElementById('projectId').value = '';
            const msg = document.getElementById('modalMessage');
            msg.className = 'alert d-none';
            msg.textContent = '';
        });

        async function deleteProject(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')) {
                try {
                    const response = await fetch(config.apiUrl, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id })
                    });
                    
                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Erreur lors de la suppression du projet');
                    }
                    
                    loadProjects();
                } catch (error) {
                    showError(error.message);
                }
            }
        }

        // Gestionnaires d'événements
        document.getElementById('searchButton').addEventListener('click', () => {
            config.searchQuery = document.getElementById('searchInput').value;
            config.currentPage = 1;
            loadProjects();
        });

        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                config.searchQuery = e.target.value;
                config.currentPage = 1;
                loadProjects();
            }
        });

        document.getElementById('saveProject').addEventListener('click', async () => {
            if (!document.getElementById('projectName').value.trim() ||
                !document.getElementById('startDate').value ||
                !document.getElementById('endDate').value) {
                showError('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            const formData = {
                id: document.getElementById('projectId').value || null,
                name: document.getElementById('projectName').value,
                description: document.getElementById('projectDescription').value,
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value,
                progress: document.getElementById('progress').value,
                manager_id: document.getElementById('managerId') ? document.getElementById('managerId').value : <?php echo $_SESSION['user_id']; ?>
            };

            const msg = document.getElementById('modalMessage');
            msg.className = 'alert d-none';
            msg.textContent = '';

            try {
                const response = await fetch(config.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                const data = await response.json();
                if (!data.success) {
                    showError(data.error || 'Erreur lors de l\'enregistrement du projet');
                    return;
                }
                msg.className = 'alert alert-success';
                msg.textContent = data.message || 'Projet enregistré avec succès';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('addProjectModal')).hide();
                    loadProjects();
                }, 900);
            } catch (error) {
                showError(error.message);
            }
        });

        // Chargement initial
        loadProjects();
    </script>
</body>
</html> 
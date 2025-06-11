<?php
session_start();
require_once '../config/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$pageTitle = "Gestion des Tâches";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des tâches</title>
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

        .filters {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-todo {
            background-color: #e5e7eb;
            color: #374151;
        }

        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-finished {
            background-color: #dcfce7;
            color: #166534;
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
                <h1 class="h3 mb-0">Gestion des tâches</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                    <i class="bi bi-plus-lg"></i> Nouvelle Tâche
                        </button>
                    </div>
        </div>

        <div class="filters">
            <div class="row g-3">
                            <div class="col-md-3">
                                <select class="form-select" id="projectFilter">
                                    <option value="">Tous les projets</option>
                                    <!-- Les projets seront chargés ici dynamiquement -->
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="statusFilter">
                                    <option value="">Tous les statuts</option>
                        <option value="To-do">À faire</option>
                        <option value="in progress">En cours</option>
                        <option value="finished">Terminée</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="priorityFilter">
                                    <option value="">Toutes les priorités</option>
                                    <option value="low">Basse</option>
                                    <option value="medium">Moyenne</option>
                                    <option value="high">Haute</option>
                                </select>
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
                                        <th>Titre</th>
                                        <th>Projet</th>
                                        <th>Assigné à</th>
                                        <th>Statut</th>
                                        <th>Date d'échéance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                        <tbody id="tasksTableBody">
                                    <!-- Les tâches seront chargées ici dynamiquement -->
                                </tbody>
                            </table>
            </div>
        </div>
    </div>
</div>

    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                    <h5 class="modal-title" id="addTaskModalLabel">Nouvelle Tâche</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                    <div id="modalMessage" class="alert d-none" role="alert"></div>
                <form id="taskForm">
                        <input type="hidden" id="taskId">
                    <div class="mb-3">
                            <label class="form-label">Titre</label>
                        <input type="text" class="form-control" id="taskTitle" required>
                    </div>
                    <div class="mb-3">
                            <label class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                            <label class="form-label">Projet</label>
                        <select class="form-select" id="taskProject" required>
                            <!-- Les projets seront chargés ici dynamiquement -->
                        </select>
                    </div>
                    <div class="mb-3">
                            <label class="form-label">Assigné à</label>
                        <select class="form-select" id="taskAssignee">
                            <!-- Les utilisateurs seront chargés ici dynamiquement -->
                        </select>
                    </div>
                    <div class="mb-3">
                            <label class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="taskStartDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date d'échéance</label>
                            <input type="date" class="form-control" id="taskDueDate" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveTask">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const config = {
            apiUrl: 'api/tasks.php'
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

        function showSuccess(message) {
            const msg = document.getElementById('modalMessage');
            msg.className = 'alert alert-success';
            msg.textContent = message;
        }

        // Fonctions de gestion des tâches
        async function loadTasks() {
            try {
                const projectFilter = document.getElementById('projectFilter').value;
                const statusFilter = document.getElementById('statusFilter').value;

                const response = await fetch(`${config.apiUrl}?project=${projectFilter}&status=${statusFilter}`);
                console.log('Raw response for projects:', response);
                const responseText = await response.text();
                console.log('Response text for projects:', responseText);
                const data = JSON.parse(responseText);
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des tâches');
                }
                
                renderTasks(data.data);
            } catch (error) {
                showError(error.message);
            }
        }

        function renderTasks(tasks) {
            const tbody = document.getElementById('tasksTableBody');
            tbody.innerHTML = '';
            
            tasks.forEach(task => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${task.id}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check2-square me-2 text-primary"></i>
                            <span class="fw-medium">${task.title}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-primary">${task.project_name}</span>
                    </td>
                    <td>${task.assignee_name || '-'}</td>
                    <td>
                        <span class="status-badge status-${task.status.toLowerCase().replace(' ', '-')}">
                            ${task.status === 'To-do' ? 'À faire' : 
                              task.status === 'in progress' ? 'En cours' : 'Terminée'}
                        </span>
                    </td>
                    <td>${formatDate(task.due_date)}</td>
                    <td class="action-buttons">
                        <button class="btn btn-sm btn-info" onclick="viewTask(${task.id})" title="Voir la tâche">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="editTask(${task.id})" title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteTask(${task.id})" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        async function loadProjects() {
            try {
                const response = await fetch('../project/api/projects.php');
                console.log('Raw response for projects:', response);
                const responseText = await response.text();
                console.log('Response text for projects:', responseText);
                const data = JSON.parse(responseText);
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des projets');
                }
                
                const projectFilter = document.getElementById('projectFilter');
                const taskProject = document.getElementById('taskProject');
                
                [projectFilter, taskProject].forEach(select => {
                    select.innerHTML = '<option value="">Sélectionner un projet</option>';
                    data.data.forEach(project => {
                        select.innerHTML += `<option value="${project.id}">${project.name}</option>`;
                    });
                });
            } catch (error) {
                showError(error.message);
            }
        }

        async function loadUsers(projectId = null) {
            try {
                let url = '../backend/users.php';
                if (projectId) {
                    url += `?project_id=${projectId}`;
                }
                const response = await fetch(url);
                console.log('Raw response for users:', response);
                const responseText = await response.text();
                console.log('Response text for users:', responseText);
                const data = JSON.parse(responseText);
                
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des utilisateurs');
                }
                
                const taskAssignee = document.getElementById('taskAssignee');
                taskAssignee.innerHTML = '<option value="">Sélectionner un utilisateur</option>';
                data.data.forEach(user => {
                    taskAssignee.innerHTML += `<option value="${user.id}">${user.name}</option>`;
                });
            } catch (error) {
                showError(error.message);
            }
        }

        async function viewTask(id) {
            window.location.href = `task_details.php?id=${id}`;
        }

        async function editTask(id) {
            try {
                const response = await fetch(`${config.apiUrl}?id=${id}`);
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement de la tâche');
                }
                const task = data.data;
                document.getElementById('taskId').value = task.id;
                document.getElementById('taskTitle').value = task.title;
                document.getElementById('taskDescription').value = task.description;
                document.getElementById('taskProject').value = task.project_id;
                document.getElementById('taskAssignee').value = task.assigned_to;
                document.getElementById('taskStartDate').value = task.start_date;
                document.getElementById('taskDueDate').value = task.due_date;
                
                document.querySelector('#addTaskModal .modal-title').textContent = 'Modifier Tâche';
                const msg = document.getElementById('modalMessage');
                msg.className = 'alert d-none';
                msg.textContent = '';
                new bootstrap.Modal(document.getElementById('addTaskModal')).show();
            } catch (error) {
                showError(error.message);
            }
        }

        async function deleteTask(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
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
                        throw new Error(data.error || 'Erreur lors de la suppression de la tâche');
                    }
                    
                    loadTasks();
                } catch (error) {
                    showError(error.message);
                }
            }
        }

        // Gestionnaires d'événements
        document.getElementById('projectFilter').addEventListener('change', () => {
            loadTasks();
            loadUsers(document.getElementById('projectFilter').value);
        });
        document.getElementById('statusFilter').addEventListener('change', loadTasks);

        document.getElementById('saveTask').addEventListener('click', async () => {
            if (!document.getElementById('taskTitle').value.trim() ||
                !document.getElementById('taskProject').value ||
                !document.getElementById('taskStartDate').value ||
                !document.getElementById('taskDueDate').value) {
                showError('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            const formData = {
                id: document.getElementById('taskId').value || null,
                title: document.getElementById('taskTitle').value,
                description: document.getElementById('taskDescription').value,
                project_id: document.getElementById('taskProject').value,
                assigned_to: document.getElementById('taskAssignee').value || null,
                start_date: document.getElementById('taskStartDate').value,
                due_date: document.getElementById('taskDueDate').value
            };

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
                    showError(data.error || 'Erreur lors de l\'enregistrement de la tâche');
                    return;
                }
                showSuccess(data.message || 'Tâche enregistrée avec succès');
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
                    loadTasks();
                }, 900);
            } catch (error) {
                showError(error.message);
            }
        });

        // Chargement initial
        loadProjects();
        loadUsers();
        loadTasks();
    </script>
</body>
</html> 
-- 2 Admins
INSERT INTO users (name, email, password, role_id, specialty_id) VALUES
('Admin One', 'admin1@example.com', '123456789', 1, NULL),
('Admin Two', 'admin2@example.com', '123456789', 1, NULL);

-- 4 Chefs de projet (spécialité Frontend/Backend alternée)
INSERT INTO users (name, email, password, role_id, specialty_id) VALUES
('Chef Projet 1', 'chef1@example.com', '123456789', 2, 1),
('Chef Projet 2', 'chef2@example.com', '123456789', 2, 2),
('Chef Projet 3', 'chef3@example.com', '123456789', 2, 1),
('Chef Projet 4', 'chef4@example.com', '123456789', 2, 2);

-- 8 Membres avec différentes spécialités (Frontend, Backend, Tester)
INSERT INTO users (name, email, password, role_id, specialty_id) VALUES
('Membre 1', 'membre1@example.com', '123456789', 3, 1),
('Membre 2', 'membre2@example.com', '123456789', 3, 2),
('Membre 3', 'membre3@example.com', '123456789', 3, 3),
('Membre 4', 'membre4@example.com', '123456789', 3, 1),
('Membre 5', 'membre5@example.com', '123456789', 3, 2),
('Membre 6', 'membre6@example.com', '123456789', 3, 3),
('Membre 7', 'membre7@example.com', '123456789', 3, 1),
('Membre 8', 'membre8@example.com', '123456789', 3, 2);


INSERT INTO projects (name, description, start_date, end_date, progress, manager_id) VALUES
('Projet Orion', 'Application mobile de gestion de tâches', '2025-06-01', '2025-09-01', 0, 5),
('Projet Vega', 'Plateforme e-learning', '2025-06-15', '2025-10-15', 0, 6),
('Projet Nova', 'Système de gestion RH', '2025-07-01', '2025-11-01', 0, 7),
('Projet Atlas', 'Refonte du site e-commerce', '2025-06-20', '2025-09-20', 0, 8),
('Projet Zen', 'Dashboard analytique', '2025-07-05', '2025-10-05', 0, 5),
('Projet Helios', 'Application de réservation en ligne', '2025-07-10', '2025-11-10', 0, 6);





-- Chaque projet a 4 membres
INSERT INTO project_members (project_id, member_id) VALUES
(1, 9), (1, 10), (1, 11), (1, 12),
(2, 9), (2, 13), (2, 14), (2, 10),
(3, 11), (3, 12), (3, 13), (3, 14),
(4, 9), (4, 10), (4, 11), (4, 12),
(5, 13), (5, 14), (5, 9), (5, 10),
(6, 11), (6, 12), (6, 13), (6, 14);



INSERT INTO tasks (title, description, status, progress, start_date, due_date, project_id, assigned_to) VALUES
-- Projet 1
('UI Design', 'Conception de la maquette', 'To-do', 0, '2025-06-02', '2025-06-10', 1, 9),
('API Backend', 'Développement de l’API', 'To-do', 0, '2025-06-05', '2025-06-20', 1, 10),
('Tests Initiaux', 'Tests unitaires', 'To-do', 0, '2025-06-10', '2025-06-25', 1, 11),
('Déploiement', 'Mise en production', 'To-do', 0, '2025-06-25', '2025-07-01', 1, 12),

-- Projet 2
('Création Modules', 'Modules e-learning', 'To-do', 0, '2025-06-16', '2025-06-30', 2, 9),
('Backend Auth', 'Connexion utilisateur', 'To-do', 0, '2025-06-18', '2025-07-02', 2, 13),
('Tests Sécurité', 'Tests de sécurité', 'To-do', 0, '2025-07-01', '2025-07-10', 2, 14),
('Responsive Design', 'Compatibilité mobile', 'To-do', 0, '2025-07-05', '2025-07-20', 2, 10),

-- Projet 3
('BDD RH', 'Création de la base de données RH', 'To-do', 0, '2025-07-02', '2025-07-10', 3, 11),
('Interface RH', 'Page RH', 'To-do', 0, '2025-07-11', '2025-07-25', 3, 12),
('Test performance', 'Test de charge', 'To-do', 0, '2025-07-20', '2025-08-01', 3, 13),
('Documentation', 'Rédaction de la doc', 'To-do', 0, '2025-08-01', '2025-08-10', 3, 14),

-- Projet 4
('Audit UX', 'Analyse expérience utilisateur', 'To-do', 0, '2025-06-21', '2025-06-28', 4, 9),
('Dev produits', 'Développement produits', 'To-do', 0, '2025-06-29', '2025-07-10', 4, 10),
('Tests A/B', 'Comparaison de versions', 'To-do', 0, '2025-07-11', '2025-07-20', 4, 11),
('Fix Bugs', 'Correction anomalies', 'To-do', 0, '2025-07-21', '2025-08-01', 4, 12),

-- Projet 5
('Dashboard v1', 'Maquette dashboard', 'To-do', 0, '2025-07-06', '2025-07-15', 5, 13),
('Connexion Data', 'Sources de données', 'To-do', 0, '2025-07-10', '2025-07-20', 5, 14),
('Analyse Metrics', 'Visualisation KPI', 'To-do', 0, '2025-07-21', '2025-08-01', 5, 9),
('Refacto Code', 'Nettoyage code', 'To-do', 0, '2025-08-02', '2025-08-10', 5, 10),

-- Projet 6
('Réservation UI', 'Interface utilisateur', 'To-do', 0, '2025-07-11', '2025-07-20', 6, 11),
('Gestion Paiement', 'Module de paiement', 'To-do', 0, '2025-07-21', '2025-08-01', 6, 12),
('Tests fonctionnels', 'Test complet du parcours', 'To-do', 0, '2025-08-02', '2025-08-15', 6, 13),
('Livraison finale', 'Livraison du projet', 'To-do', 0, '2025-08-16', '2025-08-25', 6, 14);

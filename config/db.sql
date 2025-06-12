-- Création de la base de données
CREATE DATABASE IF NOT EXISTS teamflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teamflow;

-- Table : roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- Insertion des rôles
INSERT INTO roles (name, description) VALUES 
('admin', 'Administrateur du système'),
('chef_projet', 'Chef de projet responsable de la gestion des projets'),
('membre', 'Membre technique de l’équipe');

-- Table : specialties
CREATE TABLE specialties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- Insertion d'exemples de spécialités
INSERT INTO specialties (name, description) VALUES 
('Frontend Developer', 'Spécialiste en développement frontend'),
('Backend Developer', 'Spécialiste en développement backend'),
('Tester', 'Spécialiste en test logiciel');

-- Table : users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    specialty_id INT DEFAULT NULL,
     profile_pic VARCHAR(255) DEFAULT NULL,  -- New column for profile picture filename or URL
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE SET NULL ON UPDATE CASCADE

);

INSERT INTO users (name, email, password, role_id, specialty_id) VALUES
('Alice Admin', 'alice.admin@example.com', 'hashed_password_admin', 1, NULL),
('Bob Chef', 'bob.chef@example.com', 'hashed_password_chef', 2, 1), -- spécialité 1 = Frontend Developer par ex
('Charlie Membre', 'charlie.membre@example.com', 'hashed_password_membre', 3, 2); -- spécialité 2 = Backend Developer par ex

-- Table : projects
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    progress FLOAT DEFAULT 0,
    manager_id INT NOT NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);




-- Table : project_members
CREATE TABLE project_members (
    project_id INT,
    member_id INT,
    PRIMARY KEY (project_id, member_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Table : tasks
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('To-do', 'in progress', 'finished') DEFAULT 'To-do',
    progress FLOAT DEFAULT 0,
    start_date DATE NOT NULL,
    due_date DATE NOT NULL,
    project_id INT NOT NULL,
    assigned_to INT,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE TABLE task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Supposons que l'utilisateur avec id=2 est un chef de projet (Bob Chef)
INSERT INTO projects (name, description, start_date, end_date, progress, manager_id) VALUES
('Projet Alpha', 'Développement une nouvelle application web', '2025-06-01', '2025-09-30', 0, 2),
('Projet Beta', 'Refonte du site existant', '2025-07-01', '2025-10-15', 0, 2);
-- Ajouter Charlie Membre (id=3) au Projet Alpha (id=1)
INSERT INTO project_members (project_id, member_id) VALUES
(1, 3);
INSERT INTO tasks (title, description, status, progress, start_date, due_date, project_id, assigned_to) VALUES
('Concevoir maquettes UI', 'Créer les maquettes pour la page accueil', 'To-do', 0, '2025-06-02', '2025-06-10', 1, 3),
('Développer API Backend', 'Créer les endpoints pour la gestion des utilisateurs', 'To-do', 40, '2025-06-05', '2025-07-05', 1, 3),
('Tests fonctionnels', 'Réaliser les tests unitaires et d intégration', 'To-do', 0, '2025-07-10', '2025-07-20', 1, 3);

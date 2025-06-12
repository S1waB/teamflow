// users manager
document.addEventListener('DOMContentLoaded', () => {
    // Edit User Modal Functionality
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const userData = JSON.parse(button.getAttribute('data-user'));

            document.getElementById('editUserId').value = userData.id;
            document.getElementById('editName').value = userData.name;
            document.getElementById('editEmail').value = userData.email;
            document.getElementById('editRole').value = userData.role_id;
            document.getElementById('editSpecialty').value = userData.specialty_id || '';
            document.getElementById('editPassword').value = '';
        });
    });

    // Password Confirmation for Add Modal
    const addForm = document.querySelector('#addUserModal form');
    addForm.addEventListener('submit', (e) => {
        const password = document.getElementById('passwordAdd').value;
        const confirmPassword = document.getElementById('passwordConfirm').value;

        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas !');
            document.getElementById('passwordConfirm').focus();
        }
    });
});
// projects manager
document.addEventListener('DOMContentLoaded', () => {
    // Progress slider for add modal
    const progressInput = document.getElementById('progressInput');
    const progressValue = document.getElementById('progressValue');

    progressInput.addEventListener('input', () => {
        progressValue.textContent = `${progressInput.value}%`;
    });

    // Progress slider for edit modal
    const editProgressInput = document.getElementById('editProgressInput');
    const editProgressValue = document.getElementById('editProgressValue');

    editProgressInput.addEventListener('input', () => {
        editProgressValue.textContent = `${editProgressInput.value}%`;
    });

    // Edit Project Modal Functionality
    const editButtons = document.querySelectorAll('.edit-project-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const projectData = JSON.parse(button.getAttribute('data-project'));

            document.getElementById('editProjectId').value = projectData.id;
            document.getElementById('editProjectName').value = projectData.name;
            document.getElementById('editProjectDescription').value = projectData.description;
            document.getElementById('editStartDate').value = projectData.start_date;
            document.getElementById('editEndDate').value = projectData.end_date;
            document.getElementById('editManagerSelect').value = projectData.manager_id;
            document.getElementById('editProgressInput').value = projectData.progress;
            editProgressValue.textContent = `${projectData.progress}%`;
        });
    });
});
//  tasks manager
document.addEventListener('DOMContentLoaded', () => {
    // Progress slider for add modal
    const progressAdd = document.getElementById('progressAdd');
    const progressValueAdd = document.getElementById('progressValueAdd');
    progressAdd.addEventListener('input', () => {
        progressValueAdd.textContent = `${progressAdd.value}%`;
    });

    // Progress slider for edit modal
    const editProgress = document.getElementById('editProgress');
    const editProgressValue = document.getElementById('editProgressValue');
    editProgress.addEventListener('input', () => {
        editProgressValue.textContent = `${editProgress.value}%`;
    });

    // Progress slider for quick update modal
    const quickProgress = document.getElementById('quickProgress');
    const quickProgressValue = document.getElementById('quickProgressValue');
    quickProgress.addEventListener('input', () => {
        quickProgressValue.textContent = `${quickProgress.value}%`;
    });

    // Edit Task Modal Functionality
    const editButtons = document.querySelectorAll('.edit-task-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', () => {
            const taskData = JSON.parse(button.getAttribute('data-task'));

            document.getElementById('editTaskId').value = taskData.id;
            document.getElementById('editTitle').value = taskData.title;
            document.getElementById('editDescription').value = taskData.description;
            document.getElementById('editStartDate').value = taskData.start_date;
            document.getElementById('editDueDate').value = taskData.due_date;
            document.getElementById('editProject').value = taskData.project_id;
            document.getElementById('editAssignedTo').value = taskData.assigned_to || '';
            document.getElementById('editStatus').value = taskData.status;
            document.getElementById('editProgress').value = taskData.progress;
            editProgressValue.textContent = `${taskData.progress}%`;
        });
    });

    // Quick Update Modal Functionality
    const quickUpdateButtons = document.querySelectorAll('.quick-update-btn');
    quickUpdateButtons.forEach(button => {
        button.addEventListener('click', () => {
            const taskId = button.getAttribute('data-task-id');
            const taskStatus = button.getAttribute('data-task-status');
            const taskProgress = button.getAttribute('data-task-progress');

            document.getElementById('quickTaskId').value = taskId;
            document.getElementById('quickStatus').value = taskStatus;
            document.getElementById('quickProgress').value = taskProgress;
            quickProgressValue.textContent = `${taskProgress}%`;
        });
    });

    // Set today's date as default for start date in add modal
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('startDateAdd').value = today;

    // Set tomorrow's date as default for due date in add modal
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('dueDateAdd').value = tomorrow.toISOString().split('T')[0];
});
// specialties manager 
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
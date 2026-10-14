/*
 * File: js/projects.js
 * Description: Handles the delete-confirmation modal and related project dashboard interactions.
 * Primary Owner: Umar Farooqui
 * Team: Team 418
 */

let deleteProjectId = null;

/**
 * Opens the delete confirmation modal for a selected project.
 *
 * @param {number} projectId Identifier of the project being deleted.
 * @param {string} projectName Display name of the project being deleted.
 * @returns {void}
 */
function showDeleteModal(projectId, projectName) {
  deleteProjectId = projectId;
  document.getElementById('projectNameDisplay').textContent = projectName;
  document.getElementById('deleteModal').classList.remove('is-hidden');
}

/**
 * Closes the delete confirmation modal and clears the pending project id.
 *
 * @returns {void}
 */
function closeDeleteModal() {
  deleteProjectId = null;
  document.getElementById('deleteModal').classList.add('is-hidden');
}

/**
 * Submits the delete form for the currently selected project.
 *
 * @returns {void}
 */
function confirmDelete() {
  if (!deleteProjectId) return;

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'projects.php';
  form.innerHTML = `
    <input type="hidden" name="action" value="delete_project">
    <input type="hidden" name="project_id" value="${deleteProjectId}">
  `;
  document.body.appendChild(form);
  form.submit();
}

/**
 * Wires the modal overlay click target after the page finishes loading.
 *
 * @returns {void}
 */
function initializeDeleteModalOverlay() {
  const modal = document.getElementById('deleteModal');
  if (modal) {
    const overlay = modal.querySelector('.modal-overlay');
    if (overlay) {
      overlay.addEventListener('click', closeDeleteModal);
    }
  }
}

document.addEventListener('DOMContentLoaded', initializeDeleteModalOverlay);

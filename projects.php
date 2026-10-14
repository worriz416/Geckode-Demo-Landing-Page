<?php
/*
 * File: projects.php
 * Description: Manages project creation, editing, collaboration, and deletion for authenticated users.
 * Primary Owners: Liam Klein and Umar Farooqui
 * Team: Team 418
 */
$geckodePageTitle = 'Projects – Geckode';
$geckodeNavPrefix = 'index.php';
$geckodeExtraStylesheets = ['css/projects.css'];
require_once __DIR__ . '/bootstrap.php';

/* ── guard: must be logged in ── */
if (!geckode_is_logged_in()) {
    geckode_flash_set('error', 'Please log in to manage projects.');
    geckode_redirect('login.php');
}

$currentUser = geckode_current_user();
$allowedPrivacyOptions = ['private', 'public'];

/* ================================================================
   ACTION HANDLERS (POST)
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* ── Create Project ── */
    if ($action === 'create_project') {
        $name = trim($_POST['project_name'] ?? '');
        $type = $_POST['project_type'] ?? 'personal';
        $privacy = $_POST['privacy'] ?? 'private';
        $description = trim($_POST['description'] ?? '');
        $privacy = in_array($privacy, $allowedPrivacyOptions, true) ? $privacy : 'private';
        $newProjectId = null;

        if ($name === '') {
            geckode_flash_set('error', 'Project name cannot be empty.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO geckode_projects (project_name, description, project_type, privacy, owner_user_id)
                 VALUES (:name, :desc, :type, :privacy, :uid)"
            );
            $stmt->execute([
                ':name' => $name,
                ':desc' => $description,
                ':type' => $type,
                ':privacy' => $privacy,
                ':uid'  => $currentUser['user_id'],
            ]);
            $newProjectId = (int) $pdo->lastInsertId();

            geckode_flash_set('success', "Project \"{$name}\" created!");
        }
        geckode_redirect($newProjectId ? 'projects.php?view=' . $newProjectId : 'projects.php');
    }

    /* ── Update Project ── */
    if ($action === 'update_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $name = trim($_POST['project_name'] ?? '');
        $type = $_POST['project_type'] ?? 'personal';
        $privacy = $_POST['privacy'] ?? 'private';
        $description = trim($_POST['description'] ?? '');
        $privacy = in_array($privacy, $allowedPrivacyOptions, true) ? $privacy : 'private';

        $check = $pdo->prepare(
            "SELECT 1 FROM geckode_projects WHERE project_id = :pid AND owner_user_id = :uid"
        );
        $check->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);

        if (!$check->fetch()) {
            geckode_flash_set('error', 'You do not have permission to edit this project.');
        } elseif ($name === '') {
            geckode_flash_set('error', 'Project name cannot be empty.');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE geckode_projects SET project_name = :name, description = :desc,
                 project_type = :type, privacy = :privacy, date_updated = CURRENT_TIMESTAMP
                 WHERE project_id = :pid"
            );
            $stmt->execute([
                ':name' => $name,
                ':desc' => $description,
                ':type' => $type,
                ':privacy' => $privacy,
                ':pid' => $projectId,
            ]);
            geckode_flash_set('success', 'Project updated!');
        }
        geckode_redirect('projects.php?view=' . $projectId);
    }

    /* ── Invite Collaborator ── */
    if ($action === 'invite_collaborator') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['project_role'] ?? 'viewer';

        $check = $pdo->prepare(
            "SELECT 1
               FROM geckode_projects p
               LEFT JOIN geckode_project_collaborators pc
                 ON pc.project_id = p.project_id
                AND pc.user_id = :uid
              WHERE p.project_id = :pid
                AND (p.owner_user_id = :uid OR pc.project_role = 'admin')
              LIMIT 1"
        );
        $check->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);

        if (!$check->fetch()) {
            geckode_flash_set('error', 'You do not have permission to manage collaborators for this project.');
        } elseif ($username === '') {
            geckode_flash_set('error', 'Please enter a username.');
        } else {
            $find = $pdo->prepare("SELECT user_id FROM geckode_users WHERE username = :u");
            $find->execute([':u' => $username]);
            $target = $find->fetch();

            if (!$target) {
                geckode_flash_set('error', "User \"{$username}\" not found.");
            } elseif ($target['user_id'] == $currentUser['user_id']) {
                geckode_flash_set('error', 'You cannot add yourself as a collaborator.');
            } else {
                // Check if already a collaborator
                $existing = $pdo->prepare(
                    "SELECT 1 FROM geckode_project_collaborators WHERE project_id = :pid AND user_id = :uid"
                );
                $existing->execute([':pid' => $projectId, ':uid' => $target['user_id']]);

                if ($existing->fetch()) {
                    geckode_flash_set('error', "\"{$username}\" is already a collaborator on this project.");
                } else {
                    $ins = $pdo->prepare(
                        "INSERT INTO geckode_project_collaborators (project_id, user_id, project_role)
                         VALUES (:pid, :uid, :role)"
                    );
                    $ins->execute([
                        ':pid'  => $projectId,
                        ':uid'  => $target['user_id'],
                        ':role' => $role,
                    ]);
                    geckode_flash_set('success', "\"{$username}\" added as a collaborator.");
                }
            }
        }
        geckode_redirect('projects.php?view=' . $projectId);
    }

    /* ── Remove Collaborator ── */
    if ($action === 'remove_collaborator') {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $collaboratorId = (int)($_POST['collaborator_id'] ?? 0);

        $check = $pdo->prepare(
            "SELECT 1
               FROM geckode_projects p
               LEFT JOIN geckode_project_collaborators pc
                 ON pc.project_id = p.project_id
                AND pc.user_id = :uid
              WHERE p.project_id = :pid
                AND (p.owner_user_id = :uid OR pc.project_role = 'admin')
              LIMIT 1"
        );
        $check->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);

        if ($check->fetch()) {
            $del = $pdo->prepare(
                "DELETE FROM geckode_project_collaborators WHERE collaborator_id = :cid AND project_id = :pid"
            );
            $del->execute([':cid' => $collaboratorId, ':pid' => $projectId]);
            geckode_flash_set('success', 'Collaborator removed.');
        }
        geckode_redirect('projects.php?view=' . $projectId);
    }

    /* ── Delete Project ── */
    if ($action === 'delete_project') {
        $projectId = (int)($_POST['project_id'] ?? 0);

        $check = $pdo->prepare(
            "SELECT 1 FROM geckode_projects WHERE project_id = :pid AND owner_user_id = :uid"
        );
        $check->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);

        if ($check->fetch()) {
            // Delete collaborators first
            $pdo->prepare("DELETE FROM geckode_project_collaborators WHERE project_id = :pid")
                ->execute([':pid' => $projectId]);
            // Then delete project
            $pdo->prepare("DELETE FROM geckode_projects WHERE project_id = :pid")
                ->execute([':pid' => $projectId]);
            geckode_flash_set('success', 'Project deleted.');
        }
        geckode_redirect('projects.php');
    }
}

/* ================================================================
   DATA FOR THE PAGE
   ================================================================ */
$projectListStatement = $pdo->prepare(
    "SELECT
         p.*,
         CASE
             WHEN p.owner_user_id = :uid THEN 'owner'
             ELSE pc.project_role
         END AS access_role
     FROM geckode_projects p
     LEFT JOIN geckode_project_collaborators pc
       ON pc.project_id = p.project_id
      AND pc.user_id = :uid
     WHERE p.owner_user_id = :uid OR pc.user_id IS NOT NULL
     ORDER BY
       CASE WHEN p.owner_user_id = :uid THEN 0 ELSE 1 END,
       p.date_updated DESC,
       p.date_created DESC"
);
$projectListStatement->execute([':uid' => $currentUser['user_id']]);
$projectList = $projectListStatement->fetchAll();

$viewProjectId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$activeProject = null;
$collaborators = [];
$isProjectOwner = false;
$canManageCollaborators = false;

if ($viewProjectId) {
    $stmt = $pdo->prepare(
        "SELECT
             p.*,
             owner.username AS owner_username,
             owner.display_name AS owner_display_name,
             CASE
                 WHEN p.owner_user_id = :uid THEN 'owner'
                 ELSE pc.project_role
             END AS access_role
         FROM geckode_projects p
         JOIN geckode_users owner
           ON owner.user_id = p.owner_user_id
         LEFT JOIN geckode_project_collaborators pc
           ON pc.project_id = p.project_id
          AND pc.user_id = :uid
         WHERE p.project_id = :pid
           AND (p.owner_user_id = :uid OR pc.user_id IS NOT NULL)
         LIMIT 1"
    );
    $stmt->execute([':pid' => $viewProjectId, ':uid' => $currentUser['user_id']]);
    $activeProject = $stmt->fetch();

    if ($activeProject) {
        $isProjectOwner = (int)$activeProject['owner_user_id'] === (int)$currentUser['user_id'];
        $canManageCollaborators = $isProjectOwner || ($activeProject['access_role'] ?? '') === 'admin';
        $stmt = $pdo->prepare(
            "SELECT pc.collaborator_id, pc.project_role, pc.joined_date,
                    u.username, u.display_name, u.email
               FROM geckode_project_collaborators pc
               JOIN geckode_users u ON u.user_id = pc.user_id
              WHERE pc.project_id = :pid
              ORDER BY pc.joined_date ASC"
        );
        $stmt->execute([':pid' => $viewProjectId]);
        $collaborators = $stmt->fetchAll();
    }
}

$flash = geckode_flash_get();

require_once __DIR__ . '/includes/header.php';
?>

<div class="project-page" id="main-content">

  <!-- ── SIDEBAR ── -->
  <aside class="project-sidebar">
    <p class="project-sidebar-heading">Projects</p>

    <ul class="project-list">
      <li>
        <a href="projects.php" class="<?= $viewProjectId === null ? 'is-active' : '' ?>">
          <span>Dashboard</span>
        </a>
      </li>
      <?php if (!empty($projectList)): ?>
        <?php foreach ($projectList as $p): ?>
          <li>
            <a href="projects.php?view=<?= $p['project_id'] ?>"
               class="<?= $viewProjectId == $p['project_id'] ? 'is-active' : '' ?>">
              <span><?= htmlspecialchars($p['project_name']) ?></span>
              <span class="project-sidebar-badges">
                <span class="project-badge project-badge-<?= $p['privacy'] ?>">
                  <?= $p['privacy'] ?>
                </span>
                <span class="project-badge project-badge-<?= htmlspecialchars($p['access_role']) ?>">
                  <?= htmlspecialchars($p['access_role']) ?>
                </span>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </aside>

  <!-- ── MAIN CONTENT ── -->
  <section class="main-column">

    <?php if ($flash): ?>
      <div class="notice notice-<?= $flash['type'] ?> project-page-flash">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- ── CREATE FORM (Dashboard only) ── -->
    <?php if ($viewProjectId === null): ?>
    <!-- ── EMPTY STATE ── -->
    <?php if (empty($projectList)): ?>
    <div class="project-card">
      <p class="eyebrow">No Projects Yet</p>
      <h2>Get Started</h2>
      <p class="project-sub">Create your first project to begin building!</p>
    </div>
    <?php endif; ?>

    <div class="project-card">
      <p class="eyebrow">Dashboard</p>
      <h2>Create Project</h2>
      <p class="project-sub">Start building your creative coding project.</p>

      <form method="POST" action="projects.php">
        <input type="hidden" name="action" value="create_project">
        <div class="project-form-row">
          <div class="field">
            <span>Project Name</span>
            <input type="text" name="project_name"
                   placeholder="e.g. My Game" required>
          </div>
          <div class="field">
            <span>Type</span>
            <select name="project_type">
              <option value="personal">Personal</option>
              <option value="classroom">Classroom</option>
              <option value="collaborative">Collaborative</option>
            </select>
          </div>
          <div class="field">
            <span>Privacy</span>
            <select name="privacy">
              <option value="private">Private</option>
              <option value="public">Public</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-tall">
            Create Project
          </button>
        </div>
        <div class="field field-spaced">
          <span>Description (optional)</span>
          <textarea name="description" rows="3" placeholder="Describe your project..."></textarea>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- ── EDIT & COLLABORATORS (when viewing a project) ── -->
    <?php if ($viewProjectId !== null && $activeProject): ?>
    <div class="project-card">
      <h1 class="project-title">
        <?= htmlspecialchars($activeProject['project_name']) ?>
      </h1>
      <div class="project-header-row">
        <h2>Settings</h2>
        <a href="project_editor.php?project_id=<?= $activeProject['project_id'] ?>" class="btn btn-primary">
          Open Project
        </a>
      </div>
      <p class="project-sub">
        <span class="project-badge project-badge-<?= $activeProject['privacy'] ?>">
          <?= $activeProject['privacy'] ?>
        </span>
        &middot;
        <span class="project-badge project-badge-type">
          <?= ucfirst(str_replace('_', ' ', $activeProject['project_type'])) ?>
        </span>
        &middot;
        <span class="project-badge project-badge-<?= htmlspecialchars($activeProject['access_role']) ?> project-badge-spaced">
          <?= htmlspecialchars($activeProject['access_role']) ?>
        </span>
        &middot; Created <?= date('M j, Y', strtotime($activeProject['date_created'])) ?>
      </p>

      <?php if ($isProjectOwner): ?>
        <!-- ── UPDATE FORM ── -->
        <h3 class="project-details-heading">
          Project Details
        </h3>
        <form method="POST" action="projects.php">
          <input type="hidden" name="action" value="update_project">
          <input type="hidden" name="project_id" value="<?= $activeProject['project_id'] ?>">
          <div class="project-form-row">
            <div class="field">
              <span>Project Name</span>
              <input type="text" name="project_name" value="<?= htmlspecialchars($activeProject['project_name']) ?>" required>
            </div>
            <div class="field">
              <span>Type</span>
              <select name="project_type">
                <option value="personal" <?= $activeProject['project_type'] === 'personal' ? 'selected' : '' ?>>Personal</option>
                <option value="classroom" <?= $activeProject['project_type'] === 'classroom' ? 'selected' : '' ?>>Classroom</option>
                <option value="collaborative" <?= $activeProject['project_type'] === 'collaborative' ? 'selected' : '' ?>>Collaborative</option>
              </select>
            </div>
            <div class="field">
              <span>Privacy</span>
              <select name="privacy">
                <option value="private" <?= $activeProject['privacy'] === 'private' ? 'selected' : '' ?>>Private</option>
                <option value="public" <?= $activeProject['privacy'] === 'public' ? 'selected' : '' ?>>Public</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary btn-tall">
              Update Project
            </button>
          </div>
          <div class="field field-spaced">
            <span>Description</span>
            <textarea name="description" rows="3"><?= htmlspecialchars($activeProject['description'] ?? '') ?></textarea>
          </div>
        </form>

        <hr class="project-divider">

        <!-- ── COLLABORATORS TABLE ── -->
        <h3 class="project-collaborators-heading">
          Collaborators
        </h3>

        <?php if (empty($collaborators)): ?>
          <p class="empty-note">No collaborators yet.</p>
        <?php else: ?>
          <table class="project-roster">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Joined</th>
                <?php if ($canManageCollaborators): ?>
                  <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($collaborators as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['display_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($c['username']) ?></td>
                <td><?= htmlspecialchars(ucfirst($c['project_role'])) ?></td>
                <td><?= date('M j, Y', strtotime($c['joined_date'])) ?></td>
                <?php if ($canManageCollaborators): ?>
                  <td>
                    <form method="POST" class="form-inline"
                          onsubmit="return confirm('Remove this collaborator?');">
                      <input type="hidden" name="action" value="remove_collaborator">
                      <input type="hidden" name="project_id" value="<?= $activeProject['project_id'] ?>">
                      <input type="hidden" name="collaborator_id" value="<?= $c['collaborator_id'] ?>">
                      <button type="submit" class="btn-danger">Remove</button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <?php if ($canManageCollaborators): ?>
          <hr class="project-divider">

          <!-- ── INVITE COLLABORATOR FORM ── -->
          <h3 class="project-collaborator-heading">
            + Invite Collaborator
          </h3>
          <form method="POST" action="projects.php">
            <input type="hidden" name="action" value="invite_collaborator">
            <input type="hidden" name="project_id" value="<?= $activeProject['project_id'] ?>">
            <div class="project-form-row">
              <div class="field">
                <span>Username</span>
                <input type="text" name="username" placeholder="Enter username" required>
              </div>
              <div class="field">
                <span>Role</span>
                <select name="project_role">
                  <option value="viewer">Viewer</option>
                  <option value="editor">Editor</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-tall">
                Invite
              </button>
            </div>
          </form>
        <?php endif; ?>

        <hr class="project-divider">

        <!-- ── DELETE PROJECT ── -->
        <h3 class="project-danger-heading">
          Danger Zone
        </h3>
        <button class="btn-danger" onclick="showDeleteModal(<?= $activeProject['project_id'] ?>, '<?= htmlspecialchars(addslashes($activeProject['project_name'])) ?>')">
          Delete Project
        </button>
      <?php else: ?>
        <h3 class="project-details-heading">
          Project Details
        </h3>
        <div class="project-form-row">
          <div class="field">
            <span>Owner</span>
            <input type="text" value="<?= htmlspecialchars($activeProject['owner_display_name'] ?: $activeProject['owner_username']) ?>" readonly>
          </div>
          <div class="field">
            <span>Your Access</span>
            <input type="text" value="<?= htmlspecialchars(ucfirst($activeProject['access_role'])) ?>" readonly>
          </div>
        </div>
        <div class="field field-spaced">
          <span>Description</span>
          <textarea rows="3" readonly><?= htmlspecialchars($activeProject['description'] ?? '') ?></textarea>
        </div>
        <?php if (!empty($collaborators)): ?>
          <hr class="project-divider">
          <h3 class="project-collaborators-heading">
            Collaborators
          </h3>
          <table class="project-roster">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Joined</th>
                <?php if ($canManageCollaborators): ?><th>Actions</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($collaborators as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['display_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($c['username']) ?></td>
                <td><?= htmlspecialchars(ucfirst($c['project_role'])) ?></td>
                <td><?= date('M j, Y', strtotime($c['joined_date'])) ?></td>
                <?php if ($canManageCollaborators): ?>
                <td>
                  <form method="POST" class="form-inline"
                        onsubmit="return confirm('Remove this collaborator?');">
                    <input type="hidden" name="action" value="remove_collaborator">
                    <input type="hidden" name="project_id" value="<?= $activeProject['project_id'] ?>">
                    <input type="hidden" name="collaborator_id" value="<?= $c['collaborator_id'] ?>">
                    <button type="submit" class="btn-danger">Remove</button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php if ($canManageCollaborators): ?>
          <hr class="project-divider">
          <h3 class="project-collaborator-heading">
            + Invite Collaborator
          </h3>
          <form method="POST" action="projects.php">
            <input type="hidden" name="action" value="invite_collaborator">
            <input type="hidden" name="project_id" value="<?= $activeProject['project_id'] ?>">
            <div class="project-form-row">
              <div class="field">
                <span>Username</span>
                <input type="text" name="username" placeholder="Enter username" required>
              </div>
              <div class="field">
                <span>Role</span>
                <select name="project_role">
                  <option value="viewer">Viewer</option>
                  <option value="editor">Editor</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary btn-tall">
                Invite
              </button>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </section>
</div>

<!-- ── DELETE CONFIRMATION MODAL ── -->
<div id="deleteModal" class="modal is-hidden">
  <div class="modal-overlay" onclick="closeDeleteModal()"></div>
  <div class="modal-content">
    <h2 class="modal-title-danger">Delete Project?</h2>
    <p class="modal-description">
      This action cannot be undone. The project <strong id="projectNameDisplay"></strong> will be permanently deleted.
    </p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()">Delete Project</button>
    </div>
  </div>
</div>

<script src="js/projects.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

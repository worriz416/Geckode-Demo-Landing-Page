<?php
/*
 * File: project_editor.php
 * Description: Loads the block editor, project state, and collaborative editing interactions for saved projects.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */
$geckodePageTitle = 'Project Editor – Geckode';
$geckodeNavPrefix = 'index.php';
$geckodeExtraStylesheets = ['css/project_editor.css'];
require_once __DIR__ . '/bootstrap.php';

/* ── guard: must be logged in ── */
if (!geckode_is_logged_in()) {
    geckode_flash_set('error', 'Please log in to view projects.');
    geckode_redirect('login.php');
}

$currentUser = geckode_current_user();
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if ($projectId === 0) {
    geckode_flash_set('error', 'Project not found.');
    geckode_redirect('projects.php');
}

/* ── Load project ── */
$stmt = $pdo->prepare(
    "SELECT p.*, 
            (SELECT project_role FROM geckode_project_collaborators WHERE project_id = :pid AND user_id = :uid) as user_role
     FROM geckode_projects p
     WHERE p.project_id = :pid"
);
$stmt->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    geckode_flash_set('error', 'Project not found or you do not have access.');
    geckode_redirect('projects.php');
}

/* ── Determine user role ── */
$userRole = 'none';
if ($project['owner_user_id'] == $currentUser['user_id']) {
    $userRole = 'owner';
} else {
    $userRole = $project['user_role'] ?: 'none';
}

// Check if user has viewing permission
if ($userRole === 'none') {
    geckode_flash_set('error', 'You do not have permission to view this project.');
    geckode_redirect('projects.php');
}

$canEdit = in_array($userRole, ['owner', 'editor', 'admin']);

/* ── Load blocks ── */
$stmt = $pdo->prepare(
    "SELECT * FROM geckode_project_blocks WHERE project_id = :pid ORDER BY block_order ASC"
);
$stmt->execute([':pid' => $projectId]);
$blocks = $stmt->fetchAll();

/* ── Load connections ── */
$stmt = $pdo->prepare(
    "SELECT * FROM geckode_project_block_connections WHERE project_id = :pid"
);
$stmt->execute([':pid' => $projectId]);
$connections = $stmt->fetchAll();

$editorConfig = [
    'projectId' => $projectId,
    'canEdit' => $canEdit,
    'containerId' => 'editor-canvas',
    'trashZoneId' => 'trash-zone',
    'spawnTargetId' => $canEdit ? 'spawn-buttons' : null,
    'blocks' => $blocks,
    'connections' => $connections,
    'palette' => [
        ['label' => 'Block 1', 'color' => '#5ab576'],
        ['label' => 'Block 2', 'color' => '#6C63FF'],
        ['label' => 'Block 3', 'color' => '#FF6584'],
        ['label' => 'Block 4', 'color' => '#F7BA72'],
        ['label' => 'Block 5', 'color' => '#8d69e2'],
    ],
    'soundUrls' => [
        'pickup' => './assets/pop-high.mp3',
        'drop' => './assets/pop-low.mp3',
    ],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="editor-container" id="main-content">
  <aside class="editor-sidebar">
    <div class="editor-info">
      <h2><?= htmlspecialchars($project['project_name']) ?></h2>
      <p class="editor-project-type">
        <?= ucfirst(str_replace('_', ' ', $project['project_type'])) ?>
      </p>
      <span class="editor-role-badge <?= $userRole ?>">
        <?= ucfirst($userRole) ?>
      </span>

      <?php if ($canEdit): ?>
      <div class="editor-spawn-panel">
        <p class="editor-spawn-label">
          Create Block
        </p>
        <div id="spawn-buttons"></div>
      </div>
      <?php endif; ?>

      <a href="projects.php?view=<?= $projectId ?>" class="btn btn-secondary btn-block editor-back-link">
        Back to Settings
      </a>
    </div>
  </aside>

  <section class="editor-main">
    <div class="editor-canvas<?= $canEdit ? '' : ' editor-canvas-read-only' ?>" id="editor-canvas">
      <?php if (!$canEdit): ?>
      <div class="read-only-overlay">
        <span>Read-only mode (<?= ucfirst($userRole) ?>)</span>
      </div>
      <?php endif; ?>
      <div id="trash-zone">🗑️</div>
    </div>
  </section>
</div>

<script id="project-editor-config" type="application/json"><?= json_encode($editorConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="./js/block-editor-core.js"></script>
<script src="./js/project-editor.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

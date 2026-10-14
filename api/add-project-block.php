<?php
/*
 * File: api/add-project-block.php
 * Description: Adds a new block to a saved project and returns the result as JSON for async editor updates.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if (!geckode_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentUser = geckode_current_user();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['projectId']) || !isset($input['color'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$projectId = (int)$input['projectId'];
$color = $input['color'];
$label = $input['label'] ?? 'Block';
$posLeft = isset($input['posLeft']) && is_numeric($input['posLeft']) ? (float)$input['posLeft'] : null;
$posTop = isset($input['posTop']) && is_numeric($input['posTop']) ? (float)$input['posTop'] : null;

// Verify user has edit permission
$stmt = $pdo->prepare(
    "SELECT p.owner_user_id,
            (SELECT project_role FROM geckode_project_collaborators WHERE project_id = :pid AND user_id = :uid) as user_role
     FROM geckode_projects p
     WHERE p.project_id = :pid"
);
$stmt->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

$userRole = $project['owner_user_id'] == $currentUser['user_id'] ? 'owner' : ($project['user_role'] ?: 'none');
$canEdit = in_array($userRole, ['owner', 'editor', 'admin']);

if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

try {
    // Get next block order
    $stmt = $pdo->prepare("SELECT MAX(block_order) as max_order FROM geckode_project_blocks WHERE project_id = :pid");
    $stmt->execute([':pid' => $projectId]);
    $result = $stmt->fetch();
    $nextOrder = ($result['max_order'] ?? 0) + 1;

    // Insert new block
    $stmt = $pdo->prepare(
        "INSERT INTO geckode_project_blocks (project_id, block_order, block_label, block_color, position_left, position_top)
         VALUES (:pid, :order, :label, :color, :left, :top)"
    );
    $stmt->execute([
        ':pid' => $projectId,
        ':order' => $nextOrder,
        ':label' => $label,
        ':color' => $color,
        ':left' => $posLeft ?? rand(0, 300),
        ':top' => $posTop ?? rand(0, 300)
    ]);

    $blockId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'blockId' => $blockId,
        'id' => 'block-' . $blockId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Create failed: ' . $e->getMessage()]);
}

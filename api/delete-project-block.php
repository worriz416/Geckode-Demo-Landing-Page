<?php
/*
 * File: api/delete-project-block.php
 * Description: Deletes a project block and related connections through the async project editor API.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!geckode_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$currentUser = geckode_current_user();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['projectId']) || !isset($input['blockId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing projectId or blockId']);
    exit;
}

$projectId = (int)$input['projectId'];
$blockId = (int)$input['blockId'];

/* ── Load project to check permissions ── */
$stmt = $pdo->prepare("SELECT owner_user_id FROM geckode_projects WHERE project_id = :pid");
$stmt->execute([':pid' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Project not found']);
    exit;
}

/* ── Check permission: owner or editor/admin ── */
$isOwner = $project['owner_user_id'] == $currentUser['user_id'];
$stmt = $pdo->prepare(
    "SELECT project_role FROM geckode_project_collaborators
     WHERE project_id = :pid AND user_id = :uid"
);
$stmt->execute([':pid' => $projectId, ':uid' => $currentUser['user_id']]);
$collaborator = $stmt->fetch();
$canEdit = $isOwner || ($collaborator && in_array($collaborator['project_role'], ['editor', 'admin']));

if (!$canEdit) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

/* ── Delete block ── */
try {
    $pdo->beginTransaction();

    /* Delete the block */
    $stmt = $pdo->prepare("DELETE FROM geckode_project_blocks WHERE block_id = :bid AND project_id = :pid");
    $stmt->execute([':bid' => $blockId, ':pid' => $projectId]);

    /* Delete connections involving this block */
    $stmt = $pdo->prepare(
        "DELETE FROM geckode_project_block_connections
         WHERE project_id = :pid AND (block_id_1 = :bid OR block_id_2 = :bid)"
    );
    $stmt->execute([':pid' => $projectId, ':bid' => $blockId]);

    /* Update project timestamp */
    $stmt = $pdo->prepare("UPDATE geckode_projects SET date_updated = CURRENT_TIMESTAMP WHERE project_id = :pid");
    $stmt->execute([':pid' => $projectId]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

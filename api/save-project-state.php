<?php
/*
 * File: api/save-project-state.php
 * Description: Persists block positions and editor state changes for a saved project through JSON requests.
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

if (!$input || !isset($input['projectId']) || !isset($input['blocks'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$projectId = (int)$input['projectId'];
$blocks = $input['blocks'] ?? [];
$connections = $input['connections'] ?? [];

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
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT block_id FROM geckode_project_blocks WHERE project_id = :pid");
    $stmt->execute([':pid' => $projectId]);
    $validBlockIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $validBlockLookup = array_fill_keys($validBlockIds, true);

    // Update block positions
    $updateBlockStmt = $pdo->prepare(
        "UPDATE geckode_project_blocks
         SET position_left = :left, position_top = :top
         WHERE block_id = :bid AND project_id = :pid"
    );

    foreach ($blocks as $block) {
        $databaseId = isset($block['databaseId']) ? (int)$block['databaseId'] : 0;

        if ($databaseId <= 0 || !isset($validBlockLookup[$databaseId])) {
            continue;
        }

        $updateBlockStmt->execute([
            ':left' => isset($block['posLeft']) ? (float)$block['posLeft'] : 0,
            ':top' => isset($block['posTop']) ? (float)$block['posTop'] : 0,
            ':bid' => $databaseId,
            ':pid' => $projectId,
        ]);
    }

    $deleteConnectionsStmt = $pdo->prepare("DELETE FROM geckode_project_block_connections WHERE project_id = :pid");
    $deleteConnectionsStmt->execute([':pid' => $projectId]);

    $insertConnectionStmt = $pdo->prepare(
        "INSERT INTO geckode_project_block_connections (project_id, block_id_1, block_id_2)
         VALUES (:pid, :block1, :block2)"
    );
    $seenPairs = [];

    foreach ($connections as $connection) {
        $leftId = isset($connection['leftDatabaseId']) ? (int)$connection['leftDatabaseId'] : 0;
        $rightId = isset($connection['rightDatabaseId']) ? (int)$connection['rightDatabaseId'] : 0;
        $normalizedPair = [$leftId, $rightId];
        sort($normalizedPair, SORT_NUMERIC);

        if (
            $leftId <= 0 ||
            $rightId <= 0 ||
            $leftId === $rightId ||
            !isset($validBlockLookup[$leftId]) ||
            !isset($validBlockLookup[$rightId])
        ) {
            continue;
        }

        $pairKey = $normalizedPair[0] . ':' . $normalizedPair[1];
        if (isset($seenPairs[$pairKey])) {
            continue;
        }

        $seenPairs[$pairKey] = true;
        $insertConnectionStmt->execute([
            ':pid' => $projectId,
            ':block1' => $normalizedPair[0],
            ':block2' => $normalizedPair[1],
        ]);
    }

    // Update project timestamp
    $stmt = $pdo->prepare("UPDATE geckode_projects SET date_updated = CURRENT_TIMESTAMP WHERE project_id = :pid");
    $stmt->execute([':pid' => $projectId]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
}

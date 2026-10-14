<?php
/*
 * File: api/get_projects.php
 * Description: Returns project discovery data as JSON for project browsing and filtering flows.
 * Primary Owner: Umar Farooqui
 * Team: Team 418
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$categoryValue = $_GET['category'] ?? 'all';

$sql = 'SELECT p.project_id, p.project_name, p.description, p.play_count, p.like_count,
               p.thumbnail_url, u.display_name, c.category_name
        FROM geckode_projects p
        JOIN geckode_users u ON u.user_id = p.owner_user_id
        LEFT JOIN geckode_project_categories c ON c.category_id = p.category_id';
$params = [];

if ($categoryValue !== 'all' && $category !== false && $category !== null) {
  $sql .= ' WHERE p.category_id = :category_id';
  $params[':category_id'] = $category;
}

$sql .= ' ORDER BY p.is_featured DESC, p.play_count DESC, p.project_id DESC LIMIT 6';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

echo json_encode([
  'success' => true,
  'projects' => $projects,
]);

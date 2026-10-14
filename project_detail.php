<?php
/*
 * File: project_detail.php
 * Description: Displays project details, preview media, and related community projects for discovery flows.
 * Primary Owner: Umar Farooqui
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';
$currentUser = geckode_current_user();

$projectId = (int) ($_GET['id'] ?? 1);
if ($projectId <= 0) {
  $projectId = 1;
}

$projectStmt = $pdo->prepare(
  'SELECT p.project_id, p.project_name, p.description, p.play_count, p.like_count,
          p.thumbnail_url, p.date_created, u.display_name, c.category_name
   FROM geckode_projects p
   JOIN geckode_users u ON u.user_id = p.owner_user_id
   LEFT JOIN geckode_project_categories c ON c.category_id = p.category_id
   WHERE p.project_id = :project_id
   LIMIT 1'
);
$projectStmt->execute([':project_id' => $projectId]);
$project = $projectStmt->fetch();

if (!$project) {
  geckode_flash_set('error', 'That project could not be found.');
  geckode_redirect('index.php#projects');
}

$relatedStmt = $pdo->prepare(
  'SELECT p.project_id, p.project_name, u.display_name
   FROM geckode_projects p
   JOIN geckode_users u ON u.user_id = p.owner_user_id
   WHERE p.project_id != :project_id
   ORDER BY p.play_count DESC, p.like_count DESC
   LIMIT 3'
);
$relatedStmt->execute([':project_id' => $projectId]);
$relatedProjects = $relatedStmt->fetchAll();

$geckodePageTitle = $project['project_name'] . ' | Geckode';
$geckodeNavPrefix = 'project_detail.php';

require __DIR__ . '/includes/header.php';
?>

<main id="main-content">
  <section class="project-detail-page">
    <div class="project-detail-main">
      <p class="eyebrow">Community project</p>
      <h1><?= htmlspecialchars($project['project_name']) ?></h1>
      <p class="project-detail-summary"><?= htmlspecialchars($project['description']) ?></p>

      <div class="project-detail-meta">
        <span>Creator: <?= htmlspecialchars($project['display_name']) ?></span>
        <span>Category: <?= htmlspecialchars($project['category_name'] ?: 'Uncategorized') ?></span>
        <span><?= (int) $project['play_count'] ?> plays</span>
        <span><?= (int) $project['like_count'] ?> likes</span>
      </div>

      <div class="project-preview-box">
        <?php if ($project['thumbnail_url']): ?>
          <div class="project-preview-screen project-preview-screen-image">
            <img class="project-preview-image" src="<?= htmlspecialchars($project['thumbnail_url']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?> thumbnail">
          </div>
        <?php else: ?>
          <div class="project-preview-screen">
            <div class="project-preview-sprite"></div>
            <div class="project-preview-star star-one"></div>
            <div class="project-preview-star star-two"></div>
            <div class="project-preview-star star-three"></div>
          </div>
        <?php endif; ?>
        <div class="project-preview-notes">
          <h2>Project overview</h2>
          <ul>
            <li>Featured thumbnails are stored directly with the project record.</li>
            <li>Use this snapshot to scan the project before opening related work.</li>
            <li>Browse the sidebar to compare this project with others in the app.</li>
          </ul>
        </div>
      </div>

      <a class="btn btn-primary" href="index.php#projects">Back to projects</a>
    </div>

    <aside class="project-detail-side">
      <div class="detail-card">
        <h2>More projects</h2>
        <?php foreach ($relatedProjects as $relatedProject): ?>
          <a class="related-project-link" href="project_detail.php?id=<?= (int) $relatedProject['project_id'] ?>">
            <strong><?= htmlspecialchars($relatedProject['project_name']) ?></strong>
            <span><?= htmlspecialchars($relatedProject['display_name']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

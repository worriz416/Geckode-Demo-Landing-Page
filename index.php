<?php
/*
 * File: index.php
 * Description: Renders the Geckode landing page, including featured project discovery and community highlights.
 * Primary Owners: Greg Forster and Umar Farooqui
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

$currentUser = geckode_current_user();
$flash = geckode_flash_get();

$geckodePageTitle = 'Geckode';
$geckodeNavPrefix = '';

$featuredStmt = $pdo->prepare(
  'SELECT p.project_id, p.project_name, p.description, p.play_count, p.like_count,
          p.thumbnail_url, u.display_name, c.category_name
   FROM geckode_projects p
   JOIN geckode_users u ON u.user_id = p.owner_user_id
   LEFT JOIN geckode_project_categories c ON c.category_id = p.category_id
   WHERE p.is_featured = 1
   ORDER BY p.date_created DESC, p.project_id DESC
   LIMIT 6'
);
$featuredStmt->execute();
$featuredProjects = $featuredStmt->fetchAll();
$homepageHighlights = array_slice($featuredProjects, 0, 3);

$trendingStmt = $pdo->prepare(
  'SELECT p.project_id, p.project_name, p.play_count, p.like_count, u.display_name
   FROM geckode_projects p
   JOIN geckode_users u ON u.user_id = p.owner_user_id
   ORDER BY (p.play_count + p.like_count * 2) DESC, p.project_id DESC
   LIMIT 5'
);
$trendingStmt->execute();
$trendingProjects = $trendingStmt->fetchAll();
$getStartedHref = $currentUser ? 'projects.php' : 'auth.php?panel=signup';

require __DIR__ . '/includes/header.php';
?>

<main id="main-content">
  <?php if ($flash): ?>
    <section class="notice notice-<?= htmlspecialchars($flash['type']) ?>" aria-live="polite">
      <?= htmlspecialchars($flash['message']) ?>
    </section>
  <?php endif; ?>

  <section class="hero" id="hero">
    <div class="hero-copy">
      <!-- <p class="eyebrow">Visual game builder for kids</p> -->
      <h1>Build Games.<br><span>Learn Code.</span></h1>
      <p class="hero-description">
        Geckode is a drag-and-drop platform that helps kids create interactive games and learn core programming ideas without typing complex syntax.
      </p>

      <div class="hero-actions">
        <a class="btn btn-primary btn-large" href="<?= $getStartedHref ?>">Get Started</a>
        <a class="btn btn-secondary btn-large" href="demo.php">See How It Works</a>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <div class="mock-window">
        <div class="mock-window-bar">
          <span></span><span></span><span></span>
        </div>
        <div class="mock-workspace">
          <aside class="mock-palette">
            <span class="mock-block mock-block-blue"></span>
            <span class="mock-block mock-block-green"></span>
            <span class="mock-block mock-block-orange"></span>
            <span class="mock-block mock-block-purple"></span>
          </aside>
          <div class="mock-stage">
            <div class="mock-sprite"><span></span></div>
            <div class="mock-block-stack">
              <span class="mock-block mock-block-blue mock-block-wide"></span>
              <span class="mock-block mock-block-green mock-block-medium"></span>
              <span class="mock-block mock-block-orange mock-block-short"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="why-section" id="why-geckode">
    <div class="section-heading section-heading-center">
      <div>
        <p class="eyebrow">Featured now</p>
        <h2>Projects highlighted in the app</h2>
        <p>These are the projects currently surfaced as featured across Geckode.</p>
      </div>
    </div>

    <?php if ($homepageHighlights): ?>
      <div class="feature-grid feature-project-grid">
        <?php foreach ($homepageHighlights as $project): ?>
          <article class="feature-card feature-project-card">
            <a class="feature-project-media<?= $project['thumbnail_url'] ? '' : ' project-thumb-placeholder' ?>" href="project_detail.php?id=<?= (int) $project['project_id'] ?>">
              <?php if ($project['thumbnail_url']): ?>
                <img src="<?= htmlspecialchars($project['thumbnail_url']) ?>" alt="<?= htmlspecialchars($project['project_name']) ?> thumbnail">
              <?php else: ?>
                <span>Project</span>
              <?php endif; ?>
            </a>
            <div class="feature-project-body">
              <p class="project-category-tag"><?= htmlspecialchars($project['category_name'] ?: 'Uncategorized') ?></p>
              <h3><?= htmlspecialchars($project['project_name']) ?></h3>
              <p><?= htmlspecialchars($project['description']) ?></p>
              <div class="project-meta">
                <span>By <?= htmlspecialchars($project['display_name']) ?></span>
                <span><?= (int) $project['play_count'] ?> plays</span>
                <span><?= (int) $project['like_count'] ?> likes</span>
              </div>
              <a class="project-link" href="project_detail.php?id=<?= (int) $project['project_id'] ?>">View details</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="project-empty">
        Featured projects will show up here once they have been marked in the app.
      </div>
    <?php endif; ?>
  </section>

  <section class="trending-section" id="trending">
    <div class="section-heading">
      <div>
        <p class="eyebrow">Trending now</p>
        <h2>Popular in the community</h2>
      </div>
    </div>

    <div class="trending-row">
      <?php foreach ($trendingProjects as $project): ?>
        <article class="trending-card">
          <h3><?= htmlspecialchars($project['project_name']) ?></h3>
          <p>By <?= htmlspecialchars($project['display_name']) ?></p>
          <p><?= (int) $project['play_count'] ?> plays • <?= (int) $project['like_count'] ?> likes</p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cta-band">
    <div>
      <p class="eyebrow">Ready to start building?</p>
      <h2>Join thousands of young creators.</h2>
    </div>
    <a class="btn btn-ghost btn-large" href="projects.php">Create your first game</a>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

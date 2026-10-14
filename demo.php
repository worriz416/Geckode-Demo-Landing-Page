<?php
/*
 * File: demo.php
 * Description: Renders the public interactive demo that previews Geckode's block-based coding experience.
 * Primary Owner: Liam Klein
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

$currentUser = geckode_current_user();
$flash = geckode_flash_get();

$geckodePageTitle = 'See How It Works';
$geckodeNavPrefix = 'demo.php';

require __DIR__ . '/includes/header.php';
?>

  <main id="main-content">
    <?php if ($flash): ?>
      <section class="notice notice-<?= htmlspecialchars($flash['type']) ?>" aria-live="polite">
        <?= htmlspecialchars($flash['message']) ?>
      </section>
    <?php endif; ?>

    <!-- Demo content goes here -->
    <div id="demo-wrapper">
      <aside id="spawn-sidebar"></aside>
      <div id="container">
        <div id="trash-zone">🗑️</div>
      </div>
    </div>

    <script src="./js/block-editor-core.js"></script>
    <script src="./js/demo.js"></script>

  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>

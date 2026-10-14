<?php
/*
 * File: includes/header.php
 * Description: Renders the shared site header, primary navigation, and authentication actions.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */
$geckodePageTitle = $geckodePageTitle ?? 'Geckode';
$geckodeNavPrefix = $geckodeNavPrefix ?? '';
$currentUser = $currentUser ?? null;
$geckodeExtraStylesheets = $geckodeExtraStylesheets ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($geckodePageTitle) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <?php foreach ($geckodeExtraStylesheets as $stylesheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet) ?>">
  <?php endforeach; ?>
</head>
<body>
  <a class="skip-link" href="#main-content">Skip to content</a>

  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="index.php" aria-label="Geckode home">
        <span class="brand-mark" aria-hidden="true">
          <span></span>
        </span>
        <span class="brand-text">Geckode</span>
      </a>

      <nav class="site-nav" id="site-nav" aria-label="Primary">
        <a href="organizations.php">Organizations</a>
        <?php if ($currentUser): ?>
          <a href="projects.php">My Projects</a>
        <?php endif; ?>
        <a href="demo.php">Demo</a>
      </nav>

      <div class="header-actions">
        <?php if ($currentUser): ?>
          <span class="signed-in-pill">Signed in as <?= htmlspecialchars($currentUser['display_name']) ?></span>
          <a class="btn btn-secondary" href="logout.php">Log out</a>
        <?php else: ?>
          <a class="btn btn-secondary" href="auth.php?panel=login">Log in</a>
          <a class="btn btn-primary" href="auth.php?panel=signup">Sign up</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

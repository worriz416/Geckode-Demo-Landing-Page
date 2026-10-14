<?php
/*
 * File: connect.php
 * Description: Configures the MySQL database connection, creates core Geckode tables, and seeds baseline data.
 * Primary Owners: Greg Forster and Team 418
 * Team: Team 418
 */

try {
  $pdo = new PDO(
    'mysql:host=localhost;dbname=forsteg_db;charset=utf8mb4',
    'forsteg_local',
    '>2aY>TxH'
  );
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    profile_picture_url VARCHAR(500) DEFAULT NULL,
    user_role VARCHAR(20) NOT NULL DEFAULT 'student',
    date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_organizations (
    org_id INT AUTO_INCREMENT PRIMARY KEY,
    org_name VARCHAR(255) NOT NULL,
    org_type VARCHAR(32) NOT NULL DEFAULT 'classroom',
    admin_user_id INT NOT NULL,
    description TEXT DEFAULT NULL,
    logo_url VARCHAR(500) DEFAULT NULL,
    date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_geckode_organizations_admin_user_id (admin_user_id),
    CONSTRAINT fk_geckode_organizations_admin_user
      FOREIGN KEY (admin_user_id) REFERENCES geckode_users(user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_org_members (
    membership_id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    user_id INT NOT NULL,
    org_role VARCHAR(20) NOT NULL DEFAULT 'student',
    joined_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_geckode_org_members_org_user (org_id, user_id),
    INDEX idx_geckode_org_members_user_id (user_id),
    CONSTRAINT fk_geckode_org_members_org
      FOREIGN KEY (org_id) REFERENCES geckode_organizations(org_id) ON DELETE CASCADE,
    CONSTRAINT fk_geckode_org_members_user
      FOREIGN KEY (user_id) REFERENCES geckode_users(user_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_project_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    category_description TEXT DEFAULT NULL,
    category_icon_url VARCHAR(500) DEFAULT NULL,
    display_order INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    code_data LONGTEXT DEFAULT NULL,
    sprites_data LONGTEXT DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    play_count INT NOT NULL DEFAULT 0,
    like_count INT NOT NULL DEFAULT 0,
    category_id INT DEFAULT NULL,
    project_type VARCHAR(32) NOT NULL DEFAULT 'personal',
    privacy VARCHAR(16) NOT NULL DEFAULT 'private',
    owner_user_id INT NOT NULL,
    date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_geckode_projects_owner_user_id (owner_user_id),
    INDEX idx_geckode_projects_category_id (category_id),
    CONSTRAINT fk_geckode_projects_owner_user
      FOREIGN KEY (owner_user_id) REFERENCES geckode_users(user_id),
    CONSTRAINT fk_geckode_projects_category
      FOREIGN KEY (category_id) REFERENCES geckode_project_categories(category_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_project_collaborators (
    collaborator_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    project_role VARCHAR(16) NOT NULL DEFAULT 'viewer',
    joined_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_geckode_project_collaborators_project_user (project_id, user_id),
    INDEX idx_geckode_project_collaborators_user_id (user_id),
    CONSTRAINT fk_geckode_project_collaborators_project
      FOREIGN KEY (project_id) REFERENCES geckode_projects(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_geckode_project_collaborators_user
      FOREIGN KEY (user_id) REFERENCES geckode_users(user_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_project_blocks (
    block_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    block_order INT NOT NULL,
    block_label VARCHAR(255) NOT NULL,
    block_color VARCHAR(50) NOT NULL,
    position_left DOUBLE NOT NULL DEFAULT 0,
    position_top DOUBLE NOT NULL DEFAULT 0,
    date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_geckode_project_blocks_project_id (project_id),
    CONSTRAINT fk_geckode_project_blocks_project
      FOREIGN KEY (project_id) REFERENCES geckode_projects(project_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $pdo->exec("CREATE TABLE IF NOT EXISTS geckode_project_block_connections (
    connection_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    block_id_1 INT NOT NULL,
    block_id_2 INT NOT NULL,
    date_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_geckode_project_block_connections_pair (project_id, block_id_1, block_id_2),
    INDEX idx_geckode_project_block_connections_block_1 (block_id_1),
    INDEX idx_geckode_project_block_connections_block_2 (block_id_2),
    CONSTRAINT fk_geckode_project_block_connections_project
      FOREIGN KEY (project_id) REFERENCES geckode_projects(project_id) ON DELETE CASCADE,
    CONSTRAINT fk_geckode_project_block_connections_block_1
      FOREIGN KEY (block_id_1) REFERENCES geckode_project_blocks(block_id) ON DELETE CASCADE,
    CONSTRAINT fk_geckode_project_block_connections_block_2
      FOREIGN KEY (block_id_2) REFERENCES geckode_project_blocks(block_id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $demoUserStatement = $pdo->prepare("INSERT IGNORE INTO geckode_users (
    username,
    email,
    password_hash,
    display_name,
    profile_picture_url,
    user_role
  ) VALUES (
    :username,
    :email,
    :password_hash,
    :display_name,
    :profile_picture_url,
    :user_role
  )");

  $demoUserStatement->execute([
    ':username' => 'geckode-demo',
    ':email' => 'demo@geckode.local',
    ':password_hash' => password_hash('Geckode123!', PASSWORD_DEFAULT),
    ':display_name' => 'Demo Student',
    ':profile_picture_url' => null,
    ':user_role' => 'student',
  ]);

  $pdo->exec("INSERT IGNORE INTO geckode_project_categories (
    category_id,
    category_name,
    category_description,
    display_order
  ) VALUES
    (1, 'Games', 'Simple games made by the Geckode community.', 1),
    (2, 'Stories', 'Interactive story projects and adventures.', 2),
    (3, 'Animations', 'Creative animations and motion projects.', 3),
    (4, 'Puzzles', 'Logic games and puzzle experiences.', 4)");

  $demoUserId = (int) $pdo->query("SELECT user_id FROM geckode_users WHERE username = 'geckode-demo' LIMIT 1")->fetchColumn();
  $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM geckode_projects')->fetchColumn();

  if ($projectCount === 0 && $demoUserId > 0) {
    $projectStatement = $pdo->prepare("INSERT INTO geckode_projects (
      owner_user_id,
      category_id,
      project_name,
      description,
      thumbnail_url,
      code_data,
      sprites_data,
      is_featured,
      play_count,
      like_count,
      project_type,
      privacy
    ) VALUES (
      :owner_user_id,
      :category_id,
      :project_name,
      :description,
      :thumbnail_url,
      :code_data,
      :sprites_data,
      :is_featured,
      :play_count,
      :like_count,
      :project_type,
      :privacy
    )");

    $seedProjects = [
      [1, 'Rocket Rescue', 'Guide a rocket through space and collect stars while avoiding drifting asteroids.', '/images/rocket-rescue.jpg', 'when start -> move rocket', '{}', 1, 248, 61],
      [4, 'Jungle Quest', 'A short platform puzzle where players jump across bridges and find hidden gems.', '/images/jungle-quest.jpg', 'when key pressed -> jump', '{}', 1, 191, 47],
      [3, 'Galaxy Light Show', 'Animated stars pulse and change colour to create a bright space performance.', '/images/galaxy-light-show.webp', 'repeat forever -> glow', '{}', 1, 164, 39],
      [2, 'Pet Adventure Story', 'Click through a choose-your-own-adventure story about a lost classroom pet.', null, 'when clicked -> next scene', '{}', 0, 129, 35],
      [1, 'Castle Escape', 'Move through a maze and unlock doors before the timer reaches zero.', null, 'if key touched -> open door', '{}', 0, 302, 74],
      [3, 'Dance Party Studio', 'Sprites dance in sync with music and flashing background effects.', null, 'play beat -> dance', '{}', 0, 275, 58],
      [4, 'Math Match', 'Match quick math answers to falling tiles before they hit the floor.', null, 'if answer correct -> score +1', '{}', 0, 233, 52],
      [2, 'Ocean Explorer', 'Explore underwater scenes and click sea creatures to learn fun facts.', null, 'click creature -> show fact', '{}', 0, 187, 44],
    ];

    foreach ($seedProjects as $project) {
      $projectStatement->execute([
        ':owner_user_id' => $demoUserId,
        ':category_id' => $project[0],
        ':project_name' => $project[1],
        ':description' => $project[2],
        ':thumbnail_url' => $project[3],
        ':code_data' => $project[4],
        ':sprites_data' => $project[5],
        ':is_featured' => $project[6],
        ':play_count' => $project[7],
        ':like_count' => $project[8],
        ':project_type' => 'personal',
        ':privacy' => 'public',
      ]);
    }
  }

  $thumbnailBackfillStatement = $pdo->prepare(
    "UPDATE geckode_projects
     SET thumbnail_url = :thumbnail_url,
         date_updated = CURRENT_TIMESTAMP
     WHERE project_name = :project_name
       AND (thumbnail_url IS NULL OR TRIM(COALESCE(thumbnail_url, '')) = '')"
  );

  foreach ([
    'Rocket Rescue' => '/images/rocket-rescue.jpg',
    'Jungle Quest' => '/images/jungle-quest.jpg',
    'Galaxy Light Show' => '/images/galaxy-light-show.webp',
  ] as $projectName => $thumbnailUrl) {
    $thumbnailBackfillStatement->execute([
      ':project_name' => $projectName,
      ':thumbnail_url' => $thumbnailUrl,
    ]);
  }
} catch (PDOException $e) {
  die('Connection failed: ' . $e->getMessage());
}

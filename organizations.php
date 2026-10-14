<?php
/*
 * File: organizations.php
 * Description: Manages organization creation, membership, and roster workflows for classroom collaboration.
 * Primary Owner: Thuvarahan Jeyaseelan
 * Team: Team 418
 */
$geckodePageTitle = 'Organizations – Geckode';
$geckodeNavPrefix = 'index.php';
$geckodeExtraStylesheets = ['css/organizations.css'];
require_once __DIR__ . '/bootstrap.php';

/* ── guard: must be logged in ── */
if (!geckode_is_logged_in()) {
    geckode_flash_set('error', 'Please log in to manage organizations.');
    geckode_redirect('login.php');
}

$currentUser = geckode_current_user();

/* ================================================================
   ACTION HANDLERS (POST)
   ================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* ── Create Organization ── */
    if ($action === 'create_org') {
        $name = trim($_POST['org_name'] ?? '');
        $type = $_POST['org_type'] ?? 'classroom';

        if ($name === '') {
            geckode_flash_set('error', 'Organization name cannot be empty.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO geckode_organizations (org_name, org_type, admin_user_id)
                 VALUES (:name, :type, :uid)"
            );
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':uid'  => $currentUser['user_id'],
            ]);
            $newOrgId = $pdo->lastInsertId();

            /* auto-add creator as admin member */
            $stmt = $pdo->prepare(
                "INSERT INTO geckode_org_members (org_id, user_id, org_role)
                 VALUES (:oid, :uid, 'admin')"
            );
            $stmt->execute([':oid' => $newOrgId, ':uid' => $currentUser['user_id']]);

            geckode_flash_set('success', "Organization \"{$name}\" created!");
        }
        geckode_redirect('organizations.php');
    }

    /* ── Invite Member (by username) ── */
    if ($action === 'invite_member') {
        $orgId    = (int)($_POST['org_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $role     = $_POST['role'] ?? 'student';

        $check = $pdo->prepare(
            "SELECT 1 FROM geckode_org_members
              WHERE org_id = :oid AND user_id = :uid AND org_role = 'admin'"
        );
        $check->execute([':oid' => $orgId, ':uid' => $currentUser['user_id']]);

        if (!$check->fetch()) {
            geckode_flash_set('error', 'You are not an admin of this organization.');
        } elseif ($username === '') {
            geckode_flash_set('error', 'Please enter a username.');
        } else {
            $find = $pdo->prepare("SELECT user_id FROM geckode_users WHERE username = :u");
            $find->execute([':u' => $username]);
            $target = $find->fetch();

            if (!$target) {
                geckode_flash_set('error', "User \"{$username}\" not found.");
            } else {
                $ins = $pdo->prepare(
                    "INSERT IGNORE INTO geckode_org_members (org_id, user_id, org_role)
                     VALUES (:oid, :uid, :role)"
                );
                $ins->execute([
                    ':oid'  => $orgId,
                    ':uid'  => $target['user_id'],
                    ':role' => $role,
                ]);
                geckode_flash_set('success', "\"{$username}\" added to the organization.");
            }
        }
        geckode_redirect('organizations.php?view=' . $orgId);
    }

    /* ── Remove Member ── */
    if ($action === 'remove_member') {
        $orgId    = (int)($_POST['org_id'] ?? 0);
        $memberId = (int)($_POST['membership_id'] ?? 0);

        $check = $pdo->prepare(
            "SELECT 1 FROM geckode_org_members
              WHERE org_id = :oid AND user_id = :uid AND org_role = 'admin'"
        );
        $check->execute([':oid' => $orgId, ':uid' => $currentUser['user_id']]);

        if ($check->fetch()) {
            $del = $pdo->prepare("DELETE FROM geckode_org_members WHERE membership_id = :mid AND org_id = :oid");
            $del->execute([':mid' => $memberId, ':oid' => $orgId]);
            geckode_flash_set('success', 'Member removed.');
        }
        geckode_redirect('organizations.php?view=' . $orgId);
    }
}

/* ================================================================
   DATA FOR THE PAGE
   ================================================================ */
$myOrgs = $pdo->prepare(
    "SELECT o.*, om.org_role AS my_role
       FROM geckode_organizations o
       JOIN geckode_org_members om ON om.org_id = o.org_id
      WHERE om.user_id = :uid
      ORDER BY o.date_created DESC"
);
$myOrgs->execute([':uid' => $currentUser['user_id']]);
$orgList = $myOrgs->fetchAll();

$viewOrgId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$activeOrg = null;
$members   = [];

if ($viewOrgId) {
    $stmt = $pdo->prepare("SELECT * FROM geckode_organizations WHERE org_id = :oid");
    $stmt->execute([':oid' => $viewOrgId]);
    $activeOrg = $stmt->fetch();

    if ($activeOrg) {
        $stmt = $pdo->prepare(
            "SELECT om.membership_id, om.org_role, om.joined_date,
                    u.username, u.display_name, u.email
               FROM geckode_org_members om
               JOIN geckode_users u ON u.user_id = om.user_id
              WHERE om.org_id = :oid
              ORDER BY om.joined_date ASC"
        );
        $stmt->execute([':oid' => $viewOrgId]);
        $members = $stmt->fetchAll();
    }
}

$isAdmin = false;
foreach ($orgList as $o) {
    if ($o['org_id'] == $viewOrgId && $o['my_role'] === 'admin') {
        $isAdmin = true;
        break;
    }
}

$flash = geckode_flash_get();

require_once __DIR__ . '/includes/header.php';
?>

<div class="org-page" id="main-content">

  <!-- ── SIDEBAR ── -->
  <aside class="org-sidebar">
    <p class="org-sidebar-heading">Your Organizations</p>

    <?php if (empty($orgList)): ?>
      <p class="empty-hint">None yet — create one!</p>
    <?php else: ?>
      <ul class="org-list">
        <?php foreach ($orgList as $o): ?>
          <li>
            <a href="organizations.php?view=<?= $o['org_id'] ?>"
               class="<?= $viewOrgId == $o['org_id'] ? 'is-active' : '' ?>">
              <span><?= htmlspecialchars($o['org_name']) ?></span>
              <span class="org-badge org-badge-<?= $o['my_role'] ?>">
                <?= $o['my_role'] ?>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <a href="organizations.php" class="btn btn-primary btn-block">+ Create New Org</a>
  </aside>

  <!-- ── MAIN CONTENT ── -->
  <section class="main-column">

    <?php if ($flash): ?>
      <div class="notice notice-<?= $flash['type'] ?> org-page-flash">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- ── CREATE FORM ── -->
    <div class="org-card">
      <p class="eyebrow">Dashboard</p>
      <h2>Create Organization</h2>
      <p class="org-sub">Start a new hub for your creative coding team.</p>

      <form method="POST" action="organizations.php">
        <input type="hidden" name="action" value="create_org">
        <div class="org-form-row">
          <div class="field">
            <span>Organization Name</span>
            <input type="text" name="org_name"
                   placeholder="e.g. Oakwood Elementary Coding Club" required>
          </div>
          <div class="field">
            <span>Type</span>
            <select name="org_type">
              <option value="classroom">Classroom</option>
              <option value="school">School</option>
              <option value="community_group">Community Group</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-tall">
            Create Organization
          </button>
        </div>
      </form>
    </div>

    <!-- ── ROSTER (when viewing an org) ── -->
    <?php if ($activeOrg): ?>
    <div class="org-card">
      <p class="eyebrow">Editing: <?= htmlspecialchars($activeOrg['org_name']) ?></p>
      <h2><?= htmlspecialchars($activeOrg['org_name']) ?></h2>
      <p class="org-sub">
        <?= ucfirst(str_replace('_', ' ', $activeOrg['org_type'])) ?>
        &middot; Created <?= date('M j, Y', strtotime($activeOrg['date_created'])) ?>
      </p>

      <h3 class="org-members-heading">
        Organization Members
      </h3>

      <?php if (empty($members)): ?>
        <p class="empty-note">No members yet.</p>
      <?php else: ?>
        <table class="org-roster">
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Role</th>
              <th>Joined</th>
              <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
              <td><?= htmlspecialchars($m['display_name']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($m['username']) ?></td>
              <td>
                <span class="org-badge org-badge-<?= $m['org_role'] ?>">
                  <?= $m['org_role'] ?>
                </span>
              </td>
              <td><?= date('M j, Y', strtotime($m['joined_date'])) ?></td>
              <?php if ($isAdmin): ?>
              <td>
                <form method="POST" class="form-inline"
                      onsubmit="return confirm('Remove this member?');">
                  <input type="hidden" name="action" value="remove_member">
                  <input type="hidden" name="org_id" value="<?= $activeOrg['org_id'] ?>">
                  <input type="hidden" name="membership_id" value="<?= $m['membership_id'] ?>">
                  <button type="submit" class="btn-danger">Remove</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- ── INVITE FORM (admin only) ── -->
      <?php if ($isAdmin): ?>
      <hr class="org-divider">
      <h3 class="org-invite-heading">
        + Invite New Member
      </h3>
      <form method="POST" action="organizations.php">
        <input type="hidden" name="action" value="invite_member">
        <input type="hidden" name="org_id" value="<?= $activeOrg['org_id'] ?>">
        <div class="org-form-row">
          <div class="field">
            <span>Username</span>
            <input type="text" name="username" placeholder="Enter username" required>
          </div>
          <div class="field">
            <span>Role</span>
            <select name="role">
              <option value="student">Student</option>
              <option value="educator">Educator</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-tall">
            Invite
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

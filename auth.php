<?php
/*
 * File: auth.php
 * Description: Renders the combined sign up and log in experience for Geckode users.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

$currentUser = geckode_current_user();
$flash = geckode_flash_get();
$formState = $_SESSION['geckode_form'] ?? [];
$signupValues = $formState['signup'] ?? [];
$loginValues = $formState['login'] ?? [];
unset($_SESSION['geckode_form']);
$panel = ($_GET['panel'] ?? '') === 'login' ? 'login' : 'signup';

$geckodePageTitle = 'Sign up or log in | Geckode';
$geckodeNavPrefix = 'index.php';

require __DIR__ . '/includes/header.php';
?>

  <main id="main-content" class="auth-page">
    <?php if ($flash): ?>
      <section class="notice notice-<?= htmlspecialchars($flash['type']) ?>" aria-live="polite">
        <?= htmlspecialchars($flash['message']) ?>
      </section>
    <?php endif; ?>

    <section class="auth-section" id="auth">
      <div class="auth-panel" data-auth-panel>
        <div class="auth-tabs" role="tablist" aria-label="Authentication options">
          <button
            class="auth-tab <?= $panel === 'signup' ? 'is-active' : '' ?>"
            type="button"
            role="tab"
            aria-selected="<?= $panel === 'signup' ? 'true' : 'false' ?>"
            data-form-target="signup"
          >
            Sign up
          </button>
          <button
            class="auth-tab <?= $panel === 'login' ? 'is-active' : '' ?>"
            type="button"
            role="tab"
            aria-selected="<?= $panel === 'login' ? 'true' : 'false' ?>"
            data-form-target="login"
          >
            Log in
          </button>
        </div>

        <div class="auth-grid">
          <section class="auth-card <?= $panel === 'signup' ? 'is-active' : '' ?>" data-auth-form="signup">
            <h3>Create your account</h3>
            <p>Choose a username to get started. You can change how your name appears later.</p>

            <form action="signup.php" method="post" novalidate data-validate-form="signup">
              <label class="field">
                <span>Username</span>
                <input type="text" name="username" maxlength="255" autocomplete="username" required value="<?= htmlspecialchars($signupValues['username'] ?? '') ?>">
                <small class="field-error"></small>
              </label>

              <label class="field">
                <span>Email</span>
                <input type="email" name="email" maxlength="255" autocomplete="email" required value="<?= htmlspecialchars($signupValues['email'] ?? '') ?>">
                <small class="field-error"></small>
              </label>

              <label class="field">
                <span>Password</span>
                <input type="password" name="password" minlength="8" autocomplete="new-password" required>
                <small class="field-error"></small>
              </label>

              <button class="btn btn-primary btn-block" type="submit">Create account</button>
            </form>
          </section>

          <section class="auth-card <?= $panel === 'login' ? 'is-active' : '' ?>" data-auth-form="login">
            <h3>Welcome back</h3>
            <p>Use your email and password to sign in.</p>

            <form action="login.php" method="post" novalidate data-validate-form="login">
              <label class="field">
                <span>Email</span>
                <input type="email" name="email" maxlength="255" autocomplete="email" required value="<?= htmlspecialchars($loginValues['email'] ?? '') ?>">
                <small class="field-error"></small>
              </label>

              <label class="field">
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
                <small class="field-error"></small>
              </label>

              <button class="btn btn-primary btn-block" type="submit">Log in</button>
            </form>
          </section>
        </div>
      </div>
    </section>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>

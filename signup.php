<?php
/*
 * File: signup.php
 * Description: Processes account creation requests and stores new Geckode users in the database.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  geckode_redirect('auth.php?panel=signup');
}

$username = trim((string) filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
$emailInput = trim((string) filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW));
$email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
$password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

$_SESSION['geckode_form'] = [
  'signup' => [
    'username' => $username,
    'email' => $emailInput,
  ],
];

$errors = [];

if ($username === '') {
  $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_]{3,255}$/', $username)) {
  $errors[] = 'Username must be 3 to 255 characters and can only use letters, numbers, and underscores.';
}

if ($emailInput === '') {
  $errors[] = 'Email is required.';
} elseif (!$email) {
  $errors[] = 'Enter a valid email address.';
}

if (strlen($password) < 8) {
  $errors[] = 'Password must be at least 8 characters long.';
}

if ($errors) {
  geckode_flash_set('error', implode(' ', $errors));
  geckode_redirect('auth.php?panel=signup');
}

$duplicate = $pdo->prepare('SELECT user_id FROM geckode_users WHERE username = ? OR email = ? LIMIT 1');
$duplicate->execute([$username, $email]);

if ($duplicate->fetch()) {
  geckode_flash_set('error', 'That username or email is already in use.');
  geckode_redirect('auth.php?panel=signup');
}

$displayName = $username;

$stmt = $pdo->prepare('INSERT INTO geckode_users (
  username,
  email,
  password_hash,
  display_name,
  profile_picture_url,
  user_role,
  date_created,
  date_updated
) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');

$stmt->execute([
  $username,
  $email,
  password_hash($password, PASSWORD_DEFAULT),
  $displayName,
  null,
  'student',
]);

$userId = (int) $pdo->lastInsertId();
$sessionUser = [
  'user_id' => $userId,
  'username' => $username,
  'email' => $email,
  'display_name' => $displayName,
  'profile_picture_url' => null,
  'user_role' => 'student',
];

session_regenerate_id(true);
$_SESSION['geckode_user'] = $sessionUser;
unset($_SESSION['geckode_form']);
geckode_flash_set('success', 'Your Geckode account has been created.');

geckode_redirect('index.php');

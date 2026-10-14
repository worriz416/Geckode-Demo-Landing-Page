<?php
/*
 * File: login.php
 * Description: Processes log in submissions, validates credentials, and starts authenticated sessions.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  geckode_redirect('auth.php?panel=login');
}

$emailInput = trim((string) filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW));
$email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
$password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

$_SESSION['geckode_form'] = [
  'login' => [
    'email' => $emailInput,
  ],
];

$errors = [];

if ($emailInput === '') {
  $errors[] = 'Email is required.';
} elseif (!$email) {
  $errors[] = 'Enter a valid email address.';
}

if ($password === '') {
  $errors[] = 'Password is required.';
}

if ($errors) {
  geckode_flash_set('error', implode(' ', $errors));
  geckode_redirect('auth.php?panel=login');
}

$stmt = $pdo->prepare('SELECT user_id, username, email, password_hash, display_name, profile_picture_url, user_role FROM geckode_users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
  geckode_flash_set('error', 'The email or password was not recognized.');
  geckode_redirect('auth.php?panel=login');
}

session_regenerate_id(true);
$_SESSION['geckode_user'] = [
  'user_id' => (int) $user['user_id'],
  'username' => $user['username'],
  'email' => $user['email'],
  'display_name' => $user['display_name'],
  'profile_picture_url' => $user['profile_picture_url'],
  'user_role' => $user['user_role'],
];

unset($_SESSION['geckode_form']);
geckode_flash_set('success', 'Welcome back to Geckode, ' . $user['display_name'] . '.');

geckode_redirect('index.php');

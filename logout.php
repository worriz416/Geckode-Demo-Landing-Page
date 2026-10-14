<?php
/*
 * File: logout.php
 * Description: Ends the current user session and returns the user to the authentication flow.
 * Primary Owner: Greg Forster
 * Team: Team 418
 */
require_once __DIR__ . '/bootstrap.php';

$_SESSION = [];
unset($_SESSION['geckode_form']);

if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
  );
}

session_destroy();
session_start();
geckode_flash_set('success', 'You have been signed out.');

geckode_redirect('auth.php?panel=login');

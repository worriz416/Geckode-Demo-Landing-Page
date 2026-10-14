<?php
/*
 * File: bootstrap.php
 * Description: Initializes shared session helpers, authentication state, and common redirect/flash utilities.
 * Primary Owners: Greg Forster and Team 418
 * Team: Team 418
 */
session_start();

require_once __DIR__ . '/connect.php';

/**
 * Returns the authenticated Geckode user stored in the session.
 *
 * @return array<string, mixed>|null Logged-in user data, or null when no user is signed in.
 */
function geckode_current_user() {
  return $_SESSION['geckode_user'] ?? null;
}

/**
 * Indicates whether the current request has an authenticated session.
 *
 * @return bool True when a user is logged in, otherwise false.
 */
function geckode_is_logged_in() {
  return geckode_current_user() !== null;
}

/**
 * Stores a flash message for display on the next request.
 *
 * @param string $type Flash message type such as success or error.
 * @param string $message Human-readable message to display.
 * @return void
 */
function geckode_flash_set($type, $message) {
  $_SESSION['geckode_flash'] = [
    'type' => $type,
    'message' => $message,
  ];
}

/**
 * Retrieves and clears the current flash message from the session.
 *
 * @return array<string, string>|null Flash payload with type and message, or null when absent.
 */
function geckode_flash_get() {
  if (!isset($_SESSION['geckode_flash'])) {
    return null;
  }

  $flash = $_SESSION['geckode_flash'];
  unset($_SESSION['geckode_flash']);
  return $flash;
}

/**
 * Sends an HTTP redirect and terminates the current request.
 *
 * @param string $path Application-relative destination path.
 * @return void
 */
function geckode_redirect($path) {
  header('Location: ' . $path);
  exit;
}

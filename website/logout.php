<?php
/**
 * Logout Handler
 * Destroys session and redirects to home
 * Last Modified: 2025-11-30
 */

session_start();
session_destroy();

header('Location: index.php');
exit;

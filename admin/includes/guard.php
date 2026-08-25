<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

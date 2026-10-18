<?php
require_once __DIR__ . '/../config/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
unset($_SESSION['is_admin']);
session_regenerate_id(true);
header('Location: ../index.php');
exit;

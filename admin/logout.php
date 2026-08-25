<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['is_admin']);
header('Location: ../index.php');
exit;

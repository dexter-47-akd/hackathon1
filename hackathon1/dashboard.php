<?php
require_once 'includes/auth.php';

requireLogin();

$userType = getUserType();

switch ($userType) {
    case 'vendor':
        header('Location: vendor-dashboard.php');
        break;
    case 'supplier':
        header('Location: supplier-dashboard.php');
        break;
    case 'consumer':
        header('Location: index.php');
        break;
    default:
        header('Location: index.php');
}
exit;
?>

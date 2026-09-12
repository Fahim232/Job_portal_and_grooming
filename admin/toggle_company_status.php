<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$msg_success = '';
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Your session expired. Please refresh and try again.';
    } else {
        $cid = (int)$_POST['company_id'];
        $new_status = $_POST['status'] ?? '';
        $allowed = ['active', 'inactive', 'pending', 'rejected'];
        if (in_array($new_status, $allowed, true)) {
            $stmt = mysqli_prepare($con, "UPDATE companies SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $cid);
            if (mysqli_stmt_execute($stmt)) {
                $msg_success = 'Company status updated successfully.';
            } else {
                $msg_error = 'Could not update company status.';
            }
        } else {
            $msg_error = 'Invalid status value.';
        }
    }
} elseif (isset($_GET['company_id'], $_GET['status'])) {
    $cid = (int)$_GET['company_id'];
    $new_status = $_GET['status'];
    $allowed = ['active', 'inactive', 'pending', 'rejected'];
    if (in_array($new_status, $allowed, true)) {
        $stmt = mysqli_prepare($con, "UPDATE companies SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $cid);
        mysqli_stmt_execute($stmt);
    }
    header('Location: showdata.php');
    exit();
}

if (isset($_GET['company_id'], $_GET['q']) && $_GET['q'] === 'toggle') {
    $cid = (int)$_GET['company_id'];
    $q = mysqli_query($con, "SELECT status FROM companies WHERE id = $cid");
    if ($q && $row = mysqli_fetch_assoc($q)) {
        $toggle = $row['status'] === 'active' ? 'inactive' : 'active';
        mysqli_query($con, "UPDATE companies SET status = '$toggle' WHERE id = $cid");
    }
    header('Location: showdata.php');
    exit();
}

header('Location: showdata.php');
exit();
<?php
session_start();
include __DIR__ . '/../config/config.php';
include __DIR__ . '/../config/audit.php';

// Only admin + leader can resolve
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['leader','admin'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    header("Location: admin_flagged.php");
    exit();
}

$resolverId   = (int)$_SESSION['user_id'];
$resolverName = $conn->real_escape_string($_SESSION['username']);
$resolverRole = $conn->real_escape_string($_SESSION['role']);



$conn->query("
    UPDATE flagged_feedback
    SET
        status = 'resolved',
        resolved_at = NOW(),
        resolved_by = $resolverId,
        resolved_by_name = '$resolverName',
        resolved_by_role = '$resolverRole'
    WHERE id = $id
");

// Audit log
audit_log($conn, $resolverId, "Resolved Flagged Feedback", "Flag ID #$id");

// Redirect back to alerts page
header("Location: admin_flagged.php");
exit();

<?php
session_start();
include __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','leader'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: announcements.php");
    exit();
}

// Fetch announcement
$res = $conn->query("SELECT leader_id FROM announcements WHERE id = $id");
if (!$res || $res->num_rows === 0) {
    header("Location: announcements.php");
    exit();
}

$row = $res->fetch_assoc();

// Permission check
if ($role === 'leader' && $row['leader_id'] != $userId) {
    header("Location: announcements.php");
    exit();
}

// Delete
$conn->query("DELETE FROM announcements WHERE id = $id");

header("Location: announcements.php");
exit();

<?php
session_start();
require_once __DIR__ . '/../includes/db_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/manage_announcements.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? '';

if ($user_role !== 'bookstore') {
    die("Unauthorized access.");
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (empty($title) || empty($message)) {
            $_SESSION['error'] = "Title and message are required.";
        } else {
            if (addAnnouncement($user_id, $title, $message, $link, $startDate, $endDate)) {
                $_SESSION['success'] = "Announcement posted successfully!";
            } else {
                $_SESSION['error'] = "Failed to post announcement.";
            }
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (empty($title) || empty($message)) {
            $_SESSION['error'] = "Title and message are required.";
        } else {
            if (updateAnnouncement($id, $user_id, $title, $message, $link, $startDate, $endDate)) {
                $_SESSION['success'] = "Announcement updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update announcement.";
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if (deleteAnnouncement($id, $user_id)) {
            $_SESSION['success'] = "Announcement deleted.";
        } else {
            $_SESSION['error'] = "Failed to delete announcement.";
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../pages/manage_announcements.php');
exit;

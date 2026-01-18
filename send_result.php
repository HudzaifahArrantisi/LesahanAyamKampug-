<?php
// send_results.php
session_start();
include 'config.php';
include 'functions.php';

$amount = floatval($_POST['amount']);
$description = sanitize_input($_POST['description']);

$sql = "INSERT INTO daily_reports (amount, description, created_by) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("dsi", $amount, $description, $_SESSION['user_id']);
$stmt->execute();

// Reset user's income to zero
$sql_reset = "UPDATE daily_income SET amount = 0 WHERE created_by = ?";
$stmt_reset = $conn->prepare($sql_reset);
$stmt_reset->bind_param("i", $_SESSION['user_id']);
$stmt_reset->execute();

echo json_encode(['success' => true]);
?>
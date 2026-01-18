<?php
// update_transaction_status.php
session_start();
include 'config.php';
include 'functions.php';

$bon_id = intval($_POST['bon_id']);

$sql = "UPDATE bon SET status = 'completed' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bon_id);
$stmt->execute();

echo json_encode(['success' => true]);
?>
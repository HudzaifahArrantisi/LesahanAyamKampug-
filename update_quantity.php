<?php
// update_quantity.php
session_start();
include 'config.php';
include 'functions.php';

$item_detail_id = intval($_POST['item_detail_id']);
$new_quantity = intval($_POST['quantity']);

if ($new_quantity <= 0) {
    $sql = "DELETE FROM bon_detail WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_detail_id);
    $stmt->execute();
} else {
    $sql = "UPDATE bon_detail SET quantity = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_quantity, $item_detail_id);
    $stmt->execute();
}

echo json_encode(['success' => true]);
?>
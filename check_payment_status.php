<?php
// check_payment.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

// In a real implementation, you would check with Midtrans API
// For this example, we'll simulate a response

// Simulate checking payment status with Midtrans
$statuses = ['pending', 'settlement', 'expire'];
$random_status = $statuses[array_rand($statuses)];

echo json_encode([
    'order_id' => $order_id,
    'transaction_status' => $random_status,
    'status_message' => 'Simulated status check'
]);
<?php
// process_qris.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Include Midtrans library

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$bon_id = $input['bon_id'] ?? 0;
$amount = $input['amount'] ?? 0;

if (!$bon_id || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Generate Midtrans transaction
$result = createMidtransTransaction($bon_id, $amount);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'order_id' => $result['order_id'],
        'qr_code' => $result['qr_code']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}
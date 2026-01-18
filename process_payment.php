<?php
// process_payment.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$bon_id = $_POST['bon_id'] ?? null;
$payment_method = $_POST['payment_method'] ?? null;

if (!$bon_id || !$payment_method) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Get bon details
$bon = getBon($bon_id);
if (!$bon) {
    echo json_encode(['success' => false, 'message' => 'Bon not found']);
    exit;
}

$totals = calculateBonTotals($bon_id);
$total_amount = $totals['total_amount'];

if ($total_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit;
}

// Process based on payment method
if ($payment_method === 'cash') {
    // Update bon status to paid
    $success = updateBonStatus($bon_id, 'paid', 'cash');
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Cash payment processed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process cash payment']);
    }
} elseif ($payment_method === 'qris') {
    // Create Midtrans transaction
    $result = createMidtransTransaction($bon_id, $total_amount);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'order_id' => $result['order_id'],
            'qr_code' => $result['qr_code'],
            'message' => 'QRIS transaction created successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to create QRIS transaction'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
}
<?php
// webhook_doku.php
require_once __DIR__ . '/config.php';

// Get the raw POST data
$input = file_get_contents('php://input');
$headers = getallheaders();

// Log the webhook request (for debugging)
file_put_contents('doku_webhook.log', date('Y-m-d H:i:s') . " - Headers: " . json_encode($headers) . "\nBody: " . $input . "\n\n", FILE_APPEND);

try {
    // Verify signature (you should implement proper signature verification)
    $signature = $headers['Signature'] ?? '';
    
    // Decode the webhook data
    $webhookData = json_decode($input, true);
    
    if ($webhookData && isset($webhookData['order']['invoice_number'])) {
        $order_id = $webhookData['order']['invoice_number'];
        $status = $webhookData['transaction']['status'];
        
        // Map DOKU status to your system status
        $statusMap = [
            'SUCCESS' => 'paid',
            'FAILED' => 'failed',
            'PENDING' => 'pending'
        ];
        
        $mappedStatus = $statusMap[$status] ?? 'pending';
        
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = ?, updated_at = NOW() 
            WHERE order_id = ?
        ");
        $stmt->execute([$mappedStatus, $order_id]);
        
        // If payment is successful, update the bon
        if ($mappedStatus === 'paid') {
            // Get bon_id from transaction
            $stmt = $pdo->prepare("SELECT bon_id FROM transactions WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                $bon_id = $transaction['bon_id'];
                
                // Update bon status to completed
                $pdo->prepare("UPDATE bon SET status='completed', payment_date=NOW() WHERE id=?")->execute([$bon_id]);
                
                // Archive the bon
                $pdo->prepare("UPDATE bon SET archived=1 WHERE id=?")->execute([$bon_id]);
                
                // Save to bon_history
                $bon = getBon($bon_id);
                $stmt = $pdo->prepare("
                    INSERT INTO bon_history 
                    (table_number, created_at, updated_at, status, created_by, archived, payment_date, total_amount) 
                    VALUES (?, ?, ?, 'completed', ?, 1, ?, ?)
                ");
                $stmt->execute([
                    $bon['table_number'], 
                    $bon['created_at'], 
                    $bon['updated_at'], 
                    $bon['created_by'], 
                    date('Y-m-d H:i:s'),
                    calculateBonTotals($bon_id)['total_amount']
                ]);
            }
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
        
    } else {
        throw new Exception('Invalid webhook data');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Helper functions (you might need to define these or include functions.php)
function getBon($bon_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bon WHERE id = ?");
    $stmt->execute([$bon_id]);
    return $stmt->fetch();
}

function calculateBonTotals($bon_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(subtotal) as total_amount FROM bon_detail WHERE bon_id = ?");
    $stmt->execute([$bon_id]);
    return $stmt->fetch();
}
?>
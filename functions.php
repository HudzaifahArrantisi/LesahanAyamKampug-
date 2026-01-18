<?php
// functions.php
require_once __DIR__ . '/config.php';

// buat Login nya
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Create a bon with details (items)
 * $items = [ ['menu_id'=>1, 'quantity'=>2], ... ]
 * returns inserted bon_id
 */
function createBon($table_number, $created_by, $items = []) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO bon (table_number, created_by, status) VALUES (:table_number, :created_by, 'active')");
        $stmt->execute([
            ':table_number' => $table_number,
            ':created_by' => $created_by
        ]);
        $bon_id = $pdo->lastInsertId();

        $insertDetail = $pdo->prepare("INSERT INTO bon_detail (bon_id, menu_id, quantity, price, subtotal) VALUES (:bon_id, :menu_id, :quantity, :price, :subtotal)");

        foreach ($items as $it) {
            $menuId = (int)$it['menu_id'];
            $qty = max(1, (int)$it['quantity']);

            // fetch price from menu table
            $m = $pdo->prepare("SELECT name, price FROM menu WHERE id = :id");
            $m->execute([':id' => $menuId]);
            $menu = $m->fetch();
            if (!$menu) continue;

            $price = (float)$menu['price'];
            $subtotal = $price * $qty;

            $insertDetail->execute([
                ':bon_id' => $bon_id,
                ':menu_id' => $menuId,
                ':quantity' => $qty,
                ':price' => $price,
                ':subtotal' => $subtotal
            ]);
        }

        $pdo->commit();
        return $bon_id;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get bon with details
 */
function getBon(int $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bon WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Get bon_detail rows with menu name
 */
function getBonDetails($bon_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT bd.*, m.name as menu_name 
        FROM bon_detail bd 
        JOIN menu m ON bd.menu_id = m.id 
        WHERE bd.bon_id = ?
    ");
    $stmt->execute([$bon_id]);
    return $stmt->fetchAll();
}

/**
 * Update quantity for bon_detail by delta (+1 / -1) or set absolute
 * If new qty <= 0 then delete detail.
 */
function updateBonDetailQuantity($detail_id, $delta = 0, $setAbsolute = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bon_detail WHERE id = :id");
    $stmt->execute([':id' => $detail_id]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if ($setAbsolute !== null) {
        $newQty = (int)$setAbsolute;
    } else {
        $newQty = (int)$row['quantity'] + (int)$delta;
    }

    if ($newQty <= 0) {
        // delete row
        $d = $pdo->prepare("DELETE FROM bon_detail WHERE id = :id");
        $d->execute([':id' => $detail_id]);
        return ['deleted' => true];
    } else {
        // update subtotal
        $price = (float)$row['price'];
        $subtotal = $price * $newQty;
        $u = $pdo->prepare("UPDATE bon_detail SET quantity = :q, subtotal = :st WHERE id = :id");
        $u->execute([
            ':q' => $newQty,
            ':st' => $subtotal,
            ':id' => $detail_id
        ]);
        return ['deleted' => false, 'quantity' => $newQty, 'subtotal' => $subtotal];
    }
}

/**
 * Delete specific bon_detail
 */
function deleteBonDetail($detail_id) {
    global $pdo;
    $d = $pdo->prepare("DELETE FROM bon_detail WHERE id = :id");
    $d->execute([':id' => $detail_id]);
    return $d->rowCount() > 0;
}

/**
 * Calculate totals (qty and total amount) for a bon
 */
function calculateBonTotals($bon_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            SUM(bd.quantity) as total_items,
            SUM(bd.subtotal) as total_amount
        FROM bon_detail bd 
        WHERE bd.bon_id = ?
    ");
    $stmt->execute([$bon_id]);
    return $stmt->fetch();
}

/**
 * Create local transaction row and return local txn id
 */
function createLocalTransaction($bon_id, $amount, $payment_method = 'qris') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO transactions (bon_id, amount, payment_method, status) VALUES (:bon_id, :amount, :pm, 'pending')");
    $stmt->execute([
        ':bon_id' => $bon_id,
        ':amount' => $amount,
        ':pm' => $payment_method
    ]);
    return $pdo->lastInsertId();
}

/**
 * Mark transaction by local id as paid/failed/pending
 */
function updateLocalTransactionStatus($local_txn_id, $status = 'paid') {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE transactions SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $local_txn_id]);
    return $stmt->rowCount() > 0;
}

/**
 * Mark bon status
 */
function updateBonStatus($bon_id, $status = 'completed', $payment_method = null) {
    global $pdo;
    
    try {
        if ($payment_method) {
            // Periksa apakah kolom payment_method ada di tabel bon
            $stmt = $pdo->prepare("SHOW COLUMNS FROM bon LIKE 'payment_method'");
            $stmt->execute();
            $column_exists = $stmt->fetch();
            
            if ($column_exists) {
                $stmt = $pdo->prepare("UPDATE bon SET status = :status, payment_method = :payment_method WHERE id = :id");
                $stmt->execute([
                    ':status' => $status,
                    ':payment_method' => $payment_method,
                    ':id' => $bon_id
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE bon SET status = :status WHERE id = :id");
                $stmt->execute([
                    ':status' => $status,
                    ':id' => $bon_id
                ]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE bon SET status = :status WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':id' => $bon_id
            ]);
        }
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error updating bon status: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper to fetch a single bon_detail row with menu name
 */
function getBonDetailById($detail_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT bd.*, m.name as menu_name
        FROM bon_detail bd
        JOIN menu m ON m.id = bd.menu_id
        WHERE bd.id = :id
    ");
    $stmt->execute([':id' => $detail_id]);
    return $stmt->fetch();
}

/**
 * Verify Doku signature_key
 * signature_key = SHA512(order_id + status_code + gross_amount + serverkey)
 * Returns boolean.
 */
function verifyDokuSignature($order_id, $status_code, $gross_amount, $signature_key) {
    $payload = $order_id . $status_code . number_format((float)$gross_amount, 2, '.', '') . DOKU_SERVER_KEY;
    $hash = hash('sha512', $payload);
    return hash_equals($hash, $signature_key);
}

/**
 * Get completed bons for history
 */
function getBonHistory($date = null) {
    global $pdo;
    
    // Periksa apakah kolom payment_method ada di tabel bon
    $stmt = $pdo->prepare("SHOW COLUMNS FROM bon LIKE 'payment_method'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if ($column_exists) {
        $sql = "SELECT b.*, t.amount, t.payment_method, t.created_at as payment_date 
                FROM bon b 
                LEFT JOIN transactions t ON b.id = t.bon_id 
                WHERE b.status = 'completed'";
    } else {
        $sql = "SELECT b.*, t.amount, t.payment_method, t.created_at as payment_date 
                FROM bon b 
                LEFT JOIN transactions t ON b.id = t.bon_id 
                WHERE b.status = 'completed'";
    }
    
    if ($date) {
        $sql .= " AND DATE(t.created_at) = :date";
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($date) {
        $stmt->execute([':date' => $date]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

/**
 * Delete bon history for a specific date
 */
function deleteBonHistoryByDate($date) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Get bon IDs to delete
        $stmt = $pdo->prepare("SELECT b.id FROM bon b 
                              JOIN transactions t ON b.id = t.bon_id 
                              WHERE DATE(t.created_at) = :date AND b.status = 'completed'");
        $stmt->execute([':date' => $date]);
        $bonIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($bonIds)) {
            // Delete from bon_detail
            $placeholders = implode(',', array_fill(0, count($bonIds), '?'));
            $pdo->prepare("DELETE FROM bon_detail WHERE bon_id IN ($placeholders)")->execute($bonIds);
            
            // Delete from transactions
            $pdo->prepare("DELETE FROM transactions WHERE bon_id IN ($placeholders)")->execute($bonIds);
            
            // Delete from bon
            $pdo->prepare("DELETE FROM bon WHERE id IN ($placeholders)")->execute($bonIds);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Get Doku access token for SNAP API
 */
function getDokuAccessToken() {
    $url = DOKU_IS_PRODUCTION ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
    $url .= '/api/v1.1/access-token/b2b';

    $client_id = DOKU_CLIENT_KEY;
    $client_secret = DOKU_SERVER_KEY;

    $request_id = uniqid();
    $request_timestamp = gmdate('c');

    $body = json_encode(['grantType' => 'client_credentials']);

    $signature = hash_hmac('sha256', $body, $client_secret);

    $headers = [
        'Content-Type: application/json',
        'Client-Id: ' . $client_id,
        'Request-Id: ' . $request_id,
        'Request-Timestamp: ' . $request_timestamp,
        'Signature: ' . $signature
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        return $data['accessToken'] ?? null;
    }

    return null;
}


/**
 * Check Midtrans transaction status
 */
function checkMidtransTransactionStatus($order_id) {
    try {
        \Midtrans\Config::$serverKey = DOKU_SERVER_KEY;
        \Midtrans\Config::$isProduction = DOKU_IS_PRODUCTION;
        
        $status = \Midtrans\Transaction::status($order_id);
        return $status;
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Get transaction by order_id
 */
function getTransactionByOrderId($order_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE midtrans_id = :order_id OR order_id = :order_id");
    $stmt->execute([
        ':order_id' => $order_id
    ]);
    
    return $stmt->fetch();
}

/**
 * Process cash payment
 */
function processCashPayment($bon_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Calculate totals
        $totals = calculateBonTotals($bon_id);
        $total_amount = $totals['total_amount'];
        
        // Create transaction record
        $stmt = $pdo->prepare("INSERT INTO transactions (bon_id, amount, payment_method, status) VALUES (:bon_id, :amount, 'cash', 'paid')");
        $stmt->execute([
            ':bon_id' => $bon_id,
            ':amount' => $total_amount
        ]);
        
        // Update bon status
        updateBonStatus($bon_id, 'completed', 'cash');
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Pembayaran tunai berhasil diproses'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Gagal memproses pembayaran tunai: ' . $e->getMessage()];
    }
}

/**
 * Create Midtrans transaction
 */
function createMidtransTransaction($bon_id, $amount) {
    global $pdo;

    try {
        // Generate unique order ID
        $order_id = 'BON-' . $bon_id . '-' . time();

        // Create transaction record first
        $stmt = $pdo->prepare("INSERT INTO transactions (bon_id, order_id, amount, payment_method, status) VALUES (:bon_id, :order_id, :amount, 'qris', 'pending')");
        $stmt->execute([
            ':bon_id' => $bon_id,
            ':order_id' => $order_id,
            ':amount' => $amount
        ]);

        // Prepare Midtrans transaction data
        $transaction_details = [
            'order_id' => $order_id,
            'gross_amount' => $amount
        ];

        $item_details = [];
        $bon_details = getBonDetails($bon_id);
        foreach ($bon_details as $detail) {
            $item_details[] = [
                'id' => $detail['menu_id'],
                'price' => $detail['price'],
                'quantity' => $detail['quantity'],
                'name' => $detail['menu_name']
            ];
        }

        $customer_details = [
            'first_name' => 'Customer',
            'last_name' => 'Bon ' . $bon_id,
            'email' => 'customer@example.com',
            'phone' => '08123456789'
        ];

        $transaction_data = [
            'transaction_details' => $transaction_details,
            'item_details' => $item_details,
            'customer_details' => $customer_details
        ];

        // Create QRIS payment
        $snap_response = \Midtrans\Snap::createTransaction($transaction_data);

        if ($snap_response->token) {
            // Update transaction with Midtrans token
            $stmt = $pdo->prepare("UPDATE transactions SET midtrans_token = :token WHERE order_id = :order_id");
            $stmt->execute([
                ':token' => $snap_response->token,
                ':order_id' => $order_id
            ]);

            return [
                'success' => true,
                'order_id' => $order_id,
                'qr_code' => $snap_response->redirect_url,
                'token' => $snap_response->token
            ];
        } else {
            // Update transaction status to failed
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $order_id]);

            return [
                'success' => false,
                'message' => 'Failed to create Midtrans transaction'
            ];
        }

    } catch (Exception $e) {
        error_log("Error creating Midtrans transaction: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Process QRIS payment
 */
function processQRISPayment($bon_id) {
    // Calculate totals
    $totals = calculateBonTotals($bon_id);
    $total_amount = $totals['total_amount'];

    // Create Midtrans transaction
    return createMidtransTransaction($bon_id, $total_amount);
}

/**
 * Check if bon table has payment_method column
 */
function hasPaymentMethodColumn() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM bon LIKE 'payment_method'");
        $stmt->execute();
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error checking payment_method column: " . $e->getMessage());
        return false;
    }
}

/**
 * Add payment_method column to bon table if it doesn't exist
 */
function addPaymentMethodColumn() {
    global $pdo;
    
    if (!hasPaymentMethodColumn()) {
        try {
            $stmt = $pdo->prepare("ALTER TABLE bon ADD COLUMN payment_method VARCHAR(50) NULL AFTER status");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            error_log("Error adding payment_method column: " . $e->getMessage());
            return false;
        }
    }
    
    return true;
}

// Pastikan kolom payment_method ada di tabel bon
addPaymentMethodColumn();
<?php
// ajax_bon.php
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_bon':
            $bon_id = (int)($_GET['bon_id'] ?? 0);
            $bon = getBon($bon_id);
            $details = getBonDetails($bon_id);
            $totals = calculateBonTotals($bon_id);
            echo json_encode(['success' => true, 'bon' => $bon, 'details' => $details, 'totals' => $totals]);
            break;

        case 'update_qty':
            $detail_id = (int)($_POST['detail_id'] ?? 0);
            $delta = (int)($_POST['delta'] ?? 0);
            if (!$detail_id) throw new Exception('detail_id required');
            $res = updateBonDetailQuantity($detail_id, $delta);

            $stmt = $pdo->prepare("SELECT quantity FROM bon_detail WHERE id = ?");
            $stmt->execute([$detail_id]);
            $row = $stmt->fetch();
            if ($row && (int)$row['quantity'] <= 0) {
                deleteBonDetail($detail_id);
                $res['quantity'] = 0;
            }

            $totals = null;
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            if ($bon_id) {
                $totals = calculateBonTotals($bon_id);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bon_detail WHERE bon_id = ?");
                $stmt->execute([$bon_id]);
                $count = $stmt->fetchColumn();
                if ($count == 0) {
                    $pdo->prepare("UPDATE bon SET archived = 1 WHERE id = ?")->execute([$bon_id]);
                    $totals['empty'] = true;
                }
            }
            echo json_encode(['success' => true, 'result' => $res, 'totals' => $totals]);
            break;

        case 'delete_item':
            $detail_id = (int)($_POST['detail_id'] ?? 0);
            if (!$detail_id) throw new Exception('detail_id required');
            $ok = deleteBonDetail($detail_id);
            $totals = null;
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            if ($bon_id) {
                $totals = calculateBonTotals($bon_id);
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM bon_detail WHERE bon_id = ?");
                $stmt->execute([$bon_id]);
                if ($count == 0) {
                    $pdo->prepare("UPDATE bon SET archived = 1 WHERE id = ?")->execute([$bon_id]);
                    $totals['empty'] = true;
                }
            }
            echo json_encode(['success' => true, 'deleted' => $ok, 'totals' => $totals]);
            break;

        case 'add_item':
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            $menu_id = (int)($_POST['menu_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);

            if ($bon_id <= 0 || $menu_id <= 0 || $quantity <= 0) {
                throw new Exception('Parameter tidak valid');
            }

            $stmt = $pdo->prepare("SELECT name, price FROM menu WHERE id = ?");
            $stmt->execute([$menu_id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$menu || !$menu['name'] || !is_numeric($menu['price'])) {
                throw new Exception('Menu tidak valid atau tidak ditemukan');
            }

            $name = $menu['name'];
            $price = floatval($menu['price']);
            $subtotal = $price * $quantity;

            $stmt = $pdo->prepare("SELECT id, quantity, subtotal FROM bon_detail WHERE bon_id = ? AND menu_id = ?");
            $stmt->execute([$bon_id, $menu_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $detail_id = null;
            if ($existing) {
                $new_quantity = $existing['quantity'] + $quantity;
                $new_subtotal = $price * $new_quantity;
                $stmt = $pdo->prepare("UPDATE bon_detail SET quantity = ?, subtotal = ? WHERE id = ?");
                $success = $stmt->execute([$new_quantity, $new_subtotal, $existing['id']]);
                $detail_id = $existing['id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO bon_detail (bon_id, menu_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $success = $stmt->execute([$bon_id, $menu_id, $quantity, $price, $subtotal]);
                $detail_id = $pdo->lastInsertId();
            }

            if ($success) {
                $totals = calculateBonTotals($bon_id);
                echo json_encode(['success' => true, 'totals' => $totals, 'item' => [
                    'id' => $detail_id,
                    'menu_id' => $menu_id,
                    'menu_name' => $name,
                    'quantity' => $existing ? $new_quantity : $quantity,
                    'price' => $price,
                    'subtotal' => $existing ? $new_subtotal : $subtotal
                ], 'existing' => !!$existing]);
            } else {
                throw new Exception('Gagal menambahkan item');
            }
            break;

        case 'add_multiple_items':
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            $items = json_decode($_POST['items'] ?? '[]', true);

            if (!$bon_id || empty($items)) {
                throw new Exception('Data tidak valid');
            }

            foreach ($items as $item) {
                $menu_id = (int)($item['menu_id'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                if (!$menu_id || !$quantity) continue;

                $stmt = $pdo->prepare("SELECT name, price FROM menu WHERE id = ?");
                $stmt->execute([$menu_id]);
                $menu = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$menu) continue;

                $name = $menu['name'];
                $price = floatval($menu['price']);
                $subtotal = $price * $quantity;

                $stmt = $pdo->prepare("SELECT id, quantity, subtotal FROM bon_detail WHERE bon_id = ? AND menu_id = ?");
                $stmt->execute([$bon_id, $menu_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $new_quantity = $existing['quantity'] + $quantity;
                    $new_subtotal = $price * $new_quantity;
                    $stmt = $pdo->prepare("UPDATE bon_detail SET quantity = ?, subtotal = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $new_subtotal, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO bon_detail (bon_id, menu_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$bon_id, $menu_id, $quantity, $price, $subtotal]);
                }
            }

            $totals = calculateBonTotals($bon_id);
            echo json_encode(['success' => true, 'totals' => $totals]);
            break;

        case 'cash_payment':
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            if (!$bon_id) throw new Exception('bon_id required');

            $pdo->prepare("UPDATE bon SET status='completed', payment_date=NOW() WHERE id=?")->execute([$bon_id]);
            $bon = getBon($bon_id);

            $stmt = $pdo->prepare("INSERT INTO bon_history 
                (table_number, created_at, updated_at, status, created_by, archived, payment_date, total_amount)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([
                $bon['table_number'], 
                $bon['created_at'], 
                $bon['updated_at'], 
                'completed', 
                $bon['created_by'], 
                date('Y-m-d H:i:s'),
                calculateBonTotals($bon_id)['total_amount']
            ]);

            $history_id = $pdo->lastInsertId();
            $details = getBonDetails($bon_id);
            $stmtDetail = $pdo->prepare("INSERT INTO bon_detail_history 
                (bon_history_id, menu_id, quantity, price, subtotal) 
                VALUES (?, ?, ?, ?, ?)");

            foreach ($details as $d) {
                $stmtDetail->execute([
                    $history_id, 
                    $d['menu_id'], 
                    $d['quantity'], 
                    $d['price'], 
                    $d['subtotal']
                ]);
            }

            $pdo->prepare("UPDATE bon SET archived=1 WHERE id=?")->execute([$bon_id]);
            echo json_encode(['success' => true]);
            break;

        case 'create_qris':
            $bon_id = (int)($_POST['bon_id'] ?? 0);
            if (!$bon_id) throw new Exception('bon_id required');
            $details = getBonDetails($bon_id);
            $totals = calculateBonTotals($bon_id);
            $amount = (int)round($totals['total_amount']);
            if ($amount <= 0) throw new Exception('Total pembayaran tidak valid');
            $order_id = 'BON' . $bon_id . '_' . time();
            $items_arr = [];
            foreach ($details as $d) {
                if ($d['price'] <= 0 || $d['quantity'] <= 0) continue;
                $items_arr[] = [
                    'id' => 'm'.$d['menu_id'],
                    'price' => (int)round($d['price']),
                    'quantity' => (int)$d['quantity'],
                    'name' => $d['menu_name']
                ];
            }
            $payload = [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $order_id,
                    'gross_amount' => $amount
                ],
                'item_details' => $items_arr,
                'qris' => new stdClass()
            ];
            $url = DOKU_IS_PRODUCTION ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
            $ch = curl_init($url);
            $post = json_encode($payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode(DOKU_SERVER_KEY . ':')
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $resp = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new Exception("Curl error: $err");
            }
            curl_close($ch);
            $respObj = json_decode($resp, true);
            if ($httpcode >= 200 && $httpcode < 300 && $respObj) {
                $qr_image_url = null;
                if (isset($respObj['actions']) && is_array($respObj['actions'])) {
                    foreach ($respObj['actions'] as $a) {
                        if (isset($a['name']) && in_array($a['name'], ['generate-qr-code-v2','generate-qr-code'])) {
                            $qr_image_url = $a['url'];
                            break;
                        }
                    }
                }
                echo json_encode([
                    'success'=>true,
                    'qr_image_url'=>$qr_image_url,
                    'order_id'=>$order_id
                ]);
            } else {
                $msg = $respObj['status_message'] ?? 'Midtrans error';
                throw new Exception('Midtrans error (' . $httpcode . '): ' . $msg);
            }
            break;

        case 'check_txn_status':
            $local_txn_id = (int)($_GET['local_txn_id'] ?? 0);
            if (!$local_txn_id) throw new Exception('local_txn_id required');
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id");
            $stmt->execute([':id' => $local_txn_id]);
            $t = $stmt->fetch();
            if (!$t) throw new Exception('Transaction not found');
            echo json_encode(['success'=>true, 'status' => $t['status'], 'txn' => $t]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>
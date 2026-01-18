<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$uuid = $_POST['uuid'] ?? '';

// Validasi UUID
if (empty($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
    echo json_encode(['success' => false, 'message' => 'UUID tidak valid']);
    exit;
}

// Cek guest session
$stmt = $pdo->prepare("SELECT * FROM guest_sessions WHERE uuid = ? AND status = 'active'");
$stmt->execute([$uuid]);
$guest_session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guest_session) {
    echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
    exit;
}

switch ($action) {
    case 'add_to_cart':
        $menu_id = intval($_POST['menu_id']);
        $quantity_change = intval($_POST['quantity_change']);
        
        // Cek menu exists
        $stmt = $pdo->prepare("SELECT * FROM menu WHERE id = ?");
        $stmt->execute([$menu_id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$menu) {
            echo json_encode(['success' => false, 'message' => 'Menu tidak ditemukan']);
            exit;
        }
        
        // Cek atau buat order yang pending
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE guest_uuid = ? AND status = 'pending'");
        $stmt->execute([$uuid]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $stmt = $pdo->prepare("INSERT INTO orders (guest_uuid, table_number, status) VALUES (?, 0, 'pending')");
            $stmt->execute([$uuid]);
            $order_id = $pdo->lastInsertId();
        } else {
            $order_id = $order['id'];
        }
        
        // Cek item sudah ada di cart
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? AND menu_id = ?");
        $stmt->execute([$order_id, $menu_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_item) {
            $new_quantity = $existing_item['quantity'] + $quantity_change;
            if ($new_quantity <= 0) {
                // Hapus item jika quantity <= 0
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
                $stmt->execute([$existing_item['id']]);
            } else {
                // Update quantity
                $subtotal = $new_quantity * $existing_item['price'];
                $stmt = $pdo->prepare("UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $subtotal, $existing_item['id']]);
            }
        } else {
            if ($quantity_change > 0) {
                // Tambah item baru
                $subtotal = $quantity_change * $menu['price'];
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $menu_id, $quantity_change, $menu['price'], $subtotal]);
            }
        }
        
        // Update total amount order
        updateOrderTotal($order_id);
        
        // Return updated cart
        returnCart($uuid);
        break;
        
    case 'update_cart_item':
        $item_id = intval($_POST['item_id']);
        $direction = intval($_POST['direction']);
        
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $new_quantity = $item['quantity'] + $direction;
            if ($new_quantity <= 0) {
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
                $stmt->execute([$item_id]);
            } else {
                $subtotal = $new_quantity * $item['price'];
                $stmt = $pdo->prepare("UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $subtotal, $item_id]);
            }
            
            // Update order total
            $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $order_item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order_item) {
                updateOrderTotal($order_item['order_id']);
            }
        }
        
        returnCart($uuid);
        break;
        
    case 'remove_cart_item':
        $item_id = intval($_POST['item_id']);
        
        $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
            $stmt->execute([$item_id]);
            updateOrderTotal($item['order_id']);
        }
        
        returnCart($uuid);
        break;
        
    case 'update_table':
        $table_number = intval($_POST['table_number']);
        
        $stmt = $pdo->prepare("UPDATE guest_sessions SET table_number = ? WHERE uuid = ?");
        $stmt->execute([$table_number, $uuid]);
        
        $stmt = $pdo->prepare("UPDATE orders SET table_number = ? WHERE guest_uuid = ? AND status = 'pending'");
        $stmt->execute([$table_number, $uuid]);
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}

function updateOrderTotal($order_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT SUM(subtotal) as total FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = $result['total'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
    $stmt->execute([$total, $order_id]);
}

function returnCart($uuid) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as menu_name 
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id 
        JOIN menu m ON oi.menu_id = m.id 
        WHERE o.guest_uuid = ? AND o.status = 'pending'
    ");
    $stmt->execute([$uuid]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'cart' => $cart]);
}
?>
<?php
// create_bon.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Set header to return JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['table_number']) || !isset($input['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}
try {
    $tableNumber = (int)$input['table_number'];
    $items = $input['items'];
    
    if (!is_array($items) || count($items) === 0) {
        throw new Exception('No items in order');
    }
    foreach ($items as $item) {
        if (!isset($item['menu_id']) || !isset($item['quantity'])) {
            throw new Exception('Invalid item format');
        }
    }
    
    $createdBy = 1; // Default user ID, you can change this based on your auth system
    
    $bonId = createBon($tableNumber, $createdBy, $items);
    
    echo json_encode([
        'success' => true,
        'bon_id' => $bonId,
        'message' => 'Bon created successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Create bon error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
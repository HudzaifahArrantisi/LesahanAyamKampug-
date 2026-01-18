<?php
// process_bon.php
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success'=>false, 'message'=>'No data received']);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success'=>false, 'message'=>'Invalid JSON']);
    exit;
}

$table_number = isset($data['table_number']) ? (int)$data['table_number'] : 1;
$created_by = isset($data['created_by']) ? (int)$data['created_by'] : 1;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

try {
    $bon_id = createBon($table_number, $created_by, $items);
    echo json_encode(['success'=>true, 'bon_id'=>$bon_id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
<?php
// add_to_cart.php
session_start();
include 'config.php';
include 'functions.php';

$item_id = intval($_POST['item_id']);
$quantity = intval($_POST['quantity']);

echo json_encode(add_to_cart($item_id, $quantity));
?>
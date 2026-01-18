<?php
// remove_from_cart.php
session_start();
include 'config.php';
include 'functions.php';

$item_id = intval($_POST['item_id']);

unset($_SESSION['cart'][$item_id]);

echo json_encode(['success' => true]);
?>
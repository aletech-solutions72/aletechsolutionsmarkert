<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'];
$delta = $data['delta'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Get current quantity
    $query = "SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $product_id]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cart_item) {
        $new_quantity = $cart_item['quantity'] + $delta;
        
        if ($new_quantity <= 0) {
            // Remove item from cart
            $query = "DELETE FROM carts WHERE user_id = ? AND product_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $product_id]);
        } else {
            // Update quantity
            $query = "UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_quantity, $user_id, $product_id]);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
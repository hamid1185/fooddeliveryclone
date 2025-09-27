<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('customer');
$user = getCurrentUser();

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $cart_id = $_POST['cart_id'];
        $quantity = max(1, intval($_POST['quantity']));
        
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
        $updateStmt->bind_param("iii", $quantity, $cart_id, $user['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        showMessage("Cart updated!", "success");
    }
    
    if (isset($_POST['remove_item'])) {
        $cart_id = $_POST['cart_id'];
        
        $deleteStmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
        $deleteStmt->bind_param("ii", $cart_id, $user['user_id']);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        showMessage("Item removed from cart!", "success");
    }
    
    if (isset($_POST['place_order'])) {
        $delivery_address = trim($_POST['delivery_address']);
        $phone = trim($_POST['phone']);
        
        if (!$delivery_address || !$phone) {
            showMessage("Delivery address and phone are required", "error");
        } else {
            // Get cart items
            $cartStmt = $conn->prepare("
                SELECT c.*, mi.name, mi.price, mi.restaurant_id 
                FROM cart c 
                JOIN menu_items mi ON c.menu_item_id = mi.menu_item_id 
                WHERE c.customer_id = ?
            ");
            $cartStmt->bind_param("i", $user['user_id']);
            $cartStmt->execute();
            $cartItems = $cartStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $cartStmt->close();
            
            if (empty($cartItems)) {
                showMessage("Your cart is empty", "error");
            } else {
                // Check if all items are from same restaurant
                $restaurants = array_unique(array_column($cartItems, 'restaurant_id'));
                if (count($restaurants) > 1) {
                    showMessage("You can only order from one restaurant at a time", "error");
                } else {
                    $restaurant_id = $restaurants[0];
                    $total_amount = 0;
                    
                    foreach ($cartItems as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                    }
                    $total_amount += 50; // Delivery fee
                    
                    $conn->begin_transaction();
                    
                    try {
                        // Create order
                        $orderStmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $orderStmt->bind_param("iidss", $user['user_id'], $restaurant_id, $total_amount, $delivery_address, $phone);
                        $orderStmt->execute();
                        $order_id = $conn->insert_id;
                        $orderStmt->close();
                        
                        // Add order items
                        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                        foreach ($cartItems as $item) {
                            $itemStmt->bind_param("iiid", $order_id, $item['menu_item_id'], $item['quantity'], $item['price']);
                            $itemStmt->execute();
                        }
                        $itemStmt->close();
                        
                        // Clear cart
                        $clearStmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
                        $clearStmt->bind_param("i", $user['user_id']);
                        $clearStmt->execute();
                        $clearStmt->close();
                        
                        $conn->commit();
                        
                        showMessage("Order placed successfully!", "success");
                        redirect('orders.php');
                    } catch (Exception $e) {
                        $conn->rollback();
                        showMessage("Failed to place order: " . $e->getMessage(), "error");
                    }
                }
            }
        }
    }
}

// Get cart items
$cartStmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, mi.name, mi.price, mi.image_url, r.name as restaurant_name
    FROM cart c 
    JOIN menu_items mi ON c.menu_item_id = mi.menu_item_id 
    JOIN restaurants r ON mi.restaurant_id = r.restaurant_id
    WHERE c.customer_id = ?
    ORDER BY r.name, mi.name
");
$cartStmt->bind_param("i", $user['user_id']);
$cartStmt->execute();
$cartItems = $cartStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cartStmt->close();

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$deliveryFee = !empty($cartItems) ? 50 : 0;
$total = $subtotal + $deliveryFee;

// Get cart count
$cartCount = array_sum(array_column($cartItems, 'quantity'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/customer/customer.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Customer Portal</h1>
    <nav>
      <a href="dashboard.php">Browse Restaurants</a>
      <a href="cart.php" class="active">Cart (<?php echo $cartCount; ?>)</a>
      <a href="orders.php">My Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>🛒 Your Shopping Cart</h1>
    </div>

    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
      <!-- Cart Items -->
      <section class="cart-items-section">
        <div class="card">
          <?php if (empty($cartItems)): ?>
            <div class="empty-message">
              <h3>🛒</h3>
              <p>Your cart is empty</p>
              <a href="dashboard.php" class="btn-primary">Browse Restaurants</a>
            </div>
          <?php else: ?>
            <?php 
            $currentRestaurant = '';
            foreach ($cartItems as $item): 
                if ($currentRestaurant !== $item['restaurant_name']):
                    if ($currentRestaurant !== '') echo '</div>';
                    $currentRestaurant = $item['restaurant_name'];
                    echo '<div class="restaurant-group"><h4>Restaurant: ' . htmlspecialchars($currentRestaurant) . '</h4>';
                endif;
            ?>
              <div class="cart-item">
                <img src="<?php echo $item['image_url'] ? '../backend/' . $item['image_url'] : '../frontend/default.png'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-img" />
                <div class="cart-item-info">
                  <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                  <div class="cart-item-price"><?php echo formatPrice($item['price']); ?> each</div>
                </div>
                <div class="cart-item-controls">
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                    <div class="quantity-controls">
                      <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="10" style="width: 60px; text-align: center;">
                      <button type="submit" name="update_quantity" class="btn-secondary">Update</button>
                    </div>
                  </form>
                  <div class="item-total"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                    <button type="submit" name="remove_item" class="btn-danger" onclick="return confirm('Remove this item?')">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if ($currentRestaurant !== '') echo '</div>'; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- Cart Summary -->
      <section class="cart-summary">
        <h3>Order Summary</h3>
        <div class="cart-total-row">
          <span>Subtotal:</span>
          <span><?php echo formatPrice($subtotal); ?></span>
        </div>
        <div class="cart-total-row">
          <span>Delivery Fee:</span>
          <span><?php echo formatPrice($deliveryFee); ?></span>
        </div>
        <div class="cart-total-final">
          <span>Total:</span>
          <span><?php echo formatPrice($total); ?></span>
        </div>

        <?php if (!empty($cartItems)): ?>
          <form method="POST" class="mt-2">
            <h4>Delivery Information</h4>
            <textarea name="delivery_address" placeholder="Delivery Address *" required></textarea>
            <input type="tel" name="phone" placeholder="Phone Number *" required>
            <button type="submit" name="place_order" class="btn-primary w-full">Place Order</button>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</body>
</html>
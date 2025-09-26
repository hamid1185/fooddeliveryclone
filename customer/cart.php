<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');
$user = getCurrentUser();

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $menuItemId = $_POST['menu_item_id'];
        $newQuantity = max(0, intval($_POST['quantity']));
        
        if ($newQuantity == 0) {
            // Remove item
            $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($menuItemId) {
                return $item['menu_item_id'] != $menuItemId;
            });
        } else {
            // Update quantity
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['menu_item_id'] == $menuItemId) {
                    $item['quantity'] = $newQuantity;
                    break;
                }
            }
        }
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
        header("Location: cart.php");
        exit;
    }
    
    if (isset($_POST['remove_item'])) {
        $menuItemId = $_POST['menu_item_id'];
        $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($menuItemId) {
            return $item['menu_item_id'] != $menuItemId;
        });
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        header("Location: cart.php");
        exit;
    }
    
    if (isset($_POST['place_order'])) {
        $deliveryAddress = trim($_POST['delivery_address']);
        $phone = trim($_POST['phone']);
        
        if (!$deliveryAddress || !$phone || empty($_SESSION['cart'])) {
            $error = "Please fill all fields and ensure cart is not empty";
        } else {
            // Check single restaurant
            $restaurants = array_unique(array_column($_SESSION['cart'], 'restaurant_id'));
            if (count($restaurants) > 1) {
                $error = "You can only order from one restaurant at a time";
            } else {
                $restaurantId = $restaurants[0];
                $subtotal = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }
                $deliveryFee = 50;
                $totalAmount = $subtotal + $deliveryFee;
                
                // Create order
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, phone, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("iidss", $user['user_id'], $restaurantId, $totalAmount, $deliveryAddress, $phone);
                    $stmt->execute();
                    $orderId = $conn->insert_id;
                    
                    // Add order items
                    $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price) VALUES (?, ?, ?, ?)");
                    foreach ($_SESSION['cart'] as $item) {
                        $itemStmt->bind_param("iiid", $orderId, $item['menu_item_id'], $item['quantity'], $item['price']);
                        $itemStmt->execute();
                    }
                    
                    $conn->commit();
                    unset($_SESSION['cart']);
                    header("Location: orders.php?success=1");
                    exit;
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to place order";
                }
            }
        }
    }
}

$cart = $_SESSION['cart'] ?? [];
$cartCount = 0;
$subtotal = 0;

foreach ($cart as $item) {
    $cartCount += $item['quantity'];
    $subtotal += $item['price'] * $item['quantity'];
}

$deliveryFee = $cartCount > 0 ? 50 : 0;
$total = $subtotal + $deliveryFee;

// Group cart by restaurant
$groupedCart = [];
foreach ($cart as $item) {
    $groupedCart[$item['restaurant_id']][] = $item;
}
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
    <div class="dashboard-header">
      <h1>🛒 Your Shopping Cart</h1>
    </div>

    <?php if (isset($error)): ?>
      <div style="background: #ff7675; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem;">
      <!-- Cart Items -->
      <section class="cart-items-section">
        <div class="card">
          <?php if (empty($cart)): ?>
            <div class="empty-message">
              <h3>🛒</h3>
              <p>Your cart is empty</p>
              <a href="dashboard.php" class="btn-primary">Browse Restaurants</a>
            </div>
          <?php else: ?>
            <?php foreach ($groupedCart as $restaurantId => $items): ?>
              <div class="restaurant-group">
                <h4>Restaurant ID: <?php echo $restaurantId; ?></h4>
                <?php foreach ($items as $item): ?>
                  <div class="cart-item">
                    <div class="cart-item-info">
                      <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                      <div class="cart-item-price">৳<?php echo number_format($item['price'], 2); ?> each</div>
                    </div>
                    <div class="cart-item-controls">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="menu_item_id" value="<?php echo $item['menu_item_id']; ?>">
                        <div class="quantity-controls">
                          <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="0" max="10" style="width: 60px;">
                          <button type="submit" name="update_quantity" class="btn-secondary">Update</button>
                        </div>
                      </form>
                      <div class="item-total">৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="menu_item_id" value="<?php echo $item['menu_item_id']; ?>">
                        <button type="submit" name="remove_item" class="btn-danger">Remove</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <!-- Cart Summary -->
      <section class="cart-summary">
        <h3>Order Summary</h3>
        <div class="cart-total-row">
          <span>Subtotal:</span>
          <span>৳<?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="cart-total-row">
          <span>Delivery Fee:</span>
          <span>৳<?php echo number_format($deliveryFee, 2); ?></span>
        </div>
        <div class="cart-total-final">
          <span>Total:</span>
          <span>৳<?php echo number_format($total, 2); ?></span>
        </div>

        <?php if (!empty($cart)): ?>
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
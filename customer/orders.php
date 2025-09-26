<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');
$user = getCurrentUser();

// Get customer orders with restaurant info
$stmt = $conn->prepare("
    SELECT o.*, r.name as restaurant_name 
    FROM orders o 
    JOIN restaurants r ON o.restaurant_id = r.restaurant_id 
    WHERE o.customer_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get order items for each order
foreach ($orders as &$order) {
    $stmt = $conn->prepare("
        SELECT oi.*, mi.name 
        FROM order_items oi 
        JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order['order_id']);
    $stmt->execute();
    $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/customer/customer.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Customer Portal</h1>
    <nav>
      <a href="dashboard.php">Browse Restaurants</a>
      <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
      <a href="orders.php" class="active">My Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <div class="dashboard-header">
      <h1>My Orders</h1>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div style="background: #00b894; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        Order placed successfully!
      </div>
    <?php endif; ?>

    <section class="orders-section">
      <?php if (empty($orders)): ?>
        <div class="empty-message">
          <h3>📋</h3>
          <p>You haven't placed any orders yet</p>
          <a href="dashboard.php" class="btn-primary">Browse Restaurants</a>
        </div>
      <?php else: ?>
        <?php foreach ($orders as $order): ?>
          <div class="order-card">
            <div class="order-header">
              <div class="order-info">
                <h4>Order #<?php echo $order['order_id']; ?></h4>
                <div class="order-date"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></div>
                <div class="restaurant-name">🏪 <?php echo htmlspecialchars($order['restaurant_name']); ?></div>
              </div>
              <div class="order-status">
                <span class="status status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
              </div>
            </div>
            
            <div class="order-items">
              <?php foreach ($order['items'] as $item): ?>
                <div class="order-item">
                  <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                  <span>৳<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            
            <div class="order-details">
              <div><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></div>
              <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></div>
            </div>
            
            <div class="order-total">
              Total: ৳<?php echo number_format($order['total_amount'], 2); ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('restaurant');
$user = getCurrentUser();

// Get restaurant info
$restaurantStmt = $conn->prepare("SELECT * FROM restaurants WHERE owner_id = ?");
$restaurantStmt->bind_param("i", $user['user_id']);
$restaurantStmt->execute();
$restaurant = $restaurantStmt->get_result()->fetch_assoc();
$restaurantStmt->close();

if (!$restaurant) {
    redirect('setup.php');
}

// Get stats if restaurant is approved
$stats = ['total_menu_items' => 0, 'pending_orders' => 0, 'today_orders' => 0];
if ($restaurant['status'] === 'approved') {
    // Menu items count
    $menuStmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE restaurant_id = ?");
    $menuStmt->bind_param("i", $restaurant['restaurant_id']);
    $menuStmt->execute();
    $stats['total_menu_items'] = $menuStmt->get_result()->fetch_assoc()['total'];
    $menuStmt->close();
    
    // Pending orders
    $pendingStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE restaurant_id = ? AND status = 'pending'");
    $pendingStmt->bind_param("i", $restaurant['restaurant_id']);
    $pendingStmt->execute();
    $stats['pending_orders'] = $pendingStmt->get_result()->fetch_assoc()['total'];
    $pendingStmt->close();
    
    // Today's orders
    $todayStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()");
    $todayStmt->bind_param("i", $restaurant['restaurant_id']);
    $todayStmt->execute();
    $stats['today_orders'] = $todayStmt->get_result()->fetch_assoc()['total'];
    $todayStmt->close();
    
    // Recent orders
    $ordersStmt = $conn->prepare("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.user_id 
        WHERE o.restaurant_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $ordersStmt->bind_param("i", $restaurant['restaurant_id']);
    $ordersStmt->execute();
    $recentOrders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ordersStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restaurant Dashboard - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/restaurant/restaurant.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Restaurant Portal</h1>
    <nav>
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="menu.php">Manage Menu</a>
      <a href="orders.php">Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
    </div>

    <!-- Restaurant Info -->
    <section>
      <div class="card">
        <h3>Restaurant Information</h3>
        <div class="restaurant-detail-item">
          <strong>Name:</strong>
          <span><?php echo htmlspecialchars($restaurant['name']); ?></span>
        </div>
        <div class="restaurant-detail-item">
          <strong>Location:</strong>
          <span><?php echo htmlspecialchars($restaurant['location']); ?></span>
        </div>
        <div class="restaurant-detail-item">
          <strong>Description:</strong>
          <span><?php echo htmlspecialchars($restaurant['description'] ?: 'No description provided'); ?></span>
        </div>
        <div class="restaurant-detail-item">
          <strong>Status:</strong>
          <span class="restaurant-status-badge status <?php echo getStatusClass($restaurant['status']); ?>"><?php echo $restaurant['status']; ?></span>
        </div>
        <a href="edit.php" class="btn-secondary">Edit Restaurant Info</a>
      </div>
    </section>

    <?php if ($restaurant['status'] === 'approved'): ?>
      <!-- Quick Actions -->
      <section>
        <h2>Quick Actions</h2>
        <div class="grid grid-2">
          <div class="card">
            <h3>Menu Management</h3>
            <p>Add, edit, or remove menu items. Control item availability.</p>
            <a href="menu.php" class="btn-primary">Manage Menu</a>
          </div>
          <div class="card">
            <h3>Order Management</h3>
            <p>View and update order statuses. Track customer orders.</p>
            <a href="orders.php" class="btn-secondary">Manage Orders</a>
          </div>
        </div>
      </section>

      <!-- Quick Stats -->
      <section>
        <h2>Quick Stats</h2>
        <div class="grid grid-3">
          <div class="stat-card">
            <h3><?php echo $stats['total_menu_items']; ?></h3>
            <p>Menu Items</p>
          </div>
          <div class="stat-card">
            <h3><?php echo $stats['pending_orders']; ?></h3>
            <p>Pending Orders</p>
          </div>
          <div class="stat-card">
            <h3><?php echo $stats['today_orders']; ?></h3>
            <p>Today's Orders</p>
          </div>
        </div>
      </section>

      <!-- Recent Orders -->
      <section>
        <h2>Recent Orders</h2>
        <?php if (empty($recentOrders)): ?>
          <div class="empty-state">
            <h3>📋</h3>
            <p>No recent orders</p>
          </div>
        <?php else: ?>
          <?php foreach ($recentOrders as $order): ?>
            <div class="order-card">
              <div class="order-header">
                <div class="order-info">
                  <h4>Order #<?php echo $order['order_id']; ?></h4>
                  <div class="order-date"><?php echo formatDate($order['created_at']); ?></div>
                </div>
                <span class="status <?php echo getStatusClass($order['status']); ?>"><?php echo $order['status']; ?></span>
              </div>
              <div class="order-customer-info">
                <h5>Customer: <?php echo htmlspecialchars($order['customer_name']); ?></h5>
                <p>📞 <?php echo htmlspecialchars($order['phone']); ?></p>
                <p>📍 <?php echo htmlspecialchars($order['delivery_address']); ?></p>
              </div>
              <div class="order-total">
                Total: <?php echo formatPrice($order['total_amount']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section>
        <div class="card" style="background: #ffeaa7; border-left-color: #fdcb6e;">
          <h3>Restaurant Status: <?php echo ucfirst($restaurant['status']); ?></h3>
          <?php if ($restaurant['status'] === 'pending'): ?>
            <p>Your restaurant is pending approval from admin. You'll be able to manage menu and orders once approved.</p>
          <?php elseif ($restaurant['status'] === 'rejected'): ?>
            <p>Your restaurant application was rejected. Please contact admin for more information.</p>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</body>
</html>
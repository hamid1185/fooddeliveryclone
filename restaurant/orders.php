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

if (!$restaurant || $restaurant['status'] !== 'approved') {
    redirect('dashboard.php');
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    $validStatuses = ['pending', 'preparing', 'delivered', 'cancelled'];
    if (in_array($status, $validStatuses)) {
        $updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND restaurant_id = ?");
        $updateStmt->bind_param("sii", $status, $order_id, $restaurant['restaurant_id']);
        
        if ($updateStmt->execute()) {
            showMessage("Order status updated to $status", "success");
        } else {
            showMessage("Failed to update order status", "error");
        }
        $updateStmt->close();
    }
}

// Get orders with customer info
$ordersStmt = $conn->prepare("
    SELECT o.*, u.name as customer_name 
    FROM orders o 
    JOIN users u ON o.customer_id = u.user_id 
    WHERE o.restaurant_id = ? 
    ORDER BY o.created_at DESC
");
$ordersStmt->bind_param("i", $restaurant['restaurant_id']);
$ordersStmt->execute();
$orders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ordersStmt->close();

// Get order items for each order
foreach ($orders as &$order) {
    $itemsStmt = $conn->prepare("
        SELECT oi.*, mi.name 
        FROM order_items oi 
        JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id 
        WHERE oi.order_id = ?
    ");
    $itemsStmt->bind_param("i", $order['order_id']);
    $itemsStmt->execute();
    $order['items'] = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();
}

// Calculate stats
$pendingCount = count(array_filter($orders, function($o) { return $o['status'] === 'pending'; }));
$preparingCount = count(array_filter($orders, function($o) { return $o['status'] === 'preparing'; }));
$deliveredToday = count(array_filter($orders, function($o) { 
    return $o['status'] === 'delivered' && date('Y-m-d', strtotime($o['created_at'])) === date('Y-m-d'); 
}));

$currentFilter = $_GET['status'] ?? 'all';
$displayOrders = $currentFilter === 'all' ? $orders : array_filter($orders, function($o) use ($currentFilter) {
    return $o['status'] === $currentFilter;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders Management - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/restaurant/restaurant.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Restaurant Portal</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="menu.php">Manage Menu</a>
      <a href="orders.php" class="active">Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>Order Management</h1>
      <div class="order-stats">
        <div class="stat-item">
          <span class="stat-number"><?php echo $pendingCount; ?></span>
          <span class="stat-label">Pending</span>
        </div>
        <div class="stat-item">
          <span class="stat-number"><?php echo $preparingCount; ?></span>
          <span class="stat-label">Preparing</span>
        </div>
        <div class="stat-item">
          <span class="stat-number"><?php echo $deliveredToday; ?></span>
          <span class="stat-label">Delivered Today</span>
        </div>
      </div>
    </div>

    <!-- Order Status Filters -->
    <section class="order-filters">
      <div class="category-pills">
        <a href="?status=all" class="category-pill <?php echo $currentFilter === 'all' ? 'active' : ''; ?>">All Orders</a>
        <a href="?status=pending" class="category-pill <?php echo $currentFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=preparing" class="category-pill <?php echo $currentFilter === 'preparing' ? 'active' : ''; ?>">Preparing</a>
        <a href="?status=delivered" class="category-pill <?php echo $currentFilter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
        <a href="?status=cancelled" class="category-pill <?php echo $currentFilter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
      </div>
    </section>

    <!-- Orders List -->
    <section class="orders-section">
      <?php if (empty($displayOrders)): ?>
        <div class="empty-state">
          <h3>📋</h3>
          <p><?php echo $currentFilter === 'all' ? 'No orders yet.' : "No $currentFilter orders."; ?></p>
        </div>
      <?php else: ?>
        <?php foreach ($displayOrders as $order): ?>
          <div class="order-card">
            <div class="order-header">
              <div class="order-info">
                <h4>Order #<?php echo $order['order_id']; ?></h4>
                <div class="order-date"><?php echo formatDate($order['created_at']); ?></div>
              </div>
              <span class="status <?php echo getStatusClass($order['status']); ?>"><?php echo $order['status']; ?></span>
            </div>
            
            <div class="order-customer-info">
              <h5>Customer Information</h5>
              <div class="customer-details">
                <div><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></div>
                <div><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></div>
              </div>
            </div>
            
            <div class="order-items">
              <h5>Order Items</h5>
              <?php foreach ($order['items'] as $item): ?>
                <div class="order-item">
                  <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                  <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            
            <div class="order-total">
              Total: <?php echo formatPrice($order['total_amount']); ?>
            </div>
            
            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
              <div class="status-actions">
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                  <select name="status" onchange="if(this.value && confirm('Update order status?')) this.form.submit();">
                    <option value="">Update Status</option>
                    <?php if ($order['status'] === 'pending'): ?>
                      <option value="preparing">Mark as Preparing</option>
                    <?php endif; ?>
                    <?php if ($order['status'] === 'preparing'): ?>
                      <option value="delivered">Mark as Delivered</option>
                    <?php endif; ?>
                    <option value="cancelled">Cancel Order</option>
                  </select>
                  <input type="hidden" name="update_status" value="1">
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
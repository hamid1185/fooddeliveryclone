<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('restaurant');
$user = getCurrentUser();

// Get restaurant info
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE owner_id = ?");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats = ['total_menu_items' => 0, 'pending_orders' => 0, 'today_orders' => 0];
$recentOrders = [];

if ($restaurant && $restaurant['status'] === 'approved') {
    // Get stats
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM menu_items WHERE restaurant_id = ?");
    $stmt->bind_param("i", $restaurant['restaurant_id']);
    $stmt->execute();
    $stats['total_menu_items'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE restaurant_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $restaurant['restaurant_id']);
    $stmt->execute();
    $stats['pending_orders'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE restaurant_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("i", $restaurant['restaurant_id']);
    $stmt->execute();
    $stats['today_orders'] = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Get recent orders
    $stmt = $conn->prepare("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.user_id 
        WHERE o.restaurant_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param("i", $restaurant['restaurant_id']);
    $stmt->execute();
    $recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle restaurant update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_restaurant'])) {
    $name = trim($_POST['name']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    
    if ($name && $location && $restaurant) {
        $stmt = $conn->prepare("UPDATE restaurants SET name = ?, location = ?, description = ? WHERE restaurant_id = ?");
        $stmt->bind_param("sssi", $name, $location, $description, $restaurant['restaurant_id']);
        if ($stmt->execute()) {
            header("Location: dashboard.php?updated=1");
            exit;
        }
        $stmt->close();
    }
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
    <div class="dashboard-header">
      <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div style="background: #00b894; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        Restaurant updated successfully!
      </div>
    <?php endif; ?>

    <?php if (!$restaurant): ?>
      <!-- Restaurant Setup Notice -->
      <div class="card" style="background: #ffeaa7; border-left-color: #fdcb6e;">
        <h3>Setup Your Restaurant</h3>
        <p>You haven't set up your restaurant yet. Click below to get started!</p>
        <a href="setup.php" class="btn-primary">Setup Restaurant</a>
      </div>
    <?php else: ?>
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
            <span class="restaurant-status-badge status-<?php echo $restaurant['status']; ?>"><?php echo $restaurant['status']; ?></span>
          </div>
          <button onclick="document.getElementById('editModal').style.display='block'" class="btn-secondary">Edit Restaurant Info</button>
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
                    <div class="order-date"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></div>
                  </div>
                  <span class="status status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
                </div>
                <div class="order-customer-info">
                  <h5>Customer: <?php echo htmlspecialchars($order['customer_name']); ?></h5>
                  <p>📞 <?php echo htmlspecialchars($order['phone']); ?></p>
                  <p>📍 <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                </div>
                <div class="order-total">
                  Total: ৳<?php echo number_format($order['total_amount'], 2); ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Edit Restaurant Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
      <h3>Edit Restaurant Information</h3>
      <form method="POST">
        <input type="text" name="name" placeholder="Restaurant Name" value="<?php echo htmlspecialchars($restaurant['name'] ?? ''); ?>" required>
        <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($restaurant['location'] ?? ''); ?>" required>
        <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($restaurant['description'] ?? ''); ?></textarea>
        <button type="submit" name="update_restaurant" class="btn-primary">Update Restaurant</button>
      </form>
    </div>
  </div>

  <script>
    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>
</body>
</html>
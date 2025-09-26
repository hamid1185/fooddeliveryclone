<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('admin');
$user = getCurrentUser();

// Get system statistics
$stats = [];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$stats['total_users'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM restaurants");
$stmt->execute();
$stats['total_restaurants'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM restaurants WHERE status = 'pending'");
$stmt->execute();
$stats['pending_approvals'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders");
$stmt->execute();
$stats['total_orders'] = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get pending restaurants
$stmt = $conn->prepare("
    SELECT r.*, u.name as owner_name, u.email as owner_email
    FROM restaurants r 
    JOIN users u ON r.owner_id = u.user_id 
    WHERE r.status = 'pending' 
    ORDER BY r.created_at ASC
");
$stmt->execute();
$pendingRestaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent orders
$stmt = $conn->prepare("
    SELECT o.*, 
           u.name as customer_name,
           r.name as restaurant_name,
           r.location as restaurant_location
    FROM orders o 
    JOIN users u ON o.customer_id = u.user_id 
    JOIN restaurants r ON o.restaurant_id = r.restaurant_id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle restaurant approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_restaurant'])) {
        $restaurantId = $_POST['restaurant_id'];
        $stmt = $conn->prepare("UPDATE restaurants SET status = 'approved' WHERE restaurant_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $restaurantId);
        $stmt->execute();
        $stmt->close();
        header("Location: dashboard.php?approved=1");
        exit;
    }
    
    if (isset($_POST['reject_restaurant'])) {
        $restaurantId = $_POST['restaurant_id'];
        $stmt = $conn->prepare("UPDATE restaurants SET status = 'rejected' WHERE restaurant_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $restaurantId);
        $stmt->execute();
        $stmt->close();
        header("Location: dashboard.php?rejected=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/admin/admin.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Admin Portal</h1>
    <nav>
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="users.php">Users</a>
      <a href="restaurants.php">Restaurants</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <div class="dashboard-header">
      <h1>Welcome, Admin!</h1>
    </div>

    <?php if (isset($_GET['approved'])): ?>
      <div style="background: #00b894; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        Restaurant approved successfully!
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['rejected'])): ?>
      <div style="background: #ff7675; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        Restaurant rejected.
      </div>
    <?php endif; ?>

    <!-- System Stats -->
    <section>
      <h2>System Overview</h2>
      <div class="grid grid-4">
        <div class="stat-card">
          <h3><?php echo $stats['total_users']; ?></h3>
          <p>Total Users</p>
        </div>
        <div class="stat-card">
          <h3><?php echo $stats['total_restaurants']; ?></h3>
          <p>Total Restaurants</p>
        </div>
        <div class="stat-card">
          <h3><?php echo $stats['pending_approvals']; ?></h3>
          <p>Pending Approvals</p>
        </div>
        <div class="stat-card">
          <h3><?php echo $stats['total_orders']; ?></h3>
          <p>Total Orders</p>
        </div>
      </div>
    </section>

    <!-- Pending Restaurant Approvals -->
    <section>
      <h2>Pending Restaurant Approvals</h2>
      <?php if (empty($pendingRestaurants)): ?>
        <div class="empty-state">
          <h3>✅</h3>
          <p>No pending restaurant approvals</p>
        </div>
      <?php else: ?>
        <?php foreach ($pendingRestaurants as $restaurant): ?>
          <div class="approval-card">
            <div class="approval-header">
              <div class="approval-info">
                <h4><?php echo htmlspecialchars($restaurant['name']); ?></h4>
                <div class="approval-date">Applied: <?php echo date('M j, Y g:i A', strtotime($restaurant['created_at'])); ?></div>
                <div class="approval-date">Owner: <?php echo htmlspecialchars($restaurant['owner_name']); ?></div>
              </div>
            </div>
            <div class="approval-details">
              <p><strong>Location:</strong> <?php echo htmlspecialchars($restaurant['location']); ?></p>
              <p><strong>Description:</strong> <?php echo htmlspecialchars($restaurant['description'] ?: 'No description provided'); ?></p>
            </div>
            <div class="approval-actions">
              <form method="POST" style="display: inline;">
                <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                <button type="submit" name="approve_restaurant" class="btn-success" onclick="return confirm('Approve this restaurant?')">
                  Approve
                </button>
              </form>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                <button type="submit" name="reject_restaurant" class="btn-danger" onclick="return confirm('Reject this restaurant?')">
                  Reject
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- Recent Activity -->
    <section>
      <h2>Recent Orders</h2>
      <?php if (empty($recentOrders)): ?>
        <div class="empty-state">
          <h3>📋</h3>
          <p>No recent orders</p>
        </div>
      <?php else: ?>
        <?php foreach ($recentOrders as $order): ?>
          <div class="admin-order-card">
            <div class="order-header">
              <div class="order-info">
                <h4>Order #<?php echo $order['order_id']; ?></h4>
                <div class="order-date"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></div>
              </div>
              <span class="status status-<?php echo $order['status']; ?>"><?php echo $order['status']; ?></span>
            </div>
            <div class="order-parties">
              <div class="party-info">
                <h5>Customer</h5>
                <p><?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p>📞 <?php echo htmlspecialchars($order['phone']); ?></p>
              </div>
              <div class="party-info">
                <h5>Restaurant</h5>
                <p><?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                <p>📍 <?php echo htmlspecialchars($order['restaurant_location']); ?></p>
              </div>
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
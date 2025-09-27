<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('customer');
$user = getCurrentUser();

// Get search query
$search = $_GET['search'] ?? '';

// Fetch restaurants
$query = "SELECT restaurant_id, name, location, description FROM restaurants WHERE status = 'approved'";
if ($search) {
    $query .= " AND (name LIKE ? OR location LIKE ? OR description LIKE ?)";
}
$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
if ($search) {
    $searchTerm = "%$search%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}
$stmt->execute();
$restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get cart count
$cartStmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?");
$cartStmt->bind_param("i", $user['user_id']);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();
$cartCount = $cartResult->fetch_assoc()['total'] ?? 0;
$cartStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Restaurants - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/customer/customer.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Customer Portal</h1>
    <nav>
      <a href="dashboard.php" class="active">Browse Restaurants</a>
      <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
      <a href="orders.php">My Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
      <div class="search-bar">
        <form method="GET">
          <input type="text" name="search" placeholder="Search restaurants..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
      </div>
    </div>

    <section class="restaurants-section">
      <h2>Available Restaurants</h2>
      <div class="grid grid-2">
        <?php if (empty($restaurants)): ?>
          <div class="empty-message">
            <h3>🍽️</h3>
            <p><?php echo $search ? 'No restaurants found' : 'No restaurants available'; ?></p>
          </div>
        <?php else: ?>
          <?php foreach ($restaurants as $restaurant): ?>
            <div class="restaurant-card">
              <div class="restaurant-header">
                <div class="restaurant-info">
                  <h3><?php echo htmlspecialchars($restaurant['name']); ?></h3>
                  <div class="restaurant-location"><?php echo htmlspecialchars($restaurant['location']); ?></div>
                </div>
                <span class="restaurant-status status-open">Open</span>
              </div>
              <div class="restaurant-description">
                <?php echo htmlspecialchars($restaurant['description'] ?: 'Delicious food awaits you!'); ?>
              </div>
              <div class="restaurant-actions">
                <a href="restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>" class="btn-primary">View Menu</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</body>
</html>
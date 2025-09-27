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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$name || !$location) {
        showMessage("Restaurant name and location are required", "error");
    } else {
        $updateStmt = $conn->prepare("UPDATE restaurants SET name = ?, location = ?, description = ? WHERE restaurant_id = ?");
        $updateStmt->bind_param("sssi", $name, $location, $description, $restaurant['restaurant_id']);
        
        if ($updateStmt->execute()) {
            showMessage("Restaurant updated successfully!", "success");
            redirect('dashboard.php');
        } else {
            showMessage("Failed to update restaurant", "error");
        }
        $updateStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Restaurant - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/restaurant/restaurant.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Restaurant Portal</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="menu.php">Manage Menu</a>
      <a href="orders.php">Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>Edit Restaurant Information</h1>
    </div>

    <section>
      <div class="card">
        <form method="POST">
          <div class="form-section">
            <h3>Basic Information</h3>
            <input type="text" name="name" placeholder="Restaurant Name *" value="<?php echo htmlspecialchars($restaurant['name']); ?>" required>
            <input type="text" name="location" placeholder="Restaurant Address *" value="<?php echo htmlspecialchars($restaurant['location']); ?>" required>
            <textarea name="description" placeholder="Describe your restaurant"><?php echo htmlspecialchars($restaurant['description']); ?></textarea>
          </div>
          
          <div class="form-actions">
            <a href="dashboard.php" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Update Restaurant</button>
          </div>
        </form>
      </div>
    </section>
  </div>
</body>
</html>
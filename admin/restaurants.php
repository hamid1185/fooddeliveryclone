<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');
$user = getCurrentUser();

// Handle restaurant actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $restaurant_id = $_POST['restaurant_id'];
        $action = $_POST['action'];
        
        if (in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $updateStmt = $conn->prepare("UPDATE restaurants SET status = ? WHERE restaurant_id = ?");
            $updateStmt->bind_param("si", $status, $restaurant_id);
            
            if ($updateStmt->execute()) {
                showMessage("Restaurant $status successfully", "success");
            } else {
                showMessage("Failed to update restaurant status", "error");
            }
            $updateStmt->close();
        }
    }
    
    if (isset($_POST['update_info'])) {
        $restaurant_id = $_POST['restaurant_id'];
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $description = trim($_POST['description']);
        
        if ($name && $location) {
            $updateStmt = $conn->prepare("UPDATE restaurants SET name = ?, location = ?, description = ? WHERE restaurant_id = ?");
            $updateStmt->bind_param("sssi", $name, $location, $description, $restaurant_id);
            
            if ($updateStmt->execute()) {
                showMessage("Restaurant information updated", "success");
            } else {
                showMessage("Failed to update restaurant", "error");
            }
            $updateStmt->close();
        } else {
            showMessage("Name and location are required", "error");
        }
    }
}

// Get all restaurants
$restaurantsStmt = $conn->prepare("
    SELECT r.restaurant_id, r.name, r.status, r.location, r.description, r.created_at,
           u.name as owner_name 
    FROM restaurants r 
    JOIN users u ON r.owner_id = u.user_id 
    ORDER BY r.created_at DESC
");
$restaurantsStmt->execute();
$restaurants = $restaurantsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$restaurantsStmt->close();

$editRestaurant = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editRestaurant = array_filter($restaurants, function($r) use ($editId) {
        return $r['restaurant_id'] == $editId;
    });
    $editRestaurant = reset($editRestaurant);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Restaurants</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/admin/admin.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Admin Portal</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="users.php">Users</a>
      <a href="restaurants.php" class="active">Restaurants</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    
    <!-- Edit Form -->
    <?php if ($editRestaurant): ?>
      <section>
        <div class="card">
          <h3>Edit Restaurant</h3>
          <form method="POST">
            <input type="hidden" name="restaurant_id" value="<?php echo $editRestaurant['restaurant_id']; ?>">
            <input type="text" name="name" placeholder="Restaurant Name" value="<?php echo htmlspecialchars($editRestaurant['name']); ?>" required>
            <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($editRestaurant['location']); ?>" required>
            <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($editRestaurant['description']); ?></textarea>
            <div class="form-actions">
              <a href="restaurants.php" class="btn-secondary">Cancel</a>
              <button type="submit" name="update_info" class="btn-primary">Update Restaurant</button>
            </div>
          </form>
        </div>
      </section>
    <?php endif; ?>
    
    <h2>All Restaurants</h2>
    
    <?php if (empty($restaurants)): ?>
      <p>No restaurants found.</p>
    <?php else: ?>
      <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
        <thead>
          <tr style="background: #f8f9fa;">
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">ID</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Name</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Owner</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Status</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Location</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($restaurants as $restaurant): ?>
            <tr style="border-bottom: 1px solid #dee2e6;">
              <td style="padding: 1rem;"><?php echo $restaurant['restaurant_id']; ?></td>
              <td style="padding: 1rem;"><?php echo htmlspecialchars($restaurant['name']); ?></td>
              <td style="padding: 1rem;"><?php echo htmlspecialchars($restaurant['owner_name']); ?></td>
              <td style="padding: 1rem;">
                <span class="status <?php echo getStatusClass($restaurant['status']); ?>"><?php echo $restaurant['status']; ?></span>
              </td>
              <td style="padding: 1rem;"><?php echo htmlspecialchars($restaurant['location']); ?></td>
              <td style="padding: 1rem;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                  <?php if ($restaurant['status'] === 'pending'): ?>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" name="update_status" class="btn-success" style="padding: 0.5rem 1rem; font-size: 0.9rem;" onclick="return confirm('Approve this restaurant?')">Approve</button>
                    </form>
                    <form method="POST" style="display: inline;">
                      <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" name="update_status" class="btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;" onclick="return confirm('Reject this restaurant?')">Reject</button>
                    </form>
                  <?php endif; ?>
                  <a href="?edit=<?php echo $restaurant['restaurant_id']; ?>" class="btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem; text-decoration: none;">Edit</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
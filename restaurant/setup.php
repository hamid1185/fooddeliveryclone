<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('restaurant');
$user = getCurrentUser();

// Check if restaurant already exists
$checkStmt = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE owner_id = ?");
$checkStmt->bind_param("i", $user['user_id']);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$name || !$location) {
        showMessage("Restaurant name and location are required", "error");
    } else {
        $createStmt = $conn->prepare("INSERT INTO restaurants (name, location, description, owner_id, status) VALUES (?, ?, ?, ?, 'pending')");
        $createStmt->bind_param("sssi", $name, $location, $description, $user['user_id']);
        
        if ($createStmt->execute()) {
            showMessage("Restaurant setup successful! Your restaurant is pending approval.", "success");
            redirect('dashboard.php');
        } else {
            showMessage("Failed to create restaurant", "error");
        }
        $createStmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup Your Restaurant - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <style>
    .setup-container { max-width: 600px; margin: 2rem auto; }
    .welcome-message { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 15px; text-align: center; margin-bottom: 2rem; }
    .welcome-message h2 { margin-bottom: 1rem; font-size: 1.8rem; }
    .restaurant-form { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 2.5rem; border-radius: 20px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); }
    .form-section { margin-bottom: 2rem; }
    .form-section h3 { color: #2c3e50; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #667eea; }
    textarea { min-height: 100px; resize: vertical; }
    .skip-link { text-align: center; margin-top: 1rem; }
    .skip-link a { color: #667eea; text-decoration: none; font-size: 0.9rem; }
    .skip-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="setup-container">
    <?php displayMessage(); ?>
    <div class="welcome-message">
      <h2>Welcome to KhudaLagse!</h2>
      <p>Let's set up your restaurant profile to start receiving orders</p>
    </div>
    
    <form class="restaurant-form" method="POST">
      <div class="form-section">
        <h3>Basic Information</h3>
        <input type="text" name="name" placeholder="Restaurant Name *" required>
        <input type="text" name="location" placeholder="Restaurant Address *" required>
        <textarea name="description" placeholder="Describe your restaurant (cuisine type, specialties, etc.)"></textarea>
      </div>
      
      <button type="submit" class="btn-primary w-full">Setup My Restaurant</button>
      
      <div class="skip-link">
        <a href="dashboard.php">Skip for now (you can setup later)</a>
      </div>
    </form>
  </div>
</body>
</html>
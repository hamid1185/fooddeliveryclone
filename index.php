<?php
require_once 'config/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'customer':
            header("Location: /customer/dashboard.php");
            break;
        case 'restaurant':
            header("Location: /restaurant/dashboard.php");
            break;
        case 'admin':
            header("Location: /admin/dashboard.php");
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Food Delivery - Home</title>
  <link rel="stylesheet" href="frontend/style.css">
</head>
<body>
  <header>
    <h1>🍔 Online Food Delivery</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="login.php">Login</a>
      <a href="signup.php">Signup</a>
    </nav>
  </header>

  <div class="welcome-section">
    <h2>Welcome to KhudaLagse</h2>
    <p>Order delicious food online from your favorite restaurants!</p>
    <a href="login.php" class="cta-button">Get Started</a>
  </div>
</body>
</html>
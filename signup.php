<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    switch($role) {
        case 'customer':
            redirect('customer/dashboard.php');
            break;
        case 'restaurant':
            redirect('restaurant/dashboard.php');
            break;
        case 'admin':
            redirect('admin/dashboard.php');
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    
    if (!$name || !$email || !$password) {
        showMessage("All fields are required", "error");
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $_SESSION['user_id'] = $userId;
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            
            showMessage("Account created successfully!", "success");
            
            switch($role) {
                case 'customer':
                    redirect('customer/dashboard.php');
                    break;
                case 'restaurant':
                    redirect('restaurant/setup.php');
                    break;
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
            }
        } else {
            showMessage("Email already exists or database error", "error");
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Signup</title>
  <link rel="stylesheet" href="frontend/style.css">
</head>
<body>
  <header>
    <h1>🍔 Join KhudaLagse</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="login.php">Login</a>
    </nav>
  </header>
  <div class="container">
    <?php displayMessage(); ?>
    <h2 class="text-center mb-2">Create Your Account</h2>
    <form method="POST">
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <select name="role" required>
        <option value="">Select Account Type</option>
        <option value="customer">Customer</option>
        <option value="restaurant">Restaurant Owner</option>
      </select>
      <button type="submit">Create Account</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
  </div>
</body>
</html>
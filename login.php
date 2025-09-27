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
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        showMessage("Email and password are required", "error");
    } else {
        $stmt = $conn->prepare("SELECT user_id, name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $user['role'];
                
                switch($user['role']) {
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
            } else {
                showMessage("Invalid password", "error");
            }
        } else {
            showMessage("User not found", "error");
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link rel="stylesheet" href="frontend/style.css">
</head>
<body>
  <header>
    <h1>🍔 Login to KhudaLagse</h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="signup.php">Signup</a>
    </nav>
  </header>
  <div class="container">
    <?php displayMessage(); ?>
    <h2 class="text-center mb-2">Welcome Back!</h2>
    <form method="POST">
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Signup</a></p>
  </div>
</body>
</html>
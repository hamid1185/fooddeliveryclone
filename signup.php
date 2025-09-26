<?php
require_once 'config/database.php';
require_once 'config/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer';
    
    if (!$name || !$email || !$password) {
        $error = "All fields are required";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            
            // Auto login after signup
            $_SESSION['user_id'] = $userId;
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
            
            // Redirect based on role
            switch($role) {
                case 'customer':
                    header("Location: /customer/dashboard.php");
                    break;
                case 'restaurant':
                    header("Location: /restaurant/setup.php");
                    break;
            }
            exit;
        } else {
            $error = "Email already exists or database error";
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
    <h2 class="text-center mb-2">Create Your Account</h2>
    <?php if ($error): ?>
      <div style="color: red; text-align: center; margin-bottom: 1rem;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
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
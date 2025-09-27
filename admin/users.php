<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');
$user = getCurrentUser();

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    if ($user_id != $user['user_id']) { // Don't allow admin to delete themselves
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->bind_param("i", $user_id);
        
        if ($deleteStmt->execute()) {
            showMessage("User deleted successfully", "success");
        } else {
            showMessage("Failed to delete user", "error");
        }
        $deleteStmt->close();
    } else {
        showMessage("You cannot delete your own account", "error");
    }
}

// Get all users
$usersStmt = $conn->prepare("SELECT user_id, name, email, role, created_at FROM users ORDER BY created_at DESC");
$usersStmt->execute();
$users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Users</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/admin/admin.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Admin Portal</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="users.php" class="active">Users</a>
      <a href="restaurants.php">Restaurants</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <h2>All Users</h2>
    
    <?php if (empty($users)): ?>
      <p>No users found.</p>
    <?php else: ?>
      <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
        <thead>
          <tr style="background: #f8f9fa;">
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">ID</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Name</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Email</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Role</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Joined</th>
            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid #dee2e6;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $userData): ?>
            <tr style="border-bottom: 1px solid #dee2e6;">
              <td style="padding: 1rem;"><?php echo $userData['user_id']; ?></td>
              <td style="padding: 1rem;"><?php echo htmlspecialchars($userData['name']); ?></td>
              <td style="padding: 1rem;"><?php echo htmlspecialchars($userData['email']); ?></td>
              <td style="padding: 1rem;">
                <span class="status status-<?php echo $userData['role']; ?>"><?php echo $userData['role']; ?></span>
              </td>
              <td style="padding: 1rem;"><?php echo formatDate($userData['created_at']); ?></td>
              <td style="padding: 1rem;">
                <?php if ($userData['user_id'] != $user['user_id']): ?>
                  <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?')">
                    <input type="hidden" name="user_id" value="<?php echo $userData['user_id']; ?>">
                    <button type="submit" name="delete_user" class="btn-danger" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Delete</button>
                  </form>
                <?php else: ?>
                  <span style="color: #666; font-style: italic;">Current User</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
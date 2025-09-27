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

if (!$restaurant || $restaurant['status'] !== 'approved') {
    redirect('dashboard.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category = trim($_POST['category']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        if ($name && $price && $category) {
            $image_url = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../backend/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $target = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image_url = 'uploads/' . $filename;
                }
            }
            
            $insertStmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, name, description, price, category, is_available, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issdsis", $restaurant['restaurant_id'], $name, $description, $price, $category, $is_available, $image_url);
            
            if ($insertStmt->execute()) {
                showMessage("Menu item added successfully!", "success");
            } else {
                showMessage("Failed to add menu item", "error");
            }
            $insertStmt->close();
        } else {
            showMessage("Name, price, and category are required", "error");
        }
    }
    
    if (isset($_POST['update_item'])) {
        $menu_item_id = $_POST['menu_item_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $category = trim($_POST['category']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        if ($name && $price && $category) {
            $image_url = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../backend/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $target = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image_url = 'uploads/' . $filename;
                }
            }
            
            if ($image_url) {
                $updateStmt = $conn->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, is_available = ?, image_url = ? WHERE menu_item_id = ? AND restaurant_id = ?");
                $updateStmt->bind_param("ssdsisii", $name, $description, $price, $category, $is_available, $image_url, $menu_item_id, $restaurant['restaurant_id']);
            } else {
                $updateStmt = $conn->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, category = ?, is_available = ? WHERE menu_item_id = ? AND restaurant_id = ?");
                $updateStmt->bind_param("ssdsiii", $name, $description, $price, $category, $is_available, $menu_item_id, $restaurant['restaurant_id']);
            }
            
            if ($updateStmt->execute()) {
                showMessage("Menu item updated successfully!", "success");
            } else {
                showMessage("Failed to update menu item", "error");
            }
            $updateStmt->close();
        } else {
            showMessage("Name, price, and category are required", "error");
        }
    }
    
    if (isset($_POST['delete_item'])) {
        $menu_item_id = $_POST['menu_item_id'];
        
        $deleteStmt = $conn->prepare("DELETE FROM menu_items WHERE menu_item_id = ? AND restaurant_id = ?");
        $deleteStmt->bind_param("ii", $menu_item_id, $restaurant['restaurant_id']);
        
        if ($deleteStmt->execute()) {
            showMessage("Menu item deleted successfully!", "success");
        } else {
            showMessage("Failed to delete menu item", "error");
        }
        $deleteStmt->close();
    }
    
    if (isset($_POST['toggle_availability'])) {
        $menu_item_id = $_POST['menu_item_id'];
        $is_available = $_POST['is_available'];
        
        $toggleStmt = $conn->prepare("UPDATE menu_items SET is_available = ? WHERE menu_item_id = ? AND restaurant_id = ?");
        $toggleStmt->bind_param("iii", $is_available, $menu_item_id, $restaurant['restaurant_id']);
        
        if ($toggleStmt->execute()) {
            showMessage("Item availability updated!", "success");
        } else {
            showMessage("Failed to update availability", "error");
        }
        $toggleStmt->close();
    }
}

// Get menu items
$menuStmt = $conn->prepare("SELECT * FROM menu_items WHERE restaurant_id = ? ORDER BY category, name");
$menuStmt->bind_param("i", $restaurant['restaurant_id']);
$menuStmt->execute();
$menuItems = $menuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$menuStmt->close();

// Get categories
$categories = array_unique(array_column($menuItems, 'category'));

$currentCategory = $_GET['category'] ?? 'all';
$displayItems = $currentCategory === 'all' ? $menuItems : array_filter($menuItems, function($item) use ($currentCategory) {
    return $item['category'] === $currentCategory;
});

$editItem = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $editItem = array_filter($menuItems, function($item) use ($editId) {
        return $item['menu_item_id'] == $editId;
    });
    $editItem = reset($editItem);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu Management - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/restaurant/restaurant.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Restaurant Portal</h1>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="menu.php" class="active">Manage Menu</a>
      <a href="orders.php">Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="dashboard-header">
      <h1>Menu Management</h1>
      <a href="?add=1" class="btn-primary">Add New Item</a>
    </div>

    <!-- Add/Edit Form -->
    <?php if (isset($_GET['add']) || $editItem): ?>
      <section>
        <div class="card">
          <h3><?php echo $editItem ? 'Edit Menu Item' : 'Add Menu Item'; ?></h3>
          <form method="POST" enctype="multipart/form-data">
            <?php if ($editItem): ?>
              <input type="hidden" name="menu_item_id" value="<?php echo $editItem['menu_item_id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
              <h4>Basic Information</h4>
              <input type="text" name="name" placeholder="Item Name *" value="<?php echo $editItem ? htmlspecialchars($editItem['name']) : ''; ?>" required>
              <textarea name="description" placeholder="Item Description"><?php echo $editItem ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
              <input type="number" name="price" placeholder="Price (৳) *" step="0.01" min="0" value="<?php echo $editItem ? $editItem['price'] : ''; ?>" required>
            </div>
            
            <div class="form-section">
              <h4>Category & Availability</h4>
              <select name="category" required>
                <option value="">Select Category</option>
                <option value="appetizers" <?php echo ($editItem && $editItem['category'] === 'appetizers') ? 'selected' : ''; ?>>Appetizers</option>
                <option value="main" <?php echo ($editItem && $editItem['category'] === 'main') ? 'selected' : ''; ?>>Main Course</option>
                <option value="desserts" <?php echo ($editItem && $editItem['category'] === 'desserts') ? 'selected' : ''; ?>>Desserts</option>
                <option value="beverages" <?php echo ($editItem && $editItem['category'] === 'beverages') ? 'selected' : ''; ?>>Beverages</option>
                <option value="snacks" <?php echo ($editItem && $editItem['category'] === 'snacks') ? 'selected' : ''; ?>>Snacks</option>
              </select>
              
              <div class="availability-toggle">
                <label>
                  <input type="checkbox" name="is_available" <?php echo (!$editItem || $editItem['is_available']) ? 'checked' : ''; ?>>
                  Available for orders
                </label>
              </div>
            </div>
            
            <div class="form-section">
              <h4>Item Image</h4>
              <input type="file" name="image" accept="image/*">
              <small>Upload a photo of this menu item</small>
            </div>
            
            <div class="form-actions">
              <a href="menu.php" class="btn-secondary">Cancel</a>
              <button type="submit" name="<?php echo $editItem ? 'update_item' : 'add_item'; ?>" class="btn-primary">
                <?php echo $editItem ? 'Update Item' : 'Save Item'; ?>
              </button>
            </div>
          </form>
        </div>
      </section>
    <?php endif; ?>

    <!-- Category Filters -->
    <section class="menu-actions">
      <div class="category-pills">
        <a href="?category=all" class="category-pill <?php echo $currentCategory === 'all' ? 'active' : ''; ?>">All Items</a>
        <?php foreach ($categories as $category): ?>
          <a href="?category=<?php echo urlencode($category); ?>" class="category-pill <?php echo $currentCategory === $category ? 'active' : ''; ?>">
            <?php echo htmlspecialchars(ucfirst($category)); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Menu Items -->
    <section class="menu-items-section">
      <?php if (empty($displayItems)): ?>
        <div class="empty-state">
          <h3>🍽️</h3>
          <p><?php echo $currentCategory === 'all' ? 'No menu items yet' : 'No items in ' . $currentCategory; ?></p>
          <a href="?add=1" class="btn-primary">Add Menu Item</a>
        </div>
      <?php else: ?>
        <?php foreach ($displayItems as $item): ?>
          <div class="menu-item-card">
            <div class="menu-item-image">
              <img src="<?php echo $item['image_url'] ? '../backend/' . $item['image_url'] : '../frontend/default.png'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
            </div>
            <div class="menu-item-info">
              <h4><?php echo htmlspecialchars($item['name']); ?></h4>
              <div><?php echo htmlspecialchars($item['category']); ?></div>
              <div><?php echo htmlspecialchars($item['description'] ?: 'No description'); ?></div>
              <div><?php echo formatPrice($item['price']); ?></div>
              <div class="<?php echo $item['is_available'] ? 'available' : 'unavailable'; ?>">
                <?php echo $item['is_available'] ? '✅ Available' : '❌ Unavailable'; ?>
              </div>
            </div>
            <div class="menu-item-actions">
              <a href="?edit=<?php echo $item['menu_item_id']; ?>" class="btn-secondary">Edit</a>
              
              <form method="POST" style="display: inline;">
                <input type="hidden" name="menu_item_id" value="<?php echo $item['menu_item_id']; ?>">
                <input type="hidden" name="is_available" value="<?php echo $item['is_available'] ? 0 : 1; ?>">
                <button type="submit" name="toggle_availability" class="btn-warning">
                  <?php echo $item['is_available'] ? 'Disable' : 'Enable'; ?>
                </button>
              </form>
              
              <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this item?')">
                <input type="hidden" name="menu_item_id" value="<?php echo $item['menu_item_id']; ?>">
                <button type="submit" name="delete_item" class="btn-danger">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</body>
</html>
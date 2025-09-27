<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('customer');
$user = getCurrentUser();

$restaurant_id = $_GET['id'] ?? 0;
if (!$restaurant_id) {
    redirect('dashboard.php');
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $menu_item_id = $_POST['menu_item_id'];
    $quantity = max(1, intval($_POST['quantity']));
    
    // Check if item already in cart
    $checkStmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE customer_id = ? AND menu_item_id = ?");
    $checkStmt->bind_param("ii", $user['user_id'], $menu_item_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        // Update quantity
        $newQuantity = $existing['quantity'] + $quantity;
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $updateStmt->bind_param("ii", $newQuantity, $existing['cart_id']);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Add new item
        $insertStmt = $conn->prepare("INSERT INTO cart (customer_id, menu_item_id, quantity) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iii", $user['user_id'], $menu_item_id, $quantity);
        $insertStmt->execute();
        $insertStmt->close();
    }
    
    showMessage("Item added to cart!", "success");
}

// Get restaurant info
$restaurantStmt = $conn->prepare("SELECT restaurant_id, name, location, description FROM restaurants WHERE restaurant_id = ? AND status = 'approved'");
$restaurantStmt->bind_param("i", $restaurant_id);
$restaurantStmt->execute();
$restaurant = $restaurantStmt->get_result()->fetch_assoc();
$restaurantStmt->close();

if (!$restaurant) {
    redirect('dashboard.php');
}

// Get menu items
$menuStmt = $conn->prepare("SELECT menu_item_id, name, description, price, category, image_url FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY category ASC, name ASC");
$menuStmt->bind_param("i", $restaurant_id);
$menuStmt->execute();
$menuItems = $menuStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$menuStmt->close();

// Group by category
$categories = [];
foreach ($menuItems as $item) {
    $categories[$item['category']][] = $item;
}

// Get cart count
$cartStmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?");
$cartStmt->bind_param("i", $user['user_id']);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();
$cartCount = $cartResult->fetch_assoc()['total'] ?? 0;
$cartStmt->close();

$currentCategory = $_GET['category'] ?? 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restaurant Menu - KhudaLagse</title>
  <link rel="stylesheet" href="../frontend/global.css">
  <link rel="stylesheet" href="../frontend/customer/customer.css">
</head>
<body>
  <header>
    <h1>KhudaLagse - Customer Portal</h1>
    <nav>
      <a href="dashboard.php">Browse Restaurants</a>
      <a href="cart.php">Cart (<?php echo $cartCount; ?>)</a>
      <a href="orders.php">My Orders</a>
      <a href="../logout.php" class="btn-danger">Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php displayMessage(); ?>
    <div class="menu-header">
      <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
      <p>📍 <?php echo htmlspecialchars($restaurant['location']); ?></p>
      <p><?php echo htmlspecialchars($restaurant['description']); ?></p>
    </div>

    <div class="menu-categories">
      <a href="?id=<?php echo $restaurant_id; ?>&category=all" class="category-btn <?php echo $currentCategory === 'all' ? 'active' : ''; ?>">All Items</a>
      <?php foreach (array_keys($categories) as $category): ?>
        <a href="?id=<?php echo $restaurant_id; ?>&category=<?php echo urlencode($category); ?>" class="category-btn <?php echo $currentCategory === $category ? 'active' : ''; ?>">
          <?php echo htmlspecialchars(ucfirst($category)); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <section class="menu-section">
      <div class="grid grid-1">
        <?php 
        $displayItems = [];
        if ($currentCategory === 'all') {
            $displayItems = $menuItems;
        } else {
            $displayItems = $categories[$currentCategory] ?? [];
        }
        ?>
        
        <?php if (empty($displayItems)): ?>
          <div class="empty-message">
            <h3>🍽️</h3>
            <p>No menu items available</p>
          </div>
        <?php else: ?>
          <?php foreach ($displayItems as $item): ?>
            <div class="menu-item-card">
              <div class="menu-item-image">
                <img src="<?php echo $item['image_url'] ? '../backend/' . $item['image_url'] : '../frontend/default.png'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" />
              </div>
              <div class="menu-item-info">
                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                <div class="menu-item-description"><?php echo htmlspecialchars($item['description'] ?: ''); ?></div>
                <div class="menu-item-price"><?php echo formatPrice($item['price']); ?></div>
              </div>
              <div class="menu-item-actions">
                <form method="POST" style="display: flex; flex-direction: column; gap: 0.5rem;">
                  <input type="hidden" name="menu_item_id" value="<?php echo $item['menu_item_id']; ?>">
                  <div class="quantity-controls">
                    <input type="number" name="quantity" value="1" min="1" max="10" style="width: 60px; text-align: center;">
                  </div>
                  <button type="submit" name="add_to_cart" class="btn-primary">Add to Cart</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
</body>
</html>
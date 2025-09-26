<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireRole('customer');
$user = getCurrentUser();

$restaurantId = $_GET['id'] ?? 0;
if (!$restaurantId) {
    header("Location: dashboard.php");
    exit;
}

// Get restaurant info
$stmt = $conn->prepare("SELECT restaurant_id, name, location, description FROM restaurants WHERE restaurant_id = ? AND status = 'approved'");
$stmt->bind_param("i", $restaurantId);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    header("Location: dashboard.php");
    exit;
}

// Get menu items
$stmt = $conn->prepare("SELECT menu_item_id, name, description, price, category, image_url FROM menu_items WHERE restaurant_id = ? AND is_available = 1 ORDER BY category ASC, name ASC");
$stmt->bind_param("i", $restaurantId);
$stmt->execute();
$menuItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories
$categories = array_unique(array_column($menuItems, 'category'));

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $menuItemId = $_POST['menu_item_id'];
    $quantity = max(1, intval($_POST['quantity']));
    
    // Find menu item
    $menuItem = null;
    foreach ($menuItems as $item) {
        if ($item['menu_item_id'] == $menuItemId) {
            $menuItem = $item;
            break;
        }
    }
    
    if ($menuItem) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if item already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$cartItem) {
            if ($cartItem['menu_item_id'] == $menuItemId) {
                $cartItem['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'menu_item_id' => $menuItemId,
                'restaurant_id' => $restaurantId,
                'name' => $menuItem['name'],
                'price' => $menuItem['price'],
                'quantity' => $quantity
            ];
        }
        
        header("Location: restaurant.php?id=$restaurantId&added=1");
        exit;
    }
}

// Get cart count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

$filterCategory = $_GET['category'] ?? 'all';
$filteredItems = $filterCategory === 'all' ? $menuItems : array_filter($menuItems, function($item) use ($filterCategory) {
    return $item['category'] === $filterCategory;
});
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
    <?php if (isset($_GET['added'])): ?>
      <div style="background: #00b894; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
        Item added to cart successfully!
      </div>
    <?php endif; ?>
    
    <div class="menu-header">
      <h2><?php echo htmlspecialchars($restaurant['name']); ?></h2>
      <p>📍 <?php echo htmlspecialchars($restaurant['location']); ?></p>
      <p><?php echo htmlspecialchars($restaurant['description'] ?: ''); ?></p>
    </div>

    <div class="menu-categories">
      <a href="restaurant.php?id=<?php echo $restaurantId; ?>" class="category-btn <?php echo $filterCategory === 'all' ? 'active' : ''; ?>">All Items</a>
      <?php foreach ($categories as $category): ?>
        <a href="restaurant.php?id=<?php echo $restaurantId; ?>&category=<?php echo urlencode($category); ?>" 
           class="category-btn <?php echo $filterCategory === $category ? 'active' : ''; ?>">
          <?php echo htmlspecialchars(ucfirst($category)); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <section class="menu-section">
      <div class="grid grid-1">
        <?php if (empty($filteredItems)): ?>
          <div class="empty-message">
            <h3>🍽️</h3>
            <p>No menu items available</p>
          </div>
        <?php else: ?>
          <?php foreach ($filteredItems as $item): ?>
            <div class="menu-item-card">
              <div class="menu-item-image">
                <img src="<?php echo $item['image_url'] ? '../backend/' . $item['image_url'] : '../frontend/default.png'; ?>" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>" />
              </div>
              <div class="menu-item-info">
                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                <div class="menu-item-description"><?php echo htmlspecialchars($item['description'] ?: ''); ?></div>
                <div class="menu-item-price">৳<?php echo number_format($item['price'], 2); ?></div>
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
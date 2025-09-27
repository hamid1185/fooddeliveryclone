# KhudaLagse - Food Delivery System

A complete monolithic PHP food delivery system with server-side rendering.

## Features

### Customer Features
- Browse restaurants
- View restaurant menus with categories
- Add items to cart
- Place orders with delivery information
- Track order history and status

### Restaurant Owner Features
- Restaurant registration and setup
- Menu management (add, edit, delete items)
- Upload item images
- Order management and status updates
- Dashboard with statistics

### Admin Features
- Approve/reject restaurant applications
- User management
- Restaurant management
- System overview and statistics
- Recent orders monitoring

## Installation

1. **Database Setup**
   ```sql
   -- Import the database schema from sql/db.sql
   CREATE DATABASE food_delivery;
   -- Run the SQL commands from sql/db.sql
   ```

2. **Configuration**
   - Update database credentials in `config/database.php`
   - Ensure PHP sessions are enabled
   - Create uploads directory: `mkdir backend/uploads` (for menu item images)

3. **Web Server**
   - Point document root to the project folder
   - Ensure `.htaccess` is enabled for clean URLs
   - PHP 7.4+ required

## Default Admin Account
- Email: admin@khudalagse.com
- Password: password

## File Structure

```
/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── auth.php             # Authentication functions
│   └── functions.php        # Utility functions
├── customer/
│   ├── dashboard.php        # Restaurant browsing
│   ├── restaurant.php       # Menu viewing
│   ├── cart.php            # Shopping cart
│   └── orders.php          # Order history
├── restaurant/
│   ├── setup.php           # Restaurant setup
│   ├── dashboard.php       # Restaurant dashboard
│   ├── menu.php           # Menu management
│   ├── orders.php         # Order management
│   └── edit.php           # Edit restaurant info
├── admin/
│   ├── dashboard.php       # Admin dashboard
│   ├── users.php          # User management
│   └── restaurants.php    # Restaurant management
├── frontend/              # CSS files
├── sql/
│   └── db.sql            # Database schema
├── index.php             # Homepage
├── login.php             # Login page
├── signup.php            # Registration page
└── logout.php            # Logout handler
```

## Key Technologies

- **Backend**: Pure PHP with MySQLi
- **Frontend**: HTML, CSS (no JavaScript frameworks)
- **Database**: MySQL
- **Session Management**: PHP Sessions
- **File Uploads**: Native PHP file handling
- **Authentication**: Password hashing with PHP's password_hash()

## Security Features

- Password hashing
- SQL injection prevention with prepared statements
- Session-based authentication
- Role-based access control
- CSRF protection through form validation
- Input sanitization and validation

## Usage

1. **Customer Flow**:
   - Register as customer
   - Browse restaurants
   - Add items to cart
   - Place order with delivery details
   - Track order status

2. **Restaurant Owner Flow**:
   - Register as restaurant owner
   - Set up restaurant profile
   - Wait for admin approval
   - Manage menu items
   - Process incoming orders

3. **Admin Flow**:
   - Login with admin credentials
   - Approve/reject restaurant applications
   - Monitor system activity
   - Manage users and restaurants

## Development Notes

- All forms use POST method for data submission
- Server-side validation on all inputs
- Responsive design for mobile compatibility
- Clean URLs with .htaccess rewriting
- Error handling with user-friendly messages
- File upload handling for menu item images
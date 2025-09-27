<?php
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M j, Y g:i A');
}

function formatPrice($price) {
    return '৳' . number_format($price, 2);
}

function getStatusClass($status) {
    $classes = [
        'pending' => 'status-pending',
        'preparing' => 'status-preparing',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected'
    ];
    
    return $classes[$status] ?? '';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function showMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        echo "<div class='message message-$type'>$message</div>";
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}
?>
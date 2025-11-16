<?php
// Security Functions

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/quickmed/login.php');
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect('/quickmed/login.php');
    }
}

// Generate random verification code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send email (basic version - you can integrate PHPMailer later)
function sendEmail($to, $subject, $message) {
    // For now, just log it (in production, use PHPMailer or similar)
    error_log("Email to $to: $subject - $message");
    return true;
}

// Image upload handler
function uploadImage($file, $directory = 'products') {
    $target_dir = __DIR__ . "/../assets/images/uploads/$directory/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file['size'] > 5000000) { // 5MB
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $new_filename];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

// Format currency
function formatPrice($amount) {
    return '৳' . number_format($amount, 2);
}

// Generate order number
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Generate invoice number
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Audit log
function logActivity($pdo, $user_id, $action, $table_name, $record_id, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

// Get membership number from email
function getMembershipNumber($email) {
    $parts = explode('@', $email);
    return strtoupper($parts[0]);
}

// Calculate points earned (100 points per 1000 BDT)
function calculatePoints($amount) {
    return floor($amount / 1000) * 100;
}

// JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
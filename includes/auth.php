<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function createUser($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, verification_code, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $verification_code = ($data['role'] !== 'customer') ? generateVerificationCode() : null;
    $is_verified = ($data['role'] === 'customer') ? 1 : 0;
    
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'] ?? null,
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['role'] ?? 'customer',
        $verification_code,
        $is_verified
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Send verification email if not customer
    if ($data['role'] !== 'customer') {
        sendEmail(
            $data['email'],
            'QuickMed - Verification Code',
            "Your verification code is: $verification_code"
        );
    }
    
    // Give signup bonus points to customers
    if ($data['role'] === 'customer') {
        $stmt = $pdo->prepare("
            INSERT INTO point_history (user_id, points, type, description)
            VALUES (?, 100, 'earned', 'Signup Bonus')
        ");
        $stmt->execute([$user_id]);
        
        $pdo->prepare("UPDATE users SET points = 100 WHERE id = ?")->execute([$user_id]);
    }
    
    return $user_id;
}

function verifyUser($pdo, $email, $code) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_verified = 1, verification_code = NULL 
        WHERE email = ? AND verification_code = ?
    ");
    $stmt->execute([$email, $code]);
    return $stmt->rowCount() > 0;
}

function login($pdo, $email, $password) {
    $user = getUserByEmail($pdo, $email);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    if (!$user['is_verified']) {
        return ['success' => false, 'message' => 'Please verify your account first'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account has been deactivated'];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['shop_id'] = $user['shop_id'];
    
    logActivity($pdo, $user['id'], 'LOGIN', 'users', $user['id'], 'User logged in');
    
    return ['success' => true, 'role' => $user['role']];
}

function logout() {
    session_destroy();
    redirect('/quickmed/login.php');
}
?>
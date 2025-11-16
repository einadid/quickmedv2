<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function verifyCompanyCode($pdo, $code, $role) {
    $stmt = $pdo->prepare("
        SELECT * FROM verification_codes 
        WHERE code = ? AND role = ? AND is_used = 0
    ");
    $stmt->execute([$code, $role]);
    return $stmt->fetch();
}

function markCodeAsUsed($pdo, $code, $user_id) {
    $stmt = $pdo->prepare("
        UPDATE verification_codes 
        SET is_used = 1, used_by = ?, used_at = NOW() 
        WHERE code = ?
    ");
    $stmt->execute([$user_id, $code]);
}

function createUser($pdo, $data) {
    // If not customer, verification code is required
    if ($data['role'] !== 'customer') {
        if (empty($data['verification_code'])) {
            throw new Exception('Verification code is required for this role');
        }
        
        // Verify the code
        $codeData = verifyCompanyCode($pdo, $data['verification_code'], $data['role']);
        if (!$codeData) {
            throw new Exception('Invalid or already used verification code');
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'] ?? null,
        password_hash($data['password'], PASSWORD_DEFAULT),
        $data['role'] ?? 'customer'
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Mark code as used if not customer
    if ($data['role'] !== 'customer') {
        markCodeAsUsed($pdo, $data['verification_code'], $user_id);
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
    
    // Log activity
    logActivity($pdo, $user_id, 'USER_REGISTERED', 'users', $user_id, "New {$data['role']} registered");
    
    return $user_id;
}

// Auto login function
function autoLogin($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['shop_id'] = $user['shop_id'];
        return true;
    }
    return false;
}

function login($pdo, $email, $password) {
    $user = getUserByEmail($pdo, $email);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid credentials'];
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
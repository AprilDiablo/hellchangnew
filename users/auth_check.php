<?php
// 세션 시작
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function checkUserAuth() {
    // 세션 체크
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $session_token = $_SESSION['session_token'];
    
    // 데이터베이스 연결
    $pdo = getDB();
    
    // 데이터베이스에서 세션 유효성 확인
    $stmt = $pdo->prepare('
        SELECT u.*, s.session_token, s.expires_at 
        FROM users u 
        JOIN sessions s ON u.id = s.user_id 
        WHERE u.id = ? AND s.session_token = ? AND s.expires_at > NOW()
    ');
    $stmt->execute([$user_id, $session_token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // 세션이 유효하지 않으면 세션 삭제
        session_destroy();
        return false;
    }
    
    return $user;
}

function requireUserAuth() {
    $user = checkUserAuth();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function getUserInfo() {
    return checkUserAuth();
}

// 추가된 함수들
function isLoggedIn() {
    return checkUserAuth() !== false;
}

function getCurrentUser() {
    return checkUserAuth();
}
?> 
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 관리자 로그인 상태 확인 함수
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 현재 관리자 정보 가져오기
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'name' => $_SESSION['admin_name'],
        'role' => $_SESSION['admin_role']
    ];
}

// 관리자 로그인 확인 (기존 로직 유지)
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
} 
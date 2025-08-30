<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();

// 세션 토큰이 있으면 데이터베이스에서 삭제
if (isset($_SESSION['session_token'])) {
    $stmt = $pdo->prepare('DELETE FROM sessions WHERE session_token = ?');
    $stmt->execute([$_SESSION['session_token']]);
}

// 세션 삭제
session_destroy();

// 로그인 페이지로 리다이렉트
header('Location: login.php');
exit;
?> 
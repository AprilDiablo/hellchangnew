<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_check.php';

// 사용자 인증 확인
$user = requireUserAuth();

// POST 요청 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '잘못된 요청 방식입니다.']);
    exit;
}

// 운동명 확인
$exercise_name = trim($_POST['exercise_name'] ?? '');
if (empty($exercise_name)) {
    echo json_encode(['success' => false, 'message' => '운동명을 입력해주세요.']);
    exit;
}

try {
    $pdo = getDB();
    
    // 이미 요청된 운동인지 확인
    $stmt = $pdo->prepare('SELECT id FROM exercise_requests WHERE user_id = ? AND exercise_name = ? AND status = "pending"');
    $stmt->execute([$user['id'], $exercise_name]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '이미 등록 요청된 운동입니다.']);
        exit;
    }
    
    // 등록 요청 저장
    $stmt = $pdo->prepare('INSERT INTO exercise_requests (user_id, exercise_name) VALUES (?, ?)');
    $result = $stmt->execute([$user['id'], $exercise_name]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '등록 요청이 완료되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB 저장에 실패했습니다.']);
    }
    
} catch (Exception $e) {
    error_log('Exercise request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다: ' . $e->getMessage()]);
}
?>

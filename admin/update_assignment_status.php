<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

// 관리자 인증 확인
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

// JSON 입력 받기
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$assignment_id = $input['assignment_id'] ?? null;
$status = $input['status'] ?? null;

if (!$assignment_id || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.']);
    exit;
}

$pdo = getDB();

try {
    // 할당 상태 업데이트
    $stmt = $pdo->prepare('
        UPDATE m_template_assignment 
        SET status = ?, updated_at = NOW() 
        WHERE assignment_id = ?
    ');
    $stmt->execute([$status, $assignment_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => '상태가 업데이트되었습니다.'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '할당을 찾을 수 없습니다.'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '업데이트 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>

<?php
session_start();
require_once '../config/database.php';

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// 데이터베이스 연결
$pdo = getDB();

// JSON 데이터 받기
$input = json_decode(file_get_contents('php://input'), true);

// 디버깅 로그
error_log("루틴 기록 삭제 요청 - 세션 ID: " . ($_SESSION['user_id'] ?? 'null'));
error_log("받은 데이터: " . json_encode($input));

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 입력 데이터 검증
if (!isset($input['session_id']) || !isset($input['routine_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$session_id = $input['session_id'];
$routine_type = $input['routine_type'];

// 루틴 타입 검증
if (!in_array($routine_type, ['pre', 'post'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 루틴 타입입니다.']);
    exit;
}

try {
    // 세션 소유권 확인
    $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    if (!$session || $session['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
        exit;
    }
    
    // 기존 루틴 기록 삭제
    $stmt = $pdo->prepare("DELETE FROM m_routine_records WHERE session_id = ? AND routine_type = ?");
    $result = $stmt->execute([$session_id, $routine_type]);
    
    if ($result) {
        $deletedCount = $stmt->rowCount();
        error_log("루틴 기록 삭제 성공 - 삭제된 레코드 수: $deletedCount");
        echo json_encode([
            'success' => true, 
            'message' => '루틴 기록이 삭제되었습니다.',
            'deleted_count' => $deletedCount
        ]);
    } else {
        error_log("루틴 기록 삭제 실패");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '루틴 기록 삭제에 실패했습니다.']);
    }
    
} catch (Exception $e) {
    error_log("루틴 기록 삭제 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}
?>

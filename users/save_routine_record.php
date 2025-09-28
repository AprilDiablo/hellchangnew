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
error_log("루틴 기록 저장 요청 - 세션 ID: " . ($_SESSION['user_id'] ?? 'null'));
error_log("받은 데이터: " . json_encode($input));

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 입력 데이터 검증
if (!isset($input['session_id']) || !isset($input['routine_type']) || !isset($input['is_completed']) || !isset($input['duration'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 데이터가 누락되었습니다.']);
    exit;
}

$session_id = $input['session_id'];
$routine_type = $input['routine_type'];
$is_completed = $input['is_completed'] ? 1 : 0;
$duration = (int)$input['duration'];
$option = $input['option'] ?? 'new'; // 기본값은 'new'

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
    
    // 사용자의 루틴 설정 가져오기
    $stmt = $pdo->prepare("SELECT pre_routine, post_routine FROM m_routine_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $routine_settings = $stmt->fetch();
    
    if (!$routine_settings) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '루틴 설정을 찾을 수 없습니다.']);
        exit;
    }
    
    // 루틴 내용 결정
    $routine_content = '';
    $routine_name = '';
    
    if ($routine_type === 'pre') {
        $routine_content = $routine_settings['pre_routine'] ?? '';
        $routine_name = 'PRE-ROUTINE';
    } else {
        $routine_content = $routine_settings['post_routine'] ?? '';
        $routine_name = 'POST-ROUTINE';
    }
    
    if (empty($routine_content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '해당 루틴 내용이 설정되지 않았습니다.']);
        exit;
    }
    
    // 현재 시간 계산
    $start_time = date('Y-m-d H:i:s', time() - $duration);
    $end_time = date('Y-m-d H:i:s');
    
    // 루틴 기록 저장
    if ($option === 'replace') {
        // 덮어쓰기 모드: 기존 기록 삭제 후 새로 삽입
        $stmt = $pdo->prepare("DELETE FROM m_routine_records WHERE session_id = ? AND routine_type = ?");
        $stmt->execute([$session_id, $routine_type]);
        error_log("기존 루틴 기록 삭제 완료");
    }
    
    // 새 기록 삽입
    $stmt = $pdo->prepare("
        INSERT INTO m_routine_records 
        (user_id, session_id, routine_type, routine_name, routine_content, is_completed, start_time, end_time, duration) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $user_id,
        $session_id,
        $routine_type,
        $routine_name,
        $routine_content,
        $is_completed,
        $start_time,
        $end_time,
        $duration
    ]);
    
    if ($result) {
        $record_id = $pdo->lastInsertId();
        error_log("루틴 기록 저장 성공 - Record ID: $record_id");
        echo json_encode([
            'success' => true, 
            'message' => '루틴 기록이 저장되었습니다.',
            'record_id' => $record_id
        ]);
    } else {
        error_log("루틴 기록 저장 실패");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '루틴 기록 저장에 실패했습니다.']);
    }
    
} catch (Exception $e) {
    error_log("루틴 기록 저장 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.']);
}
?>

<?php
require_once '../config/database.php';
require_once 'auth_check.php';

// 사용자 인증 확인
requireUserAuth();

// JSON 입력 받기
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$pdo = getDB();

try {
    $pdo->beginTransaction();
    
    $wx_id = $input['wx_id'] ?? null;
    $completed_sets = $input['completed_sets'] ?? 0;
    $total_sets = $input['total_sets'] ?? 0;
    $total_time = $input['total_time'] ?? 0; // 총 운동 시간(초)
    $set_times = $input['set_times'] ?? []; // 각 세트별 완료 시간
    
    if (!$wx_id) {
        throw new Exception('운동 ID가 필요합니다.');
    }
    
    // 기존 세트 기록 삭제 (중복 방지)
    $stmt = $pdo->prepare("DELETE FROM m_workout_set WHERE wx_id = ?");
    $stmt->execute([$wx_id]);
    
    // 운동 정보 가져오기
    $stmt = $pdo->prepare("
        SELECT we.weight, we.reps, we.sets 
        FROM m_workout_exercise we 
        WHERE we.wx_id = ?
    ");
    $stmt->execute([$wx_id]);
    $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercise) {
        throw new Exception('운동 정보를 찾을 수 없습니다.');
    }
    
    $weight = $exercise['weight'];
    $reps = $exercise['reps'];
    $planned_sets = $exercise['sets'];
    
    // 완료된 세트만큼 기록 저장
    for ($i = 1; $i <= $completed_sets; $i++) {
        $set_time = isset($set_times[$i-1]) ? $set_times[$i-1] : 0;
        
        // 세트 아래에 찍힌 숫자를 그대로 rest_time으로 사용
        $rest_time = $set_time;
        
        $stmt = $pdo->prepare("
            INSERT INTO m_workout_set 
            (wx_id, set_no, weight, reps, completed_at, rest_time, total_time) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$wx_id, $i, $weight, $reps, $rest_time, $total_time]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '운동 기록이 저장되었습니다.',
        'data' => [
            'completed_sets' => $completed_sets,
            'total_sets' => $total_sets,
            'total_time' => $total_time
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '운동 기록 저장 중 오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>

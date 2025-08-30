<?php
session_start();
require_once 'auth_check.php';
require_once __DIR__ . '/../config/database.php';

// JSON 응답 헤더 복구
header('Content-Type: application/json');

try {
    // 로그인 확인
    if (!isLoggedIn()) {
        throw new Exception('로그인이 필요합니다.');
    }

    $user = getCurrentUser();
    if (!$user) {
        throw new Exception('사용자 정보를 가져올 수 없습니다.');
    }

    // POST 데이터 읽기
    $input = file_get_contents('php://input');
    $workouts = json_decode($input, true);

    if (!$workouts || !is_array($workouts)) {
        throw new Exception('운동 데이터가 올바르지 않습니다.');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        // 오늘 날짜의 운동 세션 생성 또는 가져오기
        $today = date('Y-m-d');
        
        $stmt = $pdo->prepare('
            INSERT INTO m_workout_session (user_id, workout_date, note) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE session_id = LAST_INSERT_ID(session_id)
        ');
        $stmt->execute([$user['id'], $today, '오늘의 운동']);
        
        $sessionId = $pdo->lastInsertId();
        if (!$sessionId) {
            // 이미 존재하는 세션 ID 가져오기
            $stmt = $pdo->prepare('SELECT session_id FROM m_workout_session WHERE user_id = ? AND workout_date = ?');
            $stmt->execute([$user['id'], $today]);
            $sessionId = $stmt->fetchColumn();
        }

        // 운동 기록 저장
        foreach ($workouts as $index => $workout) {
            $stmt = $pdo->prepare('
                INSERT INTO m_workout_exercise 
                (session_id, ex_id, order_no, weight, reps, sets, original_exercise_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $sessionId,
                $workout['exercise_id'],
                $index + 1,
                $workout['weight'],
                $workout['reps'],
                $workout['sets'],
                $workout['exercise_name']
            ]);
        }

        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '운동이 성공적으로 기록되었습니다.',
            'session_id' => $sessionId,
            'redirect_url' => 'my_workouts.php?date=' . $today
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

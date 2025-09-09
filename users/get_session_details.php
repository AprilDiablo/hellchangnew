<?php
session_start();
require_once 'auth_check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    if (!isLoggedIn()) {
        throw new Exception('로그인이 필요합니다.');
    }

    $user = getCurrentUser();
    if (!$user) {
        throw new Exception('사용자 정보를 가져올 수 없습니다.');
    }

    if (!isset($_POST['session_id']) || empty($_POST['session_id'])) {
        throw new Exception('세션 ID가 필요합니다.');
    }

    $sessionId = $_POST['session_id'];

    $pdo = getDB();
    
    // 세션이 해당 사용자의 것인지 확인
    $stmt = $pdo->prepare('
        SELECT session_id 
        FROM m_workout_session 
        WHERE session_id = ? AND user_id = ?
    ');
    $stmt->execute([$sessionId, $user['id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception('해당 세션을 찾을 수 없습니다.');
    }

    // 세션의 모든 운동 데이터 가져오기
    $stmt = $pdo->prepare('
        SELECT 
            we.wx_id,
            we.ex_id,
            we.weight,
            we.reps,
            we.sets,
            we.order_no,
            e.name_kr,
            e.name_en,
            e.equipment
        FROM m_workout_exercise we
        JOIN m_exercise e ON we.ex_id = e.ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$sessionId]);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'exercises' => $exercises
    ]);

} catch (Exception $e) {
    error_log("세션 상세 조회 오류: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>

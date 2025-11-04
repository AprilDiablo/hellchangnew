<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../config/database.php';

$kakao_id = isset($_GET['kakao_id']) ? (int)$_GET['kakao_id'] : 0;

$response = [
    'status' => 'error',
    'exists' => false,
    'message' => ''
];

if ($kakao_id <= 0) {
    $response['message'] = '유효하지 않은 kakao_id입니다.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE kakao_id = ?');
    $stmt->execute([$kakao_id]);
    
    if ($stmt->fetchColumn()) {
        // 사용자가 존재함
        $response['status'] = 'success';
        $response['exists'] = true;
    } else {
        // 사용자가 존재하지 않음
        $response['status'] = 'success';
        $response['exists'] = false;
    }
    http_response_code(200);

} catch (PDOException $e) {
    $response['message'] = 'DB 오류: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>

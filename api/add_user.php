<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/database.php';

$response = [
    'status' => 'error',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'POST 요청만 허용됩니다.';
    http_response_code(405);
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$kakao_id = $input['kakao_id'] ?? 0;
$username = $input['username'] ?? '사용자';
$email = $input['email'] ?? null;
$profile_image = $input['profile_image'] ?? null;

if ($kakao_id <= 0) {
    $response['message'] = '유효하지 않은 kakao_id입니다.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// 이메일이 없는 경우 카카오 ID 기반으로 고유한 이메일 생성
if (!$email) {
    $email = 'kakao_' . $kakao_id . '@hellchang.com';
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'INSERT INTO users (kakao_id, username, email, profile_image) VALUES (?, ?, ?, ?)'
    );
    
    $stmt->execute([$kakao_id, $username, $email, $profile_image]);

    $response['status'] = 'success';
    $response['message'] = '새로운 사용자가 성공적으로 추가되었습니다. (ID: ' . $pdo->lastInsertId() . ')';
    $response['user_id'] = $pdo->lastInsertId();
    http_response_code(201); // Created

} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        $response['message'] = 'INSERT 실패: 이미 존재하는 kakao_id입니다.';
        http_response_code(409); // Conflict
    } else {
        $response['message'] = 'DB 오류: ' . $e->getMessage();
        http_response_code(500);
    }
}

echo json_encode($response);
?>

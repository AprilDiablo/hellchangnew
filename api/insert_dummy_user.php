<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';

$response = [
    'status' => 'error',
    'message' => ''
];

// 고정된 더미 데이터 정의
$dummy_kakao_id = 9999999999; // 테스트를 위한 고유한 ID
$dummy_username = '더미 사용자 (테스트)';
$dummy_email = 'dummy_user_9999@example.com';
$dummy_profile_image = 'http://placehold.it/640x640';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'INSERT INTO users (kakao_id, username, email, profile_image) VALUES (?, ?, ?, ?)'
    );
    
    $stmt->execute([
        $dummy_kakao_id,
        $dummy_username,
        $dummy_email,
        $dummy_profile_image
    ]);

    $response['status'] = 'success';
    $response['message'] = '더미 사용자 (kakao_id: ' . $dummy_kakao_id . ')가 성공적으로 추가되었습니다. (ID: ' . $pdo->lastInsertId() . ')';
    http_response_code(201); // Created

} catch (PDOException $e) {
    // 오류 코드 23000은 보통 중복 키 오류입니다.
    if ($e->getCode() == '23000') {
        $response['message'] = 'INSERT 실패: 이미 kakao_id가 ' . $dummy_kakao_id . '인 사용자가 존재합니다. (정상적인 오류)';
        http_response_code(409); // Conflict
    } else {
        $response['message'] = 'DB 오류: ' . $e->getMessage();
        http_response_code(500); // Internal Server Error
    }
}

echo json_encode($response);
?>
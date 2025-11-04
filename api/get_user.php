<?php
// API는 JSON 형식으로 응답하고, CORS 문제를 방지하기 위해 헤더를 설정합니다.
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // 개발 중에는 모든 출처 허용, 실제 운영 시에는 앱 도메인으로 제한 권장

// DB 접속 파일을 불러옵니다.
require_once __DIR__ . '/../config/database.php';

// 요청된 kakao_id를 GET 파라미터에서 가져옵니다.
$kakao_id = isset($_GET['kakao_id']) ? (int)$_GET['kakao_id'] : 0;

// 응답을 위한 배열 초기화
$response = [
    'status' => 'error',
    'message' => '',
    'data' => null
];

if ($kakao_id <= 0) {
    $response['message'] = '유효하지 않은 kakao_id입니다.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDB();

    // 1. 사용자 조회
    $stmt = $pdo->prepare('SELECT * FROM users WHERE kakao_id = ?');
    $stmt->execute([$kakao_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 사용자가 존재할 경우
        $response['status'] = 'success';
        $response['message'] = '사용자 정보를 성공적으로 조회했습니다.';
        $response['data'] = $user;
        http_response_code(200); // OK
    } else {
        // 사용자가 존재하지 않을 경우 (추후 이곳에 신규 사용자 생성 로직 추가 가능)
        $response['status'] = 'not_found';
        $response['message'] = '데이터베이스에서 해당 사용자를 찾을 수 없습니다.';
        http_response_code(404); // Not Found
    }

} catch (PDOException $e) {
    // DB 에러 처리
    $response['message'] = '데이터베이스 오류: ' . $e->getMessage();
    http_response_code(500); // Internal Server Error
}

// 최종 응답을 JSON으로 출력
echo json_encode($response);
?>
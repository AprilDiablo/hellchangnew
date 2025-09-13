<?php
// 디버깅용 파일 - 넘어오는 값만 확인
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 받기
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

// form POST 데이터 받기
$form_data = null;
if (isset($_POST['workout_data'])) {
    $form_data = json_decode($_POST['workout_data'], true);
}

// 디버깅 정보 출력
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'raw_input' => $input,
    'json_data' => $json_data,
    'form_data' => $form_data,
    'post_data' => $_POST,
    'get_data' => $_GET,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not_set',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not_set'
];

// JSON으로 출력
echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>

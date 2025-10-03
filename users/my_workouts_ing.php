<?php
// 오류 처리 설정 - AJAX 요청에서는 JSON 응답을 보장
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // AJAX 요청인 경우 오류를 JSON으로 반환
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'PHP 오류가 발생했습니다: ' . $message,
                'error' => [
                    'severity' => $severity,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line
                ]
            ]);
            exit;
        }
    });
    
    // 예외 처리
    set_exception_handler(function($exception) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => '예외가 발생했습니다: ' . $exception->getMessage(),
            'error' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        ]);
        exit;
    });
}

session_start();
require_once 'auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 로그인 확인
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// 페이지 제목과 부제목 설정
$pageTitle = '오늘의 운동 기록';
$pageSubtitle = '운동 기록을 확인해보세요';

// 날짜 파라미터 (기본값: 오늘)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sessionIdParam = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

$message = isset($_GET['message']) ? $_GET['message'] : '';
$error = '';

// 수정/삭제 처리
if ($_POST) {
    $pdo = getDB();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'start_workout') {
                // 운동 시작시간 저장
                $session_id = $_POST['session_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 시작시간이 없으면 현재 시간으로 저장
                $stmt = $pdo->prepare("UPDATE m_workout_session SET start_time = NOW() WHERE session_id = ? AND start_time IS NULL");
                $stmt->execute([$session_id]);
                
                $message = "운동이 시작되었습니다.";
                
            } elseif ($_POST['action'] === 'end_workout') {
                // 운동 종료시간 저장
                $session_id = $_POST['session_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 종료시간이 없으면 현재 시간으로 저장
                $stmt = $pdo->prepare("UPDATE m_workout_session SET end_time = NOW() WHERE session_id = ? AND end_time IS NULL");
                $stmt->execute([$session_id]);
                
                $message = "운동이 종료되었습니다.";
                
            } elseif ($_POST['action'] === 'update_workout_time') {
                // 디버그: 받은 POST 데이터 확인
                error_log("=== update_workout_time 요청 시작 ===");
                error_log("POST 데이터: " . print_r($_POST, true));
                error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
                error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
                
                // 운동 시간 수정
                $session_id = $_POST['session_id'];
                $start_time_raw = $_POST['start_time'] ?: null;
                $end_time_raw = $_POST['end_time'] ?: null;
                
                // 시간을 DATETIME 형식으로 변환 (오늘 날짜 + 시간)
                $start_time = null;
                $end_time = null;
                
                if ($start_time_raw && $start_time_raw !== '시작시간') {
                    $today = date('Y-m-d');
                    $start_time = $today . ' ' . $start_time_raw . ':00';
                }
                
                if ($end_time_raw && $end_time_raw !== '종료시간') {
                    $today = date('Y-m-d');
                    $end_time = $today . ' ' . $end_time_raw . ':00';
                }
                
                error_log("파싱된 값들: session_id={$session_id}");
                error_log("원본 시간: start_time_raw={$start_time_raw}, end_time_raw={$end_time_raw}");
                error_log("변환된 시간: start_time={$start_time}, end_time={$end_time}");
                error_log("현재 사용자 ID: " . $user['id']);
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                $auth_result = $stmt->fetch();
                error_log("권한 확인 결과: " . ($auth_result ? '권한 있음' : '권한 없음'));
                
                if (!$auth_result) {
                    error_log("권한 없음으로 인한 실패");
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 시간 업데이트
                $update_sql = "UPDATE m_workout_session SET start_time = ?, end_time = ? WHERE session_id = ?";
                error_log("실행할 쿼리: " . $update_sql);
                error_log("쿼리 파라미터: start_time={$start_time}, end_time={$end_time}, session_id={$session_id}");
                
                $stmt = $pdo->prepare($update_sql);
                $result = $stmt->execute([$start_time, $end_time, $session_id]);
                
                error_log("쿼리 실행 결과: " . ($result ? '성공' : '실패'));
                if (!$result) {
                    $error_info = $stmt->errorInfo();
                    error_log("PDO 에러 정보: " . print_r($error_info, true));
                }
                
                // 영향받은 행 수 확인
                $affected_rows = $stmt->rowCount();
                error_log("영향받은 행 수: " . $affected_rows);
                
                // JSON 응답
                header('Content-Type: application/json');
                if ($result && $affected_rows > 0) {
                    error_log("성공 응답 전송");
                    echo json_encode(['success' => true, 'message' => '운동 시간이 수정되었습니다.']);
                } else {
                    error_log("실패 응답 전송");
                    echo json_encode(['success' => false, 'message' => '운동 시간 수정에 실패했습니다.']);
                }
                exit;
                
            } elseif ($_POST['action'] === 'delete_session') {
                // 운동 세션 삭제
                $session_id = $_POST['session_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '삭제 권한이 없습니다.']);
                    exit;
                }
                
                // 운동 세션 삭제 (CASCADE로 관련 운동들도 자동 삭제됨)
                $stmt = $pdo->prepare("DELETE FROM m_workout_session WHERE session_id = ?");
                $stmt->execute([$session_id]);
                
                $message = "운동 세션이 성공적으로 삭제되었습니다.";
                
            } elseif ($_POST['action'] === 'delete_exercise') {
                // 개별 운동 삭제
                $wx_id = $_POST['wx_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("
                    SELECT ws.user_id 
                    FROM m_workout_exercise we
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE we.wx_id = ? AND ws.user_id = ?
                ");
                $stmt->execute([$wx_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '삭제 권한이 없습니다.']);
                    exit;
                }
                
                // 삭제 전 해당 운동의 세션 ID 확보 (돌아갈 페이지 결정용)
                $stmt = $pdo->prepare("SELECT session_id FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $wxRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $redirectSessionId = $wxRow['session_id'] ?? null;

                // 운동 삭제 (CASCADE로 관련 세트들도 자동 삭제됨)
                $stmt = $pdo->prepare("DELETE FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                
                $message = "운동이 성공적으로 삭제되었습니다.";
                
                // AJAX 요청이면 JSON, 아니면 현재 세션 페이지로
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $message]);
                    exit;
                } else if ($redirectSessionId) {
                    header('Location: my_workouts_ing.php?session_id=' . $redirectSessionId . '&message=' . urlencode($message));
                    exit;
                }
                
            } elseif ($_POST['action'] === 'update_set_data') {
                // 디버그: 받은 POST 데이터 확인
                error_log("=== update_set_data 요청 시작 ===");
                error_log("POST 데이터: " . print_r($_POST, true));
                error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
                error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
                
                // 세트 데이터 업데이트
                $wx_id = $_POST['wx_id'];
                $weight = (float)$_POST['weight'];
                $reps = (int)$_POST['reps'];
                $time = isset($_POST['time']) ? (int)$_POST['time'] : 0;
                
                error_log("파싱된 변수들:");
                error_log("- wx_id: " . var_export($wx_id, true) . " (타입: " . gettype($wx_id) . ")");
                error_log("- weight: " . var_export($weight, true) . " (타입: " . gettype($weight) . ")");
                error_log("- reps: " . var_export($reps, true) . " (타입: " . gettype($reps) . ")");
                error_log("- time: " . var_export($time, true) . " (타입: " . gettype($time) . ")");
                
                // wx_id 존재 여부 확인
                error_log("wx_id 존재 여부 확인 쿼리 실행");
                $stmt = $pdo->prepare("SELECT wx_id, session_id, weight, reps FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $exercise_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("운동 존재 확인 결과: " . ($exercise_exists ? '존재함' : '존재하지 않음'));
                if ($exercise_exists) {
                    error_log("기존 운동 정보: " . print_r($exercise_exists, true));
                } else {
                    error_log("wx_id $wx_id 에 해당하는 운동이 존재하지 않음");
                    // 사용자의 다른 운동 ID들 확인
                    $stmt = $pdo->prepare("
                        SELECT we.wx_id, we.session_id, we.weight, we.reps, ws.user_id 
                        FROM m_workout_exercise we
                        JOIN m_workout_session ws ON we.session_id = ws.session_id
                        WHERE ws.user_id = ?
                        ORDER BY we.wx_id DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$user['id']]);
                    $user_exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("사용자의 최근 운동들: " . print_r($user_exercises, true));
                }
                
                if (!$exercise_exists) {
                    error_log("운동을 찾을 수 없음 - wx_id: $wx_id");
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => "운동을 찾을 수 없습니다. wx_id: $wx_id"]);
                        exit;
                    }
                    $error = "운동을 찾을 수 없습니다. wx_id: $wx_id";
                    exit;
                }
                
                // 사용자 권한 확인
                error_log("사용자 권한 확인 쿼리 실행");
                $stmt = $pdo->prepare("
                    SELECT ws.user_id 
                    FROM m_workout_exercise we
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE we.wx_id = ? AND ws.user_id = ?
                ");
                $stmt->execute([$wx_id, $user['id']]);
                $auth_result = $stmt->fetch();
                
                if (!$auth_result) {
                    error_log("권한 없음 - wx_id: $wx_id, user_id: " . $user['id']);
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                        exit;
                    }
                    $error = '권한이 없습니다.';
                    exit;
                }
                
                // 데이터 타입 변환
                $weight = (float)$weight;
                $reps = (int)$reps;
                
                error_log("데이터 타입 변환 결과:");
                error_log("- weight: " . $weight . " (타입: " . gettype($weight) . ")");
                error_log("- reps: " . $reps . " (타입: " . gettype($reps) . ")");
                
                // ex_id가 있으면 운동도 변경 (하지만 update_set_data에서는 ex_id를 받지 않음)
                if (isset($ex_id) && $ex_id) {
                    error_log("ex_id 포함 업데이트 쿼리 실행 (예상되지 않음)");
                    $query = "UPDATE m_workout_exercise SET ex_id = ?, weight = ?, reps = ?, sets = ? WHERE wx_id = ?";
                    error_log("쿼리: $query");
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$ex_id, $weight, $reps, $sets, $wx_id]);
                } else {
                    // 세트 데이터만 업데이트 (time_seconds 컬럼 제거)
                    error_log("세트 데이터만 업데이트 쿼리 실행");
                    $query = "UPDATE m_workout_exercise SET weight = ?, reps = ? WHERE wx_id = ?";
                    error_log("쿼리: $query");
                    error_log("파라미터: weight=$weight, reps=$reps, wx_id=$wx_id");
                    
                    // 업데이트 전 현재 값과 비교
                    if ($exercise_exists) {
                        $current_weight = $exercise_exists['weight'];
                        $current_reps = $exercise_exists['reps'];
                        error_log("업데이트 전 값 비교:");
                        error_log("- weight: $current_weight -> $weight (변경됨: " . ($current_weight != $weight ? 'YES' : 'NO') . ")");
                        error_log("- reps: $current_reps -> $reps (변경됨: " . ($current_reps != $reps ? 'YES' : 'NO') . ")");
                    }
                    
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$weight, $reps, $wx_id]);
                }
                
                $affected_rows = $stmt->rowCount();
                error_log("업데이트 결과:");
                error_log("- execute 결과: " . ($result ? 'true' : 'false'));
                error_log("- 영향받은 행 수: $affected_rows");
                error_log("- PDO 에러 정보: " . print_r($stmt->errorInfo(), true));
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    error_log("AJAX 요청으로 JSON 응답 생성");
                    header('Content-Type: application/json');
                    
                    if ($result && $affected_rows > 0) {
                        error_log("세트 데이터 업데이트 성공");
                        $response = ['success' => true, 'message' => '세트 데이터가 성공적으로 업데이트되었습니다.'];
                        error_log("성공 응답: " . json_encode($response));
                        echo json_encode($response);
                    } else {
                        error_log("세트 데이터 업데이트 실패");
                        $response = ['success' => false, 'message' => "세트 데이터 업데이트 실패. 영향받은 행: {$affected_rows}"];
                        error_log("실패 응답: " . json_encode($response));
                        echo json_encode($response);
                    }
                    exit;
                }
                
                $message = "세트 데이터가 성공적으로 업데이트되었습니다.";
                error_log("=== update_set_data 요청 완료 ===");
                
            } elseif ($_POST['action'] === 'update_exercise_info' || $_POST['action'] === 'update_exercise') {
                // 디버그: 받은 POST 데이터 확인
                error_log("=== update_exercise_info 요청 시작 ===");
                error_log("POST 데이터: " . print_r($_POST, true));
                error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
                error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
                
                // 운동 정보 업데이트 (무게, 횟수, 세트)
                $wx_id = $_POST['wx_id'];
                $weight = (float)$_POST['weight'];
                $reps = (int)$_POST['reps'];
                $sets = (int)$_POST['sets'];
                $ex_id = isset($_POST['ex_id']) ? $_POST['ex_id'] : null;
                
                error_log("파싱된 변수들:");
                error_log("- wx_id: " . var_export($wx_id, true) . " (타입: " . gettype($wx_id) . ")");
                error_log("- weight: " . var_export($weight, true) . " (타입: " . gettype($weight) . ")");
                error_log("- reps: " . var_export($reps, true) . " (타입: " . gettype($reps) . ")");
                error_log("- sets: " . var_export($sets, true) . " (타입: " . gettype($sets) . ")");
                error_log("- ex_id: " . var_export($ex_id, true) . " (타입: " . gettype($ex_id) . ")");
                
                // wx_id 존재 여부 확인
                $stmt = $pdo->prepare("SELECT wx_id, session_id FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $exercise_exists = $stmt->fetch();
                
                if (!$exercise_exists) {
                    // 현재 사용자의 모든 운동 ID 확인
                    $stmt = $pdo->prepare("
                        SELECT we.wx_id, we.session_id, ws.user_id 
                        FROM m_workout_exercise we
                        JOIN m_workout_session ws ON we.session_id = ws.session_id
                        WHERE ws.user_id = ?
                        ORDER BY we.wx_id DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$user['id']]);
                    $user_exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $exercise_list = [];
                    foreach ($user_exercises as $ex) {
                        $exercise_list[] = "wx_id: {$ex['wx_id']} (session: {$ex['session_id']})";
                    }
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => "운동이 존재하지 않습니다. wx_id: $wx_id, user_id: {$user['id']}",
                        'available_exercises' => $exercise_list
                    ]);
                    exit;
                }
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("
                    SELECT ws.user_id 
                    FROM m_workout_exercise we
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE we.wx_id = ? AND ws.user_id = ?
                ");
                $stmt->execute([$wx_id, $user['id']]);
                $auth_result = $stmt->fetch();
                if (!$auth_result) {
                    echo json_encode(['success' => false, 'message' => "권한이 없습니다. wx_id: $wx_id, user_id: " . $user['id'] . ", session_id: " . $exercise_exists['session_id']]);
                    exit;
                }
                
                // 업데이트 전 현재 값 확인
                error_log("업데이트 전 현재 값 확인 쿼리 실행");
                $stmt = $pdo->prepare("SELECT ex_id, weight, reps, sets FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $before_values = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("업데이트 전 값들: " . print_r($before_values, true));
                
                // 데이터 타입 변환 확인
                $original_weight = $weight;
                $original_reps = $reps;
                $original_sets = $sets;
                
                $weight = (float)$weight;
                $reps = (int)$reps;
                $sets = (int)$sets;
                
                error_log("데이터 타입 변환 결과:");
                error_log("- weight: '$original_weight' -> $weight (타입: " . gettype($weight) . ")");
                error_log("- reps: '$original_reps' -> $reps (타입: " . gettype($reps) . ")");
                error_log("- sets: '$original_sets' -> $sets (타입: " . gettype($sets) . ")");
                error_log("- ex_id: " . var_export($ex_id, true) . " (타입: " . gettype($ex_id) . ")");
                
                // 운동 정보 업데이트 (ex_id가 전달되면 같이 변경)
                if ($ex_id) {
                    error_log("ex_id 포함 업데이트 쿼리 실행");
                    $query = "UPDATE m_workout_exercise SET ex_id = ?, weight = ?, reps = ?, sets = ? WHERE wx_id = ?";
                    error_log("쿼리: $query");
                    error_log("파라미터: ex_id=$ex_id, weight=$weight, reps=$reps, sets=$sets, wx_id=$wx_id");
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$ex_id, $weight, $reps, $sets, $wx_id]);
                } else {
                    error_log("ex_id 없이 업데이트 쿼리 실행");
                    $query = "UPDATE m_workout_exercise SET weight = ?, reps = ?, sets = ? WHERE wx_id = ?";
                    error_log("쿼리: $query");
                    error_log("파라미터: weight=$weight, reps=$reps, sets=$sets, wx_id=$wx_id");
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$weight, $reps, $sets, $wx_id]);
                }
                
                // 디버깅: 업데이트 결과 확인
                $affected_rows = $stmt->rowCount();
                error_log("업데이트 결과:");
                error_log("- execute 결과: " . ($result ? 'true' : 'false'));
                error_log("- 영향받은 행 수: $affected_rows");
                error_log("- PDO 에러 정보: " . print_r($stmt->errorInfo(), true));
                
                // 업데이트 후 값 확인
                error_log("업데이트 후 값 확인 쿼리 실행");
                $stmt = $pdo->prepare("SELECT ex_id, weight, reps, sets FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $after_values = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("업데이트 후 값들: " . print_r($after_values, true));
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    error_log("AJAX 요청으로 JSON 응답 생성");
                    header('Content-Type: application/json');
                    if ($result && $affected_rows > 0) {
                        error_log("업데이트 성공 - JSON 응답 생성");
                        $response = [
                            'success' => true, 
                            'message' => "운동 정보가 성공적으로 업데이트되었습니다. (영향받은 행: {$affected_rows})",
                            'debug' => [
                                'before' => $before_values,
                                'after' => $after_values,
                                'requested' => [
                                    'ex_id' => $ex_id,
                                    'weight' => $weight,
                                    'reps' => $reps,
                                    'sets' => $sets,
                                ],
                                'affected_rows' => $affected_rows
                            ]
                        ];
                        error_log("성공 응답: " . json_encode($response));
                        echo json_encode($response);
                    } else {
                        error_log("업데이트 실패 - JSON 응답 생성");
                        // 영향받은 행이 0인 경우 상세 디버그 정보 포함
                        $no_change_reason = '값이 동일하거나 변경 사항 없음';
                        if ($before_values) {
                            $no_change_reason = (
                                ((int)($before_values['ex_id'] ?? 0) === (int)($ex_id ?? $before_values['ex_id'])) &&
                                ((float)$before_values['weight'] === (float)$weight) &&
                                ((int)$before_values['reps'] === (int)$reps) &&
                                ((int)$before_values['sets'] === (int)$sets)
                            ) ? '변경된 값이 없음(동일 값)' : 'DB가 업데이트를 보고하지 않음';
                        }

                        $failure_response = [
                            'success' => false, 
                            'message' => "업데이트 실패. 영향받은 행: {$affected_rows} - {$no_change_reason}",
                            'debug' => [
                                'before' => $before_values,
                                'after' => $after_values,
                                'requested' => [
                                    'ex_id' => $ex_id,
                                    'weight' => $weight,
                                    'reps' => $reps,
                                    'sets' => $sets,
                                ],
                                'affected_rows' => $affected_rows,
                                'no_change_reason' => $no_change_reason
                            ]
                        ];
                        error_log("실패 응답: " . json_encode($failure_response));
                        echo json_encode($failure_response);
                    }
                    exit;
                }
                
                $message = "운동 정보가 성공적으로 업데이트되었습니다.";
                error_log("=== update_exercise_info 요청 완료 ===");
                
            } elseif ($_POST['action'] === 'get_completed_exercise') {
                // 완료된 운동 데이터 가져오기
                $wx_id = $_POST['wx_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("
                    SELECT ws.user_id 
                    FROM m_workout_exercise we
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE we.wx_id = ? AND ws.user_id = ?
                ");
                $stmt->execute([$wx_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 운동 정보 가져오기
                $stmt = $pdo->prepare("
                    SELECT we.*, 
                           COALESCE(e.name_kr, te.exercise_name) as name_kr,
                           e.name_en, 
                           e.equipment,
                           we.is_temp,
                           te.exercise_name as temp_exercise_name
                    FROM m_workout_exercise we
                    LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
                    LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
                    WHERE we.wx_id = ?
                ");
                $stmt->execute([$wx_id]);
                $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 완료된 세트들 가져오기
                $stmt = $pdo->prepare("
                    SELECT set_no, weight, reps, rest_time as time_seconds, completed_at
                    FROM m_workout_set 
                    WHERE wx_id = ?
                    ORDER BY set_no ASC
                ");
                $stmt->execute([$wx_id]);
                $sets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'exercise' => $exercise,
                        'sets' => $sets
                    ]);
                    exit;
                }
                
            } elseif ($_POST['action'] === 'search_exercises') {
                // 운동 검색
                $searchTerm = $_POST['search_term'];
                
                $stmt = $pdo->prepare("
                    SELECT ex_id, name_kr, name_en, equipment_kr
                    FROM m_exercise 
                    WHERE name_kr LIKE ? OR name_en LIKE ? OR ex_id IN (
                        SELECT ex_id FROM m_exercise_alias WHERE alias LIKE ?
                    )
                    ORDER BY name_kr
                    LIMIT 20
                ");
                $searchPattern = "%{$searchTerm}%";
                $stmt->execute([$searchPattern, $searchPattern, $searchPattern]);
                $exercises = $stmt->fetchAll();
                
                $response = [
                    'success' => true,
                    'exercises' => $exercises
                ];
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                
            } elseif ($_POST['action'] === 'add_exercises') {
                // 여러 운동 추가
                $session_id = $_POST['session_id'];
                $exercisesData = json_decode($_POST['exercises_data'], true);
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 현재 세션의 최대 order_no 찾기
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_no), 0) as max_order FROM m_workout_exercise WHERE session_id = ?");
                $stmt->execute([$session_id]);
                $currentMaxOrder = $stmt->fetch()['max_order'];
                
                $addedCount = 0;
                foreach ($exercisesData as $exerciseData) {
                    $currentMaxOrder++;
                    
                    // 운동 검색 (today.php의 searchExercise 함수와 유사한 로직)
                    $exerciseResults = searchExerciseForAdd($pdo, $exerciseData['exercise_name']);
                    
                    if (!empty($exerciseResults)) {
                        // 검색된 운동이 있으면 첫 번째 결과 사용
                        $bestMatch = $exerciseResults[0];
                        $stmt = $pdo->prepare("
                            INSERT INTO m_workout_exercise (session_id, ex_id, order_no, weight, reps, sets, original_exercise_name, is_temp)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                        ");
                        $stmt->execute([
                            $session_id,
                            $bestMatch['ex_id'],
                            $currentMaxOrder,
                            (float)$exerciseData['weight'],
                            (int)$exerciseData['reps'],
                            (int)$exerciseData['sets'],
                            $exerciseData['exercise_name']
                        ]);
                    } else {
                        // 검색된 운동이 없으면 임시 운동으로 추가
                        $stmt = $pdo->prepare("
                            INSERT INTO m_temp_exercise (user_id, exercise_name, status)
                            VALUES (?, ?, 'pending')
                        ");
                        $stmt->execute([$user['id'], $exerciseData['exercise_name']]);
                        $tempExId = $pdo->lastInsertId();
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO m_workout_exercise (session_id, ex_id, order_no, weight, reps, sets, original_exercise_name, temp_ex_id, is_temp)
                            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([
                            $session_id,
                            $currentMaxOrder,
                            $exerciseData['weight'],
                            $exerciseData['reps'],
                            $exerciseData['sets'],
                            $exerciseData['exercise_name'],
                            $tempExId
                        ]);
                    }
                    $addedCount++;
                }
                
                $response = [
                    'success' => true,
                    'message' => "{$addedCount}개의 운동이 성공적으로 추가되었습니다."
                ];
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                
            }
            elseif ($_POST['action'] === 'reorder_exercise') {
                $wx_id = (int)$_POST['wx_id'];
                $direction = $_POST['direction'] === 'up' ? 'up' : 'down';

                // 대상 운동 정보
                $stmt = $pdo->prepare("SELECT session_id, order_no FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$current) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => '운동을 찾을 수 없습니다.']);
                    exit;
                }

                $session_id = (int)$current['session_id'];
                $order_no = (int)$current['order_no'];

                // 이웃 찾기
                if ($direction === 'up') {
                    $stmt = $pdo->prepare("SELECT wx_id, order_no FROM m_workout_exercise WHERE session_id = ? AND order_no < ? ORDER BY order_no DESC LIMIT 1");
                    $stmt->execute([$session_id, $order_no]);
                } else {
                    $stmt = $pdo->prepare("SELECT wx_id, order_no FROM m_workout_exercise WHERE session_id = ? AND order_no > ? ORDER BY order_no ASC LIMIT 1");
                    $stmt->execute([$session_id, $order_no]);
                }
                $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);

                header('Content-Type: application/json');
                if (!$neighbor) {
                    // 방향에 따라 친절한 메시지
                    $friendly = ($direction === 'up') ? '첫번째 카드 입니다.' : '마지막 카드 입니다.';
                    echo json_encode(['success' => false, 'message' => $friendly]);
                    exit;
                }

                // 스왑
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE m_workout_exercise SET order_no = ? WHERE wx_id = ?");
                $stmt->execute([(int)$neighbor['order_no'], $wx_id]);
                $stmt->execute([$order_no, (int)$neighbor['wx_id']]);
                $pdo->commit();

                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        // 페이지 새로고침으로 목록 업데이트
        header('Location: my_workouts.php?date=' . $date . '&message=' . urlencode($message));
}

// 운동 검색 함수 (today.php의 searchExercise 함수와 유사)
function searchExerciseForAdd($pdo, $exerciseName) {
    $searchWords = preg_split('/\s+/', trim($exerciseName));
    $conditions = [];
    $params = [];

    // 1. 공백 제거한 전체 검색어로 정확한 매칭 (최우선)
    $noSpaceTerm = str_replace(' ', '', $exerciseName);
    $conditions[] = "(REPLACE(e.name_kr, ' ', '') LIKE ? OR REPLACE(e.name_en, ' ', '') LIKE ? OR REPLACE(ea.alias, ' ', '') LIKE ?)";
    $params[] = '%' . $noSpaceTerm . '%';
    $params[] = '%' . $noSpaceTerm . '%';
    $params[] = '%' . $noSpaceTerm . '%';

    // 2. 전체 검색어로 정확한 매칭
    $conditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
    $searchTerm = '%' . $exerciseName . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    // 3. 단어별 검색 (모든 단어가 포함되어야 함)
    if (count($searchWords) > 1) {
        $wordConditions = [];
        foreach ($searchWords as $word) {
            if (strlen($word) > 1) {
                $wordConditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
                $params[] = '%' . $word . '%';
                $params[] = '%' . $word . '%';
                $params[] = '%' . $word . '%';
            }
        }
        if (!empty($wordConditions)) {
            $conditions[] = "(" . implode(' AND ', $wordConditions) . ")";
        }
    }

    $whereClause = implode(' OR ', $conditions);

    $sql = "
        SELECT DISTINCT e.ex_id, e.name_kr, e.name_en, e.equipment_kr
        FROM m_exercise e
        LEFT JOIN m_exercise_alias ea ON e.ex_id = ea.ex_id
        WHERE {$whereClause}
        ORDER BY 
            CASE 
                WHEN e.name_kr = ? THEN 1
                WHEN e.name_kr LIKE ? THEN 2
                WHEN e.name_en LIKE ? THEN 3
                ELSE 4
            END,
            e.name_kr
        LIMIT 5
    ";

    // 정확한 매칭을 위한 추가 파라미터
    $params[] = $exerciseName;
    $params[] = $exerciseName . '%';
    $params[] = $exerciseName . '%';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $results;
}

// 세션 단건 보기 또는 날짜별 보기 분기
$pdo = getDB();

// 페이지 로드 시 시작시간 자동 저장 (단일 세션 모드에서만)
if ($sessionIdParam) {
    // 시작시간이 없으면 현재 시간으로 저장
    $stmt = $pdo->prepare("UPDATE m_workout_session SET start_time = NOW() WHERE session_id = ? AND user_id = ? AND start_time IS NULL");
    $stmt->execute([$sessionIdParam, $user['id']]);
}

if ($sessionIdParam) {
    // 단일 세션 로드
    $stmt = $pdo->prepare('SELECT * FROM m_workout_session WHERE session_id = ? AND user_id = ?');
    $stmt->execute([$sessionIdParam, $user['id']]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sessionRow) {
        // exercise_count, total_volume 보강
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM m_workout_exercise WHERE session_id = ?');
        $stmt->execute([$sessionRow['session_id']]);
        $exerciseCount = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(weight * reps * sets),0) FROM m_workout_exercise WHERE session_id = ?');
        $stmt->execute([$sessionRow['session_id']]);
        $totalVolume = (float)$stmt->fetchColumn();

        $sessionRow['exercise_count'] = $exerciseCount;
        $sessionRow['total_volume'] = $totalVolume;
        $workoutSessions = [$sessionRow];
        // 단건 모드에서는 $date를 세션 날짜로 동기화
        $date = $sessionRow['workout_date'];
    } else {
        $workoutSessions = [];
    }
} else {
    // 해당 날짜의 모든 운동 세션 가져오기 (회차별로)
    $stmt = $pdo->prepare('
        SELECT ws.*, 
               COUNT(we.wx_id) as exercise_count,
               SUM(we.weight * we.reps * we.sets) as total_volume
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        WHERE ws.user_id = ? AND ws.workout_date = ?
        GROUP BY ws.session_id
        ORDER BY ws.session_id DESC
    ');
    $stmt->execute([$user['id'], $date]);
    $workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 전체 운동 데이터 수집 (모든 회차 합계)
$allExercises = [];
$totalDayVolume = 0;
$allMuscleAnalysis = [];

foreach ($workoutSessions as $session) {
    $stmt = $pdo->prepare('
        SELECT we.*, 
               COALESCE(e.name_kr, te.exercise_name) as name_kr,
               e.name_en, 
               e.equipment,
               we.is_temp,
               te.exercise_name as temp_exercise_name,
               we.original_exercise_name
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$session['session_id']]);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 각 운동의 완료 상태 확인
    foreach ($exercises as &$exercise) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_sets, MAX(set_no) as max_set_no
            FROM m_workout_set 
            WHERE wx_id = ?
        ");
        $stmt->execute([$exercise['wx_id']]);
        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exercise['completed_sets'] = $completion['completed_sets'] ?? 0;
        $exercise['is_completed'] = ($exercise['completed_sets'] >= $exercise['sets']);
    }
    
    foreach ($exercises as &$exercise) {
        $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
        $totalDayVolume += $exerciseVolume;
        
        // 해당 운동의 근육 타겟 정보 가져오기
        $stmt = $pdo->prepare('
            SELECT emt.*, m.name_kr as muscle_name, m.name_en as muscle_name_en, bp.part_name_kr
            FROM m_exercise_muscle_target emt
            JOIN m_muscle m ON emt.muscle_code = m.muscle_code
            JOIN m_body_part bp ON m.part_code = bp.part_code
            WHERE emt.ex_id = ?
            ORDER BY emt.priority ASC, emt.weight DESC
        ');
        $stmt->execute([$exercise['ex_id']]);
        $muscleTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 각 근육별 가중치 계산 (전체 기준)
        foreach ($muscleTargets as $target) {
            $muscleCode = $target['muscle_code'];
            $muscleName = $target['muscle_name'];
            $partName = $target['part_name_kr'];
            $weight = $target['weight'];
            $priority = $target['priority'];
            
            // 가중치 적용된 볼륨 계산
            $weightedVolume = $exerciseVolume * $weight;
            
            if (!isset($allMuscleAnalysis[$muscleCode])) {
                $allMuscleAnalysis[$muscleCode] = [
                    'muscle_name' => $muscleName,
                    'part_name' => $partName,
                    'total_volume' => 0,
                    'weighted_volume' => 0,
                    'exercises' => []
                ];
            }
            
            $allMuscleAnalysis[$muscleCode]['total_volume'] += $exerciseVolume;
            $allMuscleAnalysis[$muscleCode]['weighted_volume'] += $weightedVolume;
            $allMuscleAnalysis[$muscleCode]['exercises'][] = [
                'exercise_name' => $exercise['name_kr'],
                'volume' => $exerciseVolume,
                'weight' => $weight,
                'priority' => $priority,
                'weighted_volume' => $weightedVolume
            ];
        }
    }
}

// 전체 기준 퍼센트 계산
$totalWeightedVolume = 0;
foreach ($allMuscleAnalysis as $muscleCode => &$data) {
    $totalWeightedVolume += $data['weighted_volume'];
}

// 정규화된 퍼센트 계산 (전체 가중치 볼륨을 100%로)
foreach ($allMuscleAnalysis as $muscleCode => &$data) {
    $data['percentage'] = $totalWeightedVolume > 0 ? round(($data['weighted_volume'] / $totalWeightedVolume) * 100, 1) : 0;
}

// 퍼센트 기준으로 정렬
uasort($allMuscleAnalysis, function($a, $b) {
    return $b['percentage'] <=> $a['percentage'];
});

// 각 세션별로 운동 상세 정보 가져오기
$sessionsWithExercises = [];
foreach ($workoutSessions as $index => $session) {
    $stmt = $pdo->prepare('
        SELECT we.*, 
               COALESCE(e.name_kr, te.exercise_name) as name_kr,
               e.name_en, 
               e.equipment,
               we.is_temp,
               te.exercise_name as temp_exercise_name,
               we.original_exercise_name
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$session['session_id']]);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 각 운동의 완료 상태 확인
    foreach ($exercises as &$exercise) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_sets, MAX(set_no) as max_set_no
            FROM m_workout_set 
            WHERE wx_id = ?
        ");
        $stmt->execute([$exercise['wx_id']]);
        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exercise['completed_sets'] = $completion['completed_sets'] ?? 0;
        $exercise['is_completed'] = ($exercise['completed_sets'] >= $exercise['sets']);
    }
    unset($exercise); // 참조 해제
    
    // 해당 회차의 볼륨 계산
    $sessionVolume = 0;
    foreach ($exercises as $exercise) {
        $sessionVolume += $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
    }
    
    // 사용자별 프리/엔드루틴 설정 가져오기
    $stmt = $pdo->prepare("
        SELECT pre_routine, post_routine 
        FROM m_routine_settings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $routineSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $preRoutine = $routineSettings['pre_routine'] ?? '';
    $postRoutine = $routineSettings['post_routine'] ?? '';
    
    // 해당 세션의 루틴 기록 가져오기
    $stmt = $pdo->prepare("
        SELECT routine_type, routine_name, routine_content, is_completed, start_time, end_time, duration
        FROM m_routine_records 
        WHERE session_id = ? 
        ORDER BY routine_type, created_at DESC
    ");
    $stmt->execute([$session['session_id']]);
    $routineRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 루틴 기록을 타입별로 정리
    $preRoutineRecord = null;
    $postRoutineRecord = null;
    
    foreach ($routineRecords as $record) {
        if ($record['routine_type'] === 'pre') {
            $preRoutineRecord = $record;
        } else if ($record['routine_type'] === 'post') {
            $postRoutineRecord = $record;
        }
    }
    
    $sessionsWithExercises[] = [
        'session' => $session,
        'exercises' => $exercises,
        'round' => $index + 1, // 1회차, 2회차...
        'session_volume' => $sessionVolume,
        'session_percentage' => $totalDayVolume > 0 ? round(($sessionVolume / $totalDayVolume) * 100, 1) : 0,
        'pre_routine' => $preRoutine,
        'post_routine' => $postRoutine,
        'pre_routine_record' => $preRoutineRecord,
        'post_routine_record' => $postRoutineRecord
    ];
}

// 날짜 포맷팅
$formattedDate = date('Y년 m월 d일', strtotime($date));
$dayOfWeek = ['일', '월', '화', '수', '목', '금', '토'][date('w', strtotime($date))];

// 운동 수행 분석 데이터 수집
$workoutPerformanceAnalysis = [
    'total_exercises' => 0,
    'completed_exercises' => 0,
    'total_sets' => 0,
    'completed_sets' => 0,
    'total_volume' => 0,
    'completed_volume' => 0,
    'total_time' => 0,
    'average_set_time' => 0,
    'completion_rate' => 0
];

$performanceByMuscle = [];
$performanceByBodyPart = [];
$totalActualVolume = 0; // 가중치 적용 전 실제 볼륨
$exerciseVolumeByPart = []; // 부위별 운동 볼륨 (중복 제거용)

foreach ($sessionsWithExercises as $sessionData) {
    foreach ($sessionData['exercises'] as $exercise) {
        $workoutPerformanceAnalysis['total_exercises']++;
        $workoutPerformanceAnalysis['total_sets'] += $exercise['sets'];
        $workoutPerformanceAnalysis['total_volume'] += $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
        
        if ($exercise['is_completed']) {
            $workoutPerformanceAnalysis['completed_exercises']++;
            $workoutPerformanceAnalysis['completed_sets'] += $exercise['completed_sets'];
            $workoutPerformanceAnalysis['completed_volume'] += $exercise['weight'] * $exercise['reps'] * $exercise['completed_sets'];
            
            // 완료된 운동의 시간 데이터 가져오기
            $stmt = $pdo->prepare("
                SELECT SUM(rest_time) as total_time, AVG(rest_time) as avg_time
                FROM m_workout_set 
                WHERE wx_id = ?
            ");
            $stmt->execute([$exercise['wx_id']]);
            $timeData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($timeData && $timeData['total_time']) {
                $workoutPerformanceAnalysis['total_time'] += $timeData['total_time'];
            }
            
            // 완료된 운동의 근육 분석
            $stmt = $pdo->prepare('
                SELECT emt.*, m.name_kr as muscle_name, m.part_code, bp.part_name_kr
                FROM m_exercise_muscle_target emt
                JOIN m_muscle m ON emt.muscle_code = m.muscle_code
                JOIN m_body_part bp ON m.part_code = bp.part_code
                WHERE emt.ex_id = ?
                ORDER BY emt.priority ASC, emt.weight DESC
            ');
            $stmt->execute([$exercise['ex_id']]);
            $muscleTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['completed_sets'];
            $totalActualVolume += $exerciseVolume; // 실제 볼륨 누적
            
            // 부위별로 중복 제거하여 볼륨 계산
            $partVolumes = [];
            foreach ($muscleTargets as $target) {
                $partCode = $target['part_code'];
                if (!isset($partVolumes[$partCode])) {
                    $partVolumes[$partCode] = $exerciseVolume; // 각 부위별로 운동 볼륨 한 번만 추가
                }
            }
            
            // 부위별 볼륨 누적
            foreach ($partVolumes as $partCode => $volume) {
                if (!isset($exerciseVolumeByPart[$partCode])) {
                    $exerciseVolumeByPart[$partCode] = 0;
                }
                $exerciseVolumeByPart[$partCode] += $volume;
            }
            
            // 근육별 분석 (가중치 적용)
            foreach ($muscleTargets as $target) {
                $muscleCode = $target['muscle_code'];
                $partCode = $target['part_code'];
                $partName = $target['part_name_kr'];
                $weightedVolume = $exerciseVolume * $target['weight'];
                
                if (!isset($performanceByMuscle[$muscleCode])) {
                    $performanceByMuscle[$muscleCode] = [
                        'muscle_name' => $target['muscle_name'],
                        'part_name' => $partName,
                        'part_code' => $partCode,
                        'total_volume' => 0,
                        'actual_volume' => 0,
                        'exercise_count' => 0
                    ];
                }
                
                $performanceByMuscle[$muscleCode]['total_volume'] += $weightedVolume;
                $performanceByMuscle[$muscleCode]['actual_volume'] += $exerciseVolume;
                $performanceByMuscle[$muscleCode]['exercise_count']++;
            }
        }
    }
}

// 완료율 계산
if ($workoutPerformanceAnalysis['total_exercises'] > 0) {
    $workoutPerformanceAnalysis['completion_rate'] = round(
        ($workoutPerformanceAnalysis['completed_exercises'] / $workoutPerformanceAnalysis['total_exercises']) * 100, 1
    );
}

// 평균 세트 시간 계산
if ($workoutPerformanceAnalysis['completed_sets'] > 0) {
    $workoutPerformanceAnalysis['average_set_time'] = round(
        $workoutPerformanceAnalysis['total_time'] / $workoutPerformanceAnalysis['completed_sets'], 1
    );
}

// 전체 계획된 운동의 가중치 볼륨 총합 계산 (수행률 기준)
$totalPlannedWeightedVolume = 0;
foreach ($allMuscleAnalysis as $muscleCode => $muscleData) {
    $totalPlannedWeightedVolume += $muscleData['weighted_volume'];
}

// 근육별 퍼센트 계산 (전체 계획 대비 수행률)
foreach ($performanceByMuscle as $muscleCode => &$data) {
    $data['percentage'] = $totalPlannedWeightedVolume > 0 ? 
        round(($data['total_volume'] / $totalPlannedWeightedVolume) * 100, 1) : 0;
}

// 근육별 퍼센트 기준으로 정렬
uasort($performanceByMuscle, function($a, $b) {
    return $b['percentage'] <=> $a['percentage'];
});

// 부위별 데이터 생성 (가중치 적용된 볼륨 사용)
$performanceByBodyPart = [];
foreach ($performanceByMuscle as $muscleCode => $muscleData) {
    $partCode = $muscleData['part_code'];
    $partName = $muscleData['part_name'];
    
    if (!isset($performanceByBodyPart[$partCode])) {
        $performanceByBodyPart[$partCode] = [
            'part_name' => $partName,
            'part_code' => $partCode,
            'weighted_volume' => 0,
            'actual_volume' => 0,
            'exercise_count' => 0
        ];
    }
    
    $performanceByBodyPart[$partCode]['weighted_volume'] += $muscleData['total_volume'];
    $performanceByBodyPart[$partCode]['actual_volume'] += $muscleData['actual_volume'];
    $performanceByBodyPart[$partCode]['exercise_count'] += $muscleData['exercise_count'];
}

// 부위별 퍼센트 계산 (전체 계획 대비 수행률)
foreach ($performanceByBodyPart as $partCode => &$data) {
    $data['percentage'] = $totalPlannedWeightedVolume > 0 ? 
        round(($data['weighted_volume'] / $totalPlannedWeightedVolume) * 100, 1) : 0;
}

// 부위별 퍼센트 기준으로 정렬
uasort($performanceByBodyPart, function($a, $b) {
    return $b['percentage'] <=> $a['percentage'];
});

// 헤더 포함
include 'header.php';
?>

<!-- 앱바 스타일 제목 -->
<?php if (isset($sessionData['session'])): ?>
<div class="app-bar">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                        <h4 class="mb-0 fw-bold text-dark">
                            <?php 
                            $workoutDate = $sessionData['session']['workout_date'];
                            $year = date('Y', strtotime($workoutDate));
                            $month = date('n', strtotime($workoutDate));
                            $day = date('j', strtotime($workoutDate));
                            $round = $sessionData['round'];
                            echo $year . '년 ' . $month . '월 ' . $day . '일 ' . $round . '회차';
                            ?>
                        </h4>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="goBack()">
                        <i class="fas fa-arrow-left me-1"></i>뒤로가기
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 메시지 표시 -->
<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" id="messageAlert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
        // 메시지 표시 후 URL에서 message 파라미터 제거
        setTimeout(function() {
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('message');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }, 100);
        
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>



<?php if (!empty($sessionsWithExercises)): ?>
    <!-- 각 세션별 운동 목록 -->
    <?php foreach ($sessionsWithExercises as $sessionData): ?>
    
    <!-- 프리루틴 -->
    <?php if (!empty($sessionData['pre_routine'])): ?>
    <div class="mb-3">
        <?php if ($sessionData['pre_routine_record']): ?>
            <!-- 루틴 기록이 있는 경우 -->
            <div class="card border-left-success">
                <div class="card-body">
                    <!-- 첫 번째 줄: 루틴 제목, 완료 상태, 시간 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle text-success"></i> 
                            <strong>PRE-ROUTINE</strong>
                            <?php if ($sessionData['pre_routine_record']['is_completed']): ?>
                                <span class="badge bg-success ms-2">완료</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark ms-2">취소</span>
                            <?php endif; ?>
                        </h5>
                        <div class="text-muted">
                            <i class="fas fa-clock"></i> 
                            <?= gmdate('i:s', $sessionData['pre_routine_record']['duration']) ?>
                        </div>
                    </div>
                    
                    <!-- 두 번째 줄: 내용과 다시 시작 버튼 -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1 me-3">
                            <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($sessionData['pre_routine'])) ?></p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="showRoutineRestartModal('pre', <?= $sessionData['session']['session_id'] ?>)">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- 세 번째 줄: 시작시간과 종료시간 -->
                    <div class="mt-2 pt-2 border-top" style="display: block !important; visibility: visible !important;">
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">시작시간</small>
                                <strong class="text-warning">
                                    <i class="fas fa-play-circle me-1"></i>
                                    <?= date('H:i', strtotime($sessionData['pre_routine_record']['started_at'])) ?>
                                </strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">종료시간</small>
                                <strong class="text-danger">
                                    <i class="fas fa-stop-circle me-1"></i>
                                    <?= date('H:i', strtotime($sessionData['pre_routine_record']['completed_at'])) ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- 루틴 기록이 없는 경우 (시작 버튼) -->
            <div class="card border-left-warning">
                <div class="card-body text-center">
                    <h5 class="mb-3">
                        <i class="fas fa-play-circle text-warning"></i> 
                        <strong>PRE-ROUTINE</strong>
                    </h5>
                    <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($sessionData['pre_routine'])) ?></p>
                    <button type="button" class="btn btn-warning btn-lg" onclick="startRoutine('pre', <?= $sessionData['session']['session_id'] ?>)">
                        <i class="fas fa-play"></i> 프리루틴 시작
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 1. 본운동 타이틀 -->
    <div class="mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-dumbbell"></i> 본운동
                </h4>
                <button type="button" class="btn btn-outline-light btn-sm" onclick="openAddExerciseModal()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 운동 카드들 -->
    <?php foreach ($sessionData['exercises'] as $index => $exercise): ?>
    <div class="mb-3">
        <div class="card" style="cursor: pointer;" onclick="openExerciseModal(<?= $exercise['wx_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>', <?= $exercise['weight'] == floor($exercise['weight']) ? number_format($exercise['weight'], 0) : number_format($exercise['weight'], 1) ?>, <?= $exercise['reps'] ?>, <?= $exercise['sets'] ?? 0 ?>)">
            <div class="card-body p-4">
                <div class="mb-3">
                    <h5 class="mb-2">
                        <?php 
                        $colors = ['bg-primary', 'bg-success', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary', 'bg-dark'];
                        $colorClass = $colors[($index) % count($colors)];
                        ?>
                        <span class="badge <?= $colorClass ?> me-2" style="font-size: 1.05em; padding: 0.35em 0.5em;"><?= $index + 1 ?></span>
                        <strong style="font-size: 1.1em;"><?= htmlspecialchars($exercise['name_kr']) ?></strong>
                        <?php if ($exercise['is_temp']): ?>
                            <span class="badge bg-warning text-dark ms-2">임시</span>
                        <?php endif; ?>
                    </h5>
                    <div class="text-muted text-end">
                        <div class="fs-5" style="font-weight: 700;"><?= $exercise['weight'] == floor($exercise['weight']) ? number_format($exercise['weight'], 0) : number_format($exercise['weight'], 1) ?>kg × <?= $exercise['reps'] ?>회 × <?= $exercise['sets'] ?? 0 ?>세트</div>
                        <?php if ($exercise['note']): ?>
                            <div class="mt-2"><em class="fs-5"><?= htmlspecialchars($exercise['note']) ?></em></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex flex-column" onclick="event.stopPropagation();">
                        <div class="btn-group btn-group-sm mb-1">
                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                    onclick="openEditExerciseModal(<?= $exercise['wx_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>', <?= $exercise['weight'] ?>, <?= $exercise['reps'] ?>, <?= $exercise['sets'] ?? 0 ?>)"
                                    title="운동 수정">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                    onclick="deleteExercise(<?= $exercise['wx_id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="위로" onclick="reorderExercise(<?= $exercise['wx_id'] ?>, 'up');">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="아래로" onclick="reorderExercise(<?= $exercise['wx_id'] ?>, 'down');">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="btn-group" onclick="event.stopPropagation();">
                        <!-- 완료 상태 버튼 -->
                        <button type="button" class="btn <?= $exercise['is_completed'] ? 'btn-success' : 'btn-outline-secondary' ?>" 
                                title="<?= $exercise['is_completed'] ? '완료됨' : '미완료' ?>"
                                onclick="loadCompletedExercise(<?= $exercise['wx_id'] ?>)">
                            <i class="fas fa-check"></i>
                            <span class="ms-1 fw-bold" style="font-size: 1.1em;"><?= $exercise['completed_sets'] ?>/<?= $exercise['sets'] ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- 포스트루틴 -->
    <?php if (!empty($sessionData['post_routine'])): ?>
    <div class="mb-3">
        <?php if ($sessionData['post_routine_record']): ?>
            <!-- 루틴 기록이 있는 경우 -->
            <div class="card border-left-success">
                <div class="card-body">
                    <!-- 첫 번째 줄: 루틴 제목, 완료 상태, 시간 -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle text-success"></i> 
                            <strong>POST-ROUTINE</strong>
                            <?php if ($sessionData['post_routine_record']['is_completed']): ?>
                                <span class="badge bg-success ms-2">완료</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark ms-2">취소</span>
                            <?php endif; ?>
                        </h5>
                        <div class="text-muted">
                            <i class="fas fa-clock"></i> 
                            <?= gmdate('i:s', $sessionData['post_routine_record']['duration']) ?>
                        </div>
                    </div>
                    
                    <!-- 두 번째 줄: 내용과 다시 시작 버튼 -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1 me-3">
                            <p class="text-muted mb-0"><?= nl2br(htmlspecialchars($sessionData['post_routine'])) ?></p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="showRoutineRestartModal('post', <?= $sessionData['session']['session_id'] ?>)">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- 세 번째 줄: 시작시간과 종료시간 -->
                    <div class="mt-2 pt-2 border-top" style="display: block !important; visibility: visible !important;">
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted d-block">시작시간</small>
                                <strong class="text-success">
                                    <i class="fas fa-play-circle me-1"></i>
                                    <?= date('H:i', strtotime($sessionData['post_routine_record']['started_at'])) ?>
                                </strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">종료시간</small>
                                <strong class="text-danger">
                                    <i class="fas fa-stop-circle me-1"></i>
                                    <?= date('H:i', strtotime($sessionData['post_routine_record']['completed_at'])) ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- 루틴 기록이 없는 경우 (시작 버튼) -->
            <div class="card border-left-success">
                <div class="card-body text-center">
                    <h5 class="mb-3">
                        <i class="fas fa-stop-circle text-success"></i> 
                        <strong>POST-ROUTINE</strong>
                    </h5>
                    <p class="text-muted mb-3"><?= nl2br(htmlspecialchars($sessionData['post_routine'])) ?></p>
                    <button type="button" class="btn btn-success btn-lg" onclick="startRoutine('post', <?= $sessionData['session']['session_id'] ?>)">
                        <i class="fas fa-play"></i> 포스트루틴 시작
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
            
            <!-- 5. 운동 시간 정보 -->
            <div class="card border-left-info mb-3">
                <div class="card-body">
                    <!-- 첫 번째 줄: 제목 -->
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-clock text-info"></i> 
                            <strong>운동 시간</strong>
                        </h5>
                    </div>
                    
                    <!-- 두 번째 줄: 시간 설정 버튼들 -->
                    <div class="row">
                        <div class="col-6">
                            <button type="button" 
                                    class="btn btn-outline-primary btn-lg w-100" 
                                    id="start_time_btn_<?= $sessionData['session']['session_id'] ?>"
                                    onclick="openTimePicker('start', <?= $sessionData['session']['session_id'] ?>)">
                                <i class="fas fa-clock me-2"></i>
                                <span id="start_time_display_<?= $sessionData['session']['session_id'] ?>">
                                    <?= $sessionData['session']['start_time'] ? date('H:i', strtotime($sessionData['session']['start_time'])) : '시작시간' ?>
                                </span>
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" 
                                    class="btn btn-outline-danger btn-lg w-100" 
                                    id="end_time_btn_<?= $sessionData['session']['session_id'] ?>"
                                    onclick="openTimePicker('end', <?= $sessionData['session']['session_id'] ?>)">
                                <i class="fas fa-clock me-2"></i>
                                <span id="end_time_display_<?= $sessionData['session']['session_id'] ?>">
                                    <?= $sessionData['session']['end_time'] ? date('H:i', strtotime($sessionData['session']['end_time'])) : '종료시간' ?>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    <?php endforeach; ?>
    

<?php else: ?>
    <!-- 운동 기록 없음 -->
    <div class="card">
        <div class="card-body text-center">
            <i class="fas fa-calendar-times fa-3x text-muted"></i>
            <h4 class="text-muted">이 날의 운동 기록이 없습니다</h4>
            <p class="text-muted">운동을 기록해보세요!</p>
            <a href="today.php?date=<?= $date ?>" class="btn btn-primary btn-custom">
                <i class="fas fa-plus"></i> 운동 기록하기
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- 삭제 확인 모달 -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">삭제 확인</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">정말로 삭제하시겠습니까?</p>
                <p class="text-danger"><small>삭제된 데이터는 복구할 수 없습니다.</small></p>
            </div>
            <div class="modal-footer">
                <form method="post" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" id="deleteAction">
                    <input type="hidden" name="session_id" id="deleteSessionId">
                    <input type="hidden" name="wx_id" id="deleteWxId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-danger">삭제</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 루틴 다시 시작 선택 모달 -->
<div class="modal fade" id="routineRestartModal" tabindex="-1" aria-labelledby="routineRestartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-redo"></i> 루틴 다시 시작
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">루틴을 다시 시작하시겠습니까?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>주의:</strong> 기존 루틴 기록을 어떻게 처리할지 선택해주세요.
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="restartOption" id="optionNew" value="new" checked>
                    <label class="form-check-label" for="optionNew">
                        <strong>새 기록 추가</strong> - 기존 기록은 유지하고 새로운 기록을 추가합니다
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="restartOption" id="optionReplace" value="replace">
                    <label class="form-check-label" for="optionReplace">
                        <strong>기존 기록 덮어쓰기</strong> - 기존 기록을 삭제하고 새로운 기록으로 교체합니다
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="confirmRoutineRestart()">
                    <i class="fas fa-play"></i> 시작
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 루틴 타이머 모달 -->
<div class="modal fade" id="routineModal" tabindex="-1" aria-labelledby="routineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="routineModalTitle">
                    <i class="fas fa-clock"></i> 루틴 진행 중
                </h5>
            </div>
            <div class="modal-body text-center">
                <div class="mb-4">
                    <div class="display-1 text-primary" id="routineTimer">00:00</div>
                </div>
                <div class="mb-3">
                    <h6 id="routineType">PRE-ROUTINE</h6>
                    <p class="text-muted" id="routineContent"></p>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 루틴을 완료하시면 "완료" 버튼을 눌러주세요.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success btn-lg" onclick="completeRoutine()">
                    <i class="fas fa-check"></i> 완료
                </button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="cancelRoutine()">
                    <i class="fas fa-times"></i> 취소
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// 루틴 타이머 관련 변수
let routineTimer = null;
let routineStartTime = null;
let currentRoutineType = null;
let currentSessionId = null;
let restartOption = 'new'; // 'new' 또는 'replace'

// 루틴 다시 시작 선택 모달 표시
function showRoutineRestartModal(type, sessionId) {
    currentRoutineType = type;
    currentSessionId = sessionId;
    
    // 기본값으로 "새 기록 추가" 선택
    document.getElementById('optionNew').checked = true;
    restartOption = 'new';
    
    const modal = new bootstrap.Modal(document.getElementById('routineRestartModal'));
    modal.show();
}

// 루틴 다시 시작 확인
function confirmRoutineRestart() {
    // 선택된 옵션 확인
    const selectedOption = document.querySelector('input[name="restartOption"]:checked');
    restartOption = selectedOption.value;
    
    // 다시 시작 모달 닫기
    const restartModal = bootstrap.Modal.getInstance(document.getElementById('routineRestartModal'));
    restartModal.hide();
    
    // 기존 기록 삭제 옵션이면 먼저 삭제
    if (restartOption === 'replace') {
        deleteExistingRoutineRecord();
    } else {
        // 새 기록 추가 옵션이면 바로 시작
        startRoutineTimer();
    }
}

// 기존 루틴 기록 삭제
function deleteExistingRoutineRecord() {
    fetch('delete_routine_record.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            session_id: currentSessionId,
            routine_type: currentRoutineType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('기존 루틴 기록이 삭제되었습니다.');
            startRoutineTimer();
        } else {
            console.error('기존 루틴 기록 삭제 실패:', data.message);
            showCustomAlert('기존 루틴 기록 삭제에 실패했습니다: ' + data.message, '삭제 실패', 'exclamation-triangle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCustomAlert('네트워크 오류가 발생했습니다.', '네트워크 오류', 'exclamation-triangle');
    });
}

// 루틴 시작 함수
function startRoutine(type, sessionId) {
    currentRoutineType = type;
    currentSessionId = sessionId;
    routineStartTime = new Date();
    restartOption = 'new'; // 새로 시작하는 경우는 항상 새 기록 추가
    
    // 모달 제목과 내용 설정
    const modalTitle = document.getElementById('routineModalTitle');
    const routineType = document.getElementById('routineType');
    const routineContent = document.getElementById('routineContent');
    
    if (type === 'pre') {
        modalTitle.innerHTML = '<i class="fas fa-play-circle"></i> PRE-ROUTINE 진행 중';
        routineType.textContent = 'PRE-ROUTINE';
        routineContent.textContent = '운동 전 준비 루틴을 시작합니다.';
    } else {
        modalTitle.innerHTML = '<i class="fas fa-stop-circle"></i> POST-ROUTINE 진행 중';
        routineType.textContent = 'POST-ROUTINE';
        routineContent.textContent = '운동 후 마무리 루틴을 시작합니다.';
    }
    
    // 타이머 시작
    startRoutineTimer();
    
    // 모달 표시
    const modal = new bootstrap.Modal(document.getElementById('routineModal'));
    modal.show();
}

// 루틴 타이머 시작 함수
function startRoutineTimer() {
    routineStartTime = new Date();
    
    // 타이머 시작
    routineTimer = setInterval(function() {
        const now = new Date();
        const elapsed = Math.floor((now - routineStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        const timeString = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        document.getElementById('routineTimer').textContent = timeString;
    }, 1000);
    
    // 타이머 모달 표시
    const modal = new bootstrap.Modal(document.getElementById('routineModal'));
    modal.show();
}


// 루틴 완료
function completeRoutine() {
    if (routineTimer) {
        clearInterval(routineTimer);
        routineTimer = null;
    }
    
    const endTime = new Date();
    const duration = Math.floor((endTime - routineStartTime) / 1000);
    
    // 서버에 루틴 완료 정보 전송
    saveRoutineRecord(true, duration, restartOption);
    
    // 모달 닫기
    const modal = bootstrap.Modal.getInstance(document.getElementById('routineModal'));
    modal.hide();
    
    // 성공 메시지 표시
    showAlert('success', '루틴이 완료되었습니다!');
}

// 루틴 취소
function cancelRoutine() {
    if (routineTimer) {
        clearInterval(routineTimer);
        routineTimer = null;
    }
    
    const endTime = new Date();
    const duration = Math.floor((endTime - routineStartTime) / 1000);
    
    // 서버에 루틴 취소 정보 전송
    saveRoutineRecord(false, duration, restartOption);
    
    // 모달 닫기
    const modal = bootstrap.Modal.getInstance(document.getElementById('routineModal'));
    modal.hide();
}

// 루틴 기록 저장
function saveRoutineRecord(isCompleted, duration, option = 'new') {
    console.log('저장할 데이터:', {
        session_id: currentSessionId,
        routine_type: currentRoutineType,
        is_completed: isCompleted,
        duration: duration,
        option: option
    });
    
    fetch('save_routine_record.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            session_id: currentSessionId,
            routine_type: currentRoutineType,
            is_completed: isCompleted,
            duration: duration,
            option: option
        })
    })
    .then(response => {
        console.log('서버 응답 상태:', response.status);
        console.log('응답 Content-Type:', response.headers.get('content-type'));
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            console.log('서버 응답 원본:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON 파싱 오류:', e);
                console.error('파싱할 텍스트:', text);
                throw new Error('서버 응답을 파싱할 수 없습니다: ' + text);
            }
        });
    })
    .then(data => {
        console.log('서버 응답 데이터:', data);
        if (data && data.success) {
            console.log('루틴 기록이 저장되었습니다.');
            // 페이지 새로고침하여 기록 표시
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            console.error('루틴 기록 저장 실패:', data?.message || '알 수 없는 오류');
            showCustomAlert('루틴 기록 저장에 실패했습니다: ' + (data?.message || '알 수 없는 오류'), '저장 실패', 'exclamation-triangle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCustomAlert('네트워크 오류가 발생했습니다.', '네트워크 오류', 'exclamation-triangle');
    });
}

// 알림 표시 함수
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // 페이지 상단에 알림 추가
    const container = document.querySelector('.container-fluid');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // 3초 후 자동으로 사라지게 함
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 3000);
}

// 날짜 변경 함수
function changeDate(dateString) {
    window.location.href = '?date=' + dateString;
}

// 운동 세션 삭제
function deleteSession(sessionId) {
    document.getElementById('deleteMessage').textContent = '이 운동 세션을 삭제하시겠습니까?';
    document.getElementById('deleteAction').value = 'delete_session';
    document.getElementById('deleteSessionId').value = sessionId;
    document.getElementById('deleteWxId').value = '';
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// 개별 운동 삭제
function deleteExercise(wxId) {
    document.getElementById('deleteMessage').textContent = '이 운동을 삭제하시겠습니까?';
    document.getElementById('deleteAction').value = 'delete_exercise';
    document.getElementById('deleteSessionId').value = '';
    document.getElementById('deleteWxId').value = wxId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// 운동 완료
function endWorkout(sessionId) {
    showCustomConfirm('운동을 완료하시겠습니까?', '운동 완료', 'flag-checkered', function() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="end_workout">
            <input type="hidden" name="session_id" value="${sessionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    });
}

// 운동 시간 수정
function updateWorkoutTime(sessionId) {
    const startTime = document.getElementById(`start_time_${sessionId}`).value;
    const endTime = document.getElementById(`end_time_${sessionId}`).value;
    
    if (!startTime && !endTime) {
        showCustomAlert('시작시간 또는 종료시간을 입력해주세요.', '입력 오류', 'exclamation-circle');
        return;
    }
    
    // 현재 날짜와 시간을 합쳐서 datetime 형식으로 변환
    const currentDate = new Date().toISOString().split('T')[0];
    const startDateTime = startTime ? `${currentDate} ${startTime}:00` : null;
    const endDateTime = endTime ? `${currentDate} ${endTime}:00` : null;
    
    // AJAX로 데이터 전송
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_workout_time&session_id=${sessionId}&start_time=${encodeURIComponent(startDateTime || '')}&end_time=${encodeURIComponent(endDateTime || '')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message || '수정 중 오류가 발생했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('수정 중 오류가 발생했습니다.', 'error');
    });
}

// 메시지 표시 함수
function showMessage(message, type) {
    // 기존 메시지 제거
    const existingAlert = document.querySelector('.alert-message');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // 새 메시지 생성
    let alertClass = 'alert-danger';
    if (type === 'success') {
        alertClass = 'alert-success';
    } else if (type === 'info') {
        alertClass = 'alert-info';
    } else if (type === 'warning') {
        alertClass = 'alert-warning';
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show alert-message`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // 3초 후 자동 제거
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 3000);
}

// 부위별 세부 내용 토글
function togglePartDetails(partName) {
    const details = document.getElementById('details-' + partName);
    const icon = document.getElementById('icon-' + partName);
    
    if (details.style.display === 'none') {
        details.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        details.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}
</script>

<!-- 운동 수정 모달 -->
<div class="modal fade" id="editExerciseModal" tabindex="-1" aria-labelledby="editExerciseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editExerciseModalLabel">운동 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editWxId" name="wx_id">
                <!-- 운동 입력 텍스트박스 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">운동 수정</label>
                    <textarea class="form-control" id="editExerciseInputText" rows="6" 
                              placeholder="벤치프레스 80 10 3&#10;스쿼트 100 8 4&#10;데드리프트 120 5 3"></textarea>
                    <div class="mt-2">
                        <button type="button" class="btn btn-primary" onclick="parseEditExercises()">
                            <i class="fas fa-search"></i> 검색 및 파싱
                        </button>
                    </div>
                </div>
                
                <!-- 파싱된 운동 목록 (today.php 방식) -->
                <div id="editParsedExercisesList" class="mb-4" style="display: none;">
                    <label class="form-label fw-bold">운동 목록</label>
                    <div id="editExercisesContainer">
                        <!-- 파싱된 운동들이 여기에 표시됩니다 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveEditExercises()">수정</button>
            </div>
        </div>
    </div>
</div>

<!-- 운동 추가 모달 -->
<div class="modal fade" id="addExerciseModal" tabindex="-1" aria-labelledby="addExerciseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExerciseModalLabel">운동 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 운동 입력 텍스트박스 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">운동 추가</label>
                    <textarea class="form-control" id="exerciseInputText" rows="6" 
                              placeholder="벤치프레스 80 10 3&#10;스쿼트 100 8 4&#10;데드리프트 120 5 3"></textarea>
                    <div class="mt-2">
                        <button type="button" class="btn btn-primary" onclick="searchAndParseExercises()">
                            <i class="fas fa-search"></i> 검색 및 파싱
                        </button>
                    </div>
                </div>
                
                <!-- 파싱된 운동 목록 (today.php 방식) -->
                <div id="parsedExercisesList" class="mb-4" style="display: none;">
                    <label class="form-label fw-bold">운동 목록</label>
                    <div id="exercisesContainer">
                        <!-- 파싱된 운동들이 여기에 표시됩니다 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="addSelectedExercise()">추가</button>
            </div>
        </div>
    </div>
</div>

<!-- 운동 정보 수정 모달 -->
<div class="modal fade" id="exerciseInfoModal" tabindex="-1" aria-labelledby="exerciseInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exerciseInfoModalLabel">운동 정보 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 무게 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">무게 (kg)</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('weight', -0.5)">-0.5</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('weight', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseWeightDisplay">0kg</div>
                            <input type="range" class="form-range" id="exerciseWeightSlider" min="0" max="200" step="0.5" value="0" oninput="updateExerciseWeightDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0kg</span>
                                <span>200kg</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('weight', 0.5)">+0.5</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('weight', 5)">+5</button>
                        </div>
                    </div>
                </div>
                
                <!-- 횟수 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">횟수</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('reps', -1)">-1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('reps', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseRepsDisplay">0회</div>
                            <input type="range" class="form-range" id="exerciseRepsSlider" min="0" max="50" step="1" value="0" oninput="updateExerciseRepsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0회</span>
                                <span>50회</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('reps', 1)">+1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('reps', 5)">+5</button>
                        </div>
                    </div>
                </div>
                
                <!-- 세트 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">세트</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('sets', -1)">-1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('sets', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseSetsDisplay">0세트</div>
                            <input type="range" class="form-range" id="exerciseSetsSlider" min="0" max="20" step="1" value="0" oninput="updateExerciseSetsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0세트</span>
                                <span>20세트</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustExerciseValue('sets', 1)">+1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustExerciseValue('sets', 5)">+5</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="applyExerciseInfoAdjustment()">적용</button>
            </div>
        </div>
    </div>
</div>

<!-- 세트 조정 모달 -->
<div class="modal fade" id="setAdjustModal" tabindex="-1" aria-labelledby="setAdjustModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setAdjustModalLabel">세트 조정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 무게 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">무게 (kg)</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('weight', -0.5)">-0.5</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('weight', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="weightDisplay">0kg</div>
                            <input type="range" class="form-range" id="weightSlider" min="0" max="200" step="0.5" value="0" oninput="updateWeightDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0kg</span>
                                <span>200kg</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('weight', 0.5)">+0.5</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('weight', 5)">+5</button>
                        </div>
                    </div>
                </div>
                
                <!-- 횟수 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">횟수</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('reps', -1)">-1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('reps', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="repsDisplay">0회</div>
                            <input type="range" class="form-range" id="repsSlider" min="0" max="50" step="1" value="0" oninput="updateRepsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0회</span>
                                <span>50회</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('reps', 1)">+1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('reps', 5)">+5</button>
                        </div>
                    </div>
                </div>
                
                <!-- 시간 조정 (초) -->
                <div class="mb-4">
                    <label class="form-label fw-bold">시간 (초)</label>
                    <div class="d-flex align-items-center">
                        <div class="d-flex flex-column me-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('time', -1)">-1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('time', -5)">-5</button>
                        </div>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="timeDisplay">0초</div>
                            <input type="range" class="form-range" id="timeSlider" min="0" max="300" step="1" value="0" oninput="updateTimeDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0초</span>
                                <span>300초</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column ms-2">
                            <button class="btn btn-outline-secondary btn mb-1" onclick="adjustValue('time', 1)">+1</button>
                            <button class="btn btn-outline-secondary btn" onclick="adjustValue('time', 5)">+5</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="applySetAdjustment()">적용</button>
            </div>
        </div>
    </div>
</div>

<!-- 시간 선택 모달 -->
<div class="modal fade" id="timePickerModal" tabindex="-1" aria-labelledby="timePickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timePickerModalLabel">
                    <i class="fas fa-clock me-2"></i>
                    <span id="timePickerTitle">시간 설정</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-4">
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label fw-bold">무게 (kg)</label>
                            <select class="form-select form-select-lg" id="timePickerMinute">
                                <?php for($kg = 0; $kg <= 200; $kg += 0.5): ?>
                                    <option value="<?= number_format($kg, 1) ?>"><?= number_format($kg, 1) ?>kg</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 0.5kg 단위로 설정됩니다.
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success btn-lg" onclick="confirmTimeSelection()">
                    <i class="fas fa-check"></i> 확인
                </button>
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> 취소
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 커스텀 Alert 모달 -->
<div class="modal fade" id="customAlertModal" tabindex="-1" aria-labelledby="customAlertModalLabel" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="z-index: 10000;">
            <div class="modal-header">
                <h5 class="modal-title" id="customAlertModalTitle">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>알림
                </h5>
            </div>
            <div class="modal-body text-center">
                <p id="customAlertMessage" class="mb-0 fs-5">메시지가 여기에 표시됩니다.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-lg me-2" id="customAlertCancel" onclick="hideCustomAlert()" style="display: none;">
                    <i class="fas fa-times me-1"></i>취소
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="customAlertConfirm" onclick="confirmCustomAlert()">
                    <i class="fas fa-check me-1"></i>확인
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 운동 수행 모달 -->
<div class="modal fade" id="exerciseModal" tabindex="-1" aria-labelledby="exerciseModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content workout-modal" style="background-color:red">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="exerciseModalLabel">
                    <i class="fas fa-dumbbell"></i> <span id="modalExerciseName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeModalWithoutSave()" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 운동 정보 -->
                <div class="exercise-info mb-4 d-flex justify-content-between align-items-center px-0">
                    <button type="button" class="btn btn-outline-light btn-lg" id="modalExerciseInfo" onclick="openExerciseInfoModal()" style="pointer-events: auto; transition: none !important; background-color: transparent !important; border-color: #fff !important; color: #fff !important; font-size: 1.3rem !important; font-weight: bold !important;">
                        <i class="fas fa-edit"></i> 20kg × 15회 × 5세트
                    </button>
                    <button type="button" class="btn btn-outline-light btn-lg" id="undoSetBtn" onclick="undoLastSet()" style="display: none; pointer-events: auto; transition: none !important; background-color: transparent !important; border-color: #fff !important; color: #fff !important; z-index: 1000;">
                        <i class="fas fa-backspace"></i>
                    </button>
                </div>
                
                <!-- 타이머 -->
                <div class="timer-section text-center mb-4">
                    <div class="timer-display text-success mb-3" id="modalTimer" onclick="completeSetAndReset()">0</div>
                </div>
                
                <!-- 세트 기록 -->
                <div class="sets-section">
                    <div class="sets-circles text-center" id="modalSetsContainer">
                        <!-- 세트 동그라미들이 여기에 동적으로 추가됩니다 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" onclick="closeModalWithoutSave()">
                    <i class="fas fa-times"></i> 닫기
                </button>
                <button type="button" class="btn btn-primary" onclick="finishModalExercise()">
                    <i class="fas fa-flag-checkered"></i> 운동 완료
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let modalTimerInterval;
let modalStartTime;
let modalElapsedTime = 0;
let modalIsRunning = false;
let modalCompletedSets = 0;
let modalTotalSets = 0;
let modalExerciseId = 0;

// 시간 선택 관련 전역 변수
let currentTimeType = ''; // 'start' 또는 'end'

// 모달 열기
function openExerciseModal(exerciseId, exerciseName, weight, reps, sets) {
    console.log('=== openExerciseModal 함수 시작 ===');
    console.log('파라미터들:', { exerciseId, exerciseName, weight, reps, sets });
    console.log('이전 modalExerciseId:', modalExerciseId);
    
    modalExerciseId = exerciseId;
    modalTotalSets = sets || 0;  // sets가 undefined인 경우 0으로 설정
    modalCompletedSets = 0;
    
    console.log('설정된 값들:', {
        modalExerciseId: modalExerciseId,
        modalTotalSets: modalTotalSets,
        modalCompletedSets: modalCompletedSets
    });
    
    // 현재 운동 정보를 전역 변수에 저장 (부모창 업데이트용)
    window.currentExerciseInfo = {
        id: exerciseId,
        name: exerciseName,
        weight: weight,
        reps: reps,
        sets: sets
    };
    
    // 모달 내용 설정
    document.getElementById('modalExerciseName').textContent = exerciseName;
    
    // sets 값이 undefined이거나 null인 경우 기본값 설정
    const safeSets = sets || 0;
    const numericWeight = parseFloat(weight) || 0;
    const displayWeight = numericWeight == Math.floor(numericWeight) ? Math.floor(numericWeight) : numericWeight.toFixed(1);
    document.getElementById('modalExerciseInfo').innerHTML = `${displayWeight} × ${reps} × ${safeSets}`;
    
    
    // 세트 컨테이너 초기화
    const setsContainer = document.getElementById('modalSetsContainer');
    setsContainer.innerHTML = '';
    
    // 총 0세트일 때 첫 번째 세트 시작 영역 표시
    if (sets === 0) {
        const startSetWrapper = document.createElement('div');
        startSetWrapper.className = 'set-wrapper';
        startSetWrapper.style.textAlign = 'center';
        
        const startSetMessage = document.createElement('div');
        startSetMessage.className = 'start-set-message';
        startSetMessage.innerHTML = `
            <div style="color: white; font-size: 16px; margin-bottom: 10px;">
                초를 클릭하여 첫 번째 세트 시작
            </div>
            <div style="color: #ccc; font-size: 14px;">
                운동을 시작하려면 타이머를 클릭하세요
            </div>
        `;
        
        startSetWrapper.appendChild(startSetMessage);
        setsContainer.appendChild(startSetWrapper);
    } else {
        // 세트 동그라미들 생성
        for (let i = 1; i <= safeSets; i++) {
            const setWrapper = document.createElement('div');
            setWrapper.className = 'set-wrapper';
            
            const setCircle = document.createElement('div');
            setCircle.className = 'set-square';
            setCircle.setAttribute('data-set', i);
            setCircle.setAttribute('data-weight', weight);
            setCircle.setAttribute('data-reps', reps);
            setCircle.setAttribute('data-time', 0);
            
            // 무게, 횟수 표시
            setCircle.innerHTML = `
                <div class="set-weight">${weight}kg</div>
                <div class="set-divider"></div>
                <div class="set-reps">${reps}회</div>
            `;
            
            // 클릭 이벤트 추가
            setCircle.onclick = () => {
                const currentTime = parseInt(setCircle.getAttribute('data-time')) || 0;
                openSetAdjustModal(i, weight, reps, currentTime);
            };
            
            const setTime = document.createElement('div');
            setTime.className = 'set-time';
            setTime.id = `set-time-${i}`;
            setTime.innerHTML = '';
            
            setWrapper.appendChild(setCircle);
            setWrapper.appendChild(setTime);
            setsContainer.appendChild(setWrapper);
        }
        
        // 백스페이스 버튼 가시성 초기화
        updateUndoButtonVisibility();
    }
    
    // 타이머 초기화 및 시작
    resetModalTimer();
    
    // 모달 열기
    const modal = new bootstrap.Modal(document.getElementById('exerciseModal'));
    modal.show();
    
}

// 모달 타이머 함수들
function startModalTimer() {
    if (!modalIsRunning) {
        modalStartTime = Date.now() - modalElapsedTime;
        modalTimerInterval = setInterval(updateModalTimer, 1000);
        modalIsRunning = true;
    }
}

function resetModalTimer() {
    clearInterval(modalTimerInterval);
    modalIsRunning = false;
    modalElapsedTime = 0;
    
    document.getElementById('modalTimer').textContent = '0';
    
    // 색상을 빨간색으로 초기화
    const modalContent = document.querySelector('.workout-modal');
    modalContent.style.setProperty('background-color', 'red', 'important');
    
    // 리셋 후 자동으로 다시 시작
    setTimeout(() => {
        startModalTimer();
    }, 100);
}

function completeSetAndReset() {
    // 디버깅: 현재 상태 확인
    console.log('completeSetAndReset 호출 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
    
    // 총 0세트일 때 첫 번째 세트 추가
    if (modalTotalSets === 0 && modalCompletedSets === 0) {
        addNewSet(1);
        modalTotalSets = 1;
    }
    
    // 다음 완료할 세트 찾기
    const nextSet = modalCompletedSets + 1;
    console.log('다음 완료할 세트:', nextSet);
    
    // modalTotalSets가 0이거나 음수인 경우 기본값 설정
    if (modalTotalSets <= 0) {
        modalTotalSets = 1;
    }
    
    // 다음 세트가 총 세트 수를 초과하는 경우, 총 세트 수를 늘림
    if (nextSet > modalTotalSets) {
        console.log('새 세트 추가:', nextSet);
        addNewSet(nextSet);
        modalTotalSets = nextSet;
    }
    
    // 세트 완료 처리
    console.log('세트 완료 처리:', nextSet);
    completeModalSet(nextSet);
    
    // 운동 정보의 총 세트 수 업데이트 (실제 총 세트 수 유지)
    const exerciseInfo = document.getElementById('modalExerciseInfo');
    const currentText = exerciseInfo.textContent;
    const matches = currentText.match(/(\d+kg × \d+회) × (\d+)세트/);
    if (matches) {
        exerciseInfo.textContent = `${matches[1]} × ${modalTotalSets}세트`;
    }
    
    // 모든 세트에서 타이머 리셋 (마지막 세트도 동일하게)
    resetModalTimer();
    
    // 백스페이스 버튼 표시
    updateUndoButtonVisibility();
    
    console.log('세트 완료 후 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
}

// 마지막 세트 취소 함수
function undoLastSet() {
    if (modalCompletedSets <= 0) {
        return; // 취소할 세트가 없음
    }
    
    // 확인 메시지 표시
    showCustomConfirm('방금 운동 세트를 취소하겠습니까?', '세트 취소', 'backspace', function() {
        // 사용자가 확인한 경우에만 실행
        executeUndoLastSet();
    });
    
    return; // confirm 대신 callback 사용
}

// 실제 세트 취소 실행 함수
function executeUndoLastSet() {
    if (modalCompletedSets <= 0) {
        return; // 취소할 세트가 없음
    }
    
    // 마지막 완료된 세트 찾기
    const completedSets = document.querySelectorAll('.set-square.completed');
    if (completedSets.length === 0) {
        return;
    }
    
    const lastCompletedSet = completedSets[completedSets.length - 1];
    const setNumber = parseInt(lastCompletedSet.getAttribute('data-set'));
    
    // 세트 완료 상태 취소
    lastCompletedSet.classList.remove('completed');
    
    // 시간 표시 초기화
    const setTime = document.getElementById(`set-time-${setNumber}`);
    if (setTime) {
        setTime.textContent = '';
    }
    
    // 완료된 세트 수 감소
    modalCompletedSets--;
    
    // 타이머 리셋
    resetModalTimer();
    
    // 백스페이스 버튼 가시성 업데이트
    updateUndoButtonVisibility();
    
    console.log('세트 취소 완료 - modalCompletedSets:', modalCompletedSets);
}

// 백스페이스 버튼 가시성 업데이트
function updateUndoButtonVisibility() {
    const undoBtn = document.getElementById('undoSetBtn');
    if (modalCompletedSets > 0) {
        undoBtn.style.display = 'inline-block';
    } else {
        undoBtn.style.display = 'none';
    }
}

function updateModalTimer() {
    modalElapsedTime = Date.now() - modalStartTime;
    const totalSeconds = Math.floor(modalElapsedTime / 1000);
    
    document.getElementById('modalTimer').textContent = totalSeconds;
    
    // 30초마다 색상 변경
    const colorIndex = Math.floor(totalSeconds / 30) % 7;
    const colors = ['red', 'orange', '#FFD700', 'green', 'blue', 'indigo', 'purple'];
    
    const modalContent = document.querySelector('.workout-modal');
    modalContent.style.setProperty('background-color', colors[colorIndex], 'important');
}

// 새 세트 추가 함수
function addNewSet(setNumber, weight = null, reps = null) {
    const setsContainer = document.getElementById('modalSetsContainer');
    
    // 기존 시작 메시지 제거
    const startMessage = setsContainer.querySelector('.start-set-message');
    if (startMessage) {
        startMessage.parentElement.remove();
    }
    
    // 무게, 횟수가 제공되지 않으면 운동 정보에서 가져오기
    if (weight === null || reps === null) {
        const exerciseInfo = document.getElementById('modalExerciseInfo').textContent;
        const matches = exerciseInfo.match(/(\d+(?:\.\d+)?) × (\d+)/);
        weight = matches ? parseFloat(matches[1]) : 0;
        reps = matches ? parseInt(matches[2]) : 0;
    }
    
    const setWrapper = document.createElement('div');
    setWrapper.className = 'set-wrapper';
    
    const setCircle = document.createElement('div');
    setCircle.className = 'set-square';
    setCircle.setAttribute('data-set', setNumber);
    setCircle.setAttribute('data-weight', weight);
    setCircle.setAttribute('data-reps', reps);
    setCircle.setAttribute('data-time', 0);
    
    // 무게, 횟수 표시
    setCircle.innerHTML = `
        <div class="set-weight">${weight}kg</div>
        <div class="set-divider"></div>
        <div class="set-reps">${reps}회</div>
    `;
    
    // 클릭 이벤트 추가
    setCircle.onclick = () => {
        const currentTime = parseInt(setCircle.getAttribute('data-time')) || 0;
        openSetAdjustModal(setNumber, weight, reps, currentTime);
    };
    
    const setTime = document.createElement('div');
    setTime.className = 'set-time';
    setTime.id = `set-time-${setNumber}`;
    setTime.innerHTML = '';
    
    setWrapper.appendChild(setCircle);
    setWrapper.appendChild(setTime);
    setsContainer.appendChild(setWrapper);
}

// 모달 세트 완료 처리
function completeModalSet(setNumber) {
    const setCircle = document.querySelector(`[data-set="${setNumber}"]`);
    const setTime = document.getElementById(`set-time-${setNumber}`);
    
    // 세트 요소가 존재하지 않는 경우 새로 생성
    if (!setCircle) {
        addNewSet(setNumber);
        const newSetCircle = document.querySelector(`[data-set="${setNumber}"]`);
        const newSetTime = document.getElementById(`set-time-${setNumber}`);
        
        if (newSetCircle && newSetTime) {
            // 이미 완료된 세트인지 확인
            if (newSetCircle.classList.contains('completed')) {
                return; // 이미 완료된 세트는 무시
            }
            
            // 현재 타이머 시간 가져오기
            const currentTime = document.getElementById('modalTimer').textContent;
            
            // 세트 완료 표시
            newSetCircle.classList.add('completed');
            
            // 시간 표시
            newSetTime.textContent = currentTime + '초';
            
            modalCompletedSets++;
        }
        return;
    }
    
    // 이미 완료된 세트인지 확인
    if (setCircle.classList.contains('completed')) {
        return; // 이미 완료된 세트는 무시
    }
    
    // 현재 타이머 시간 가져오기
    const currentTime = document.getElementById('modalTimer').textContent;
    
    // 세트 완료 표시
    setCircle.classList.add('completed');
    
    // 시간 표시
    setTime.textContent = currentTime + '초';
    
    modalCompletedSets++;
}

// 모달 운동 완료
function finishModalExercise() {
    if (modalCompletedSets === modalTotalSets) {
        showCustomConfirm('모든 세트를 완료하셨습니다. 운동을 기록하고 종료하시겠습니까?', '운동 완료', 'flag-checkered', function() {
            // 운동 기록 저장
            saveWorkoutRecord();
            bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
        });
    } else {
        showCustomConfirm(`아직 ${modalTotalSets - modalCompletedSets}세트가 남았습니다. 운동을 기록하고 종료하시겠습니까?`, '운동 완료', 'flag-checkered', function() {
            // 운동 기록 저장
            saveWorkoutRecord();
            bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
        });
    }
}

// 세션 수정 확인 함수
function confirmEditSession(sessionId, date) {
    showCustomDanger('⚠️ 주의: 이 운동 세션을 수정하면 현재 세션의 운동 목록이 새로 교체되고, 기존에 기록된 세트별 수행 기록(무게, 횟수, 시간 등)이 삭제됩니다.\n\n정말로 수정하시겠습니까?', '세션 수정 주의', 'exclamation-triangle', function() {
        window.location.href = `today.php?edit_session=${sessionId}&date=${date}`;
    });
}

// 운동 수정 모달 열기
function openEditExerciseModal(wxId, exerciseName, weight, reps, sets) {
    // 디버깅: 받은 값들 확인
    console.log('운동 수정 모달 열기:', {
        wxId: wxId,
        exerciseName: exerciseName,
        weight: weight,
        reps: reps,
        sets: sets
    });
    
    // 현재 운동 정보를 텍스트로 설정 (운동 추가와 동일한 형식)
    document.getElementById('editWxId').value = wxId;
    
    // sets 값이 undefined인 경우 0으로 처리
    const safeSets = sets || 0;
    document.getElementById('editExerciseInputText').value = `${exerciseName} ${weight} ${reps} ${safeSets}`;
    
    console.log('텍스트에어리어 설정 완료:', `${exerciseName} ${weight} ${reps} ${safeSets}`);
    
    // 파싱된 운동 목록 초기화
    document.getElementById('editParsedExercisesList').style.display = 'none';
    document.getElementById('editExercisesContainer').innerHTML = '';
    
    const modal = new bootstrap.Modal(document.getElementById('editExerciseModal'));
    modal.show();
}

// 운동 수정 모달에서 운동 파싱 (운동 추가와 동일한 로직)
function parseEditExercises() {
    const inputText = document.getElementById('editExerciseInputText').value.trim();
    const container = document.getElementById('editParsedExercisesList');
    const exercisesContainer = document.getElementById('editExercisesContainer');
    
    if (!inputText) {
        showCustomAlert('운동을 입력해주세요.', '입력 오류', 'exclamation-circle');
        return;
    }
    
    const lines = inputText.split('\n').filter(line => line.trim());
    const parsedExercises = [];
    
    lines.forEach((line, index) => {
        // 운동 추가와 동일한 형식: "운동명 무게 횟수 세트"
        const parts = line.trim().split(/\s+/);
        if (parts.length >= 4) {
            const exercise_name = parts.slice(0, -3).join(' ');
            const weight = parseFloat(parts[parts.length - 3]);
            const reps = parseInt(parts[parts.length - 2]);
            const sets = parseInt(parts[parts.length - 1]);
            
            if (!isNaN(weight) && !isNaN(reps) && !isNaN(sets)) {
                parsedExercises.push({
                    exercise_name: exercise_name,
                    weight: weight,
                    reps: reps,
                    sets: sets
                });
            }
        }
    });
    
    if (parsedExercises.length > 0) {
        // 각 운동에 대해 검색 수행
        searchExercisesForEdit(parsedExercises);
        container.style.display = 'block';
        window.editParsedExercises = parsedExercises;
    } else {
        showCustomAlert('올바른 형식으로 운동을 입력해주세요.', '입력 형식 오류', 'exclamation-circle');
    }
}

// 운동 수정 모달에서 운동 검색 및 표시 (운동 추가와 동일한 로직)
function searchExercisesForEdit(exercises) {
    const container = document.getElementById('editExercisesContainer');
    if (!container) {
        console.error('editExercisesContainer 요소를 찾을 수 없습니다.');
        return;
    }

    container.innerHTML = '';

    exercises.forEach((exercise, index) => {
        // 추가 모달과 동일한 카드 렌더링을 재사용
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=search_exercises&search_term=${encodeURIComponent(exercise.exercise_name)}`
        })
        .then(response => response.json())
        .then(data => {
            const results = data.success ? data.exercises : [];
            // 임시로 exercisesContainer를 editExercisesContainer로 바꿔 그 안에 동일 렌더링
            const originalId = 'exercisesContainer';
            const tempId = 'editExercisesContainer';
            // displayExerciseCard는 내부에서 originalId를 참조하므로, 동일 구조로 삽입
            const placeholder = document.createElement('div');
            placeholder.id = originalId;
            placeholder.style.display = 'none';
            container.appendChild(placeholder);
            displayExerciseCard(exercise, results, index);
            // 렌더링된 마지막 카드 노드를 edit 컨테이너로 이동
            const lastCard = placeholder.lastElementChild || placeholder.parentElement.querySelector('#exercisesContainer > .card:last-child');
            if (lastCard) {
                container.appendChild(lastCard);
            }
            placeholder.remove();
        })
        .catch(() => {
            const placeholder = document.createElement('div');
            placeholder.className = 'card mb-3';
            placeholder.innerHTML = `<div class="card-body text-danger small">검색 중 오류가 발생했습니다.</div>`;
            container.appendChild(placeholder);
        });
    });
}

// 운동 수정 저장
function saveEditExercises() {
    if (!window.editParsedExercises || window.editParsedExercises.length === 0) {
        showCustomAlert('수정할 운동을 입력해주세요.', '입력 오류', 'exclamation-circle');
        return;
    }
    
    const wxId = document.getElementById('editWxId').value;
    const exercisesToUpdate = [];
    
    // 각 운동 카드에서 선택된 운동과 입력값 수집 (add 모달과 동일 규칙)
    window.editParsedExercises.forEach((exercise, index) => {
        const exerciseName = exercise.exercise_name;
        const safeName = exerciseName.replace(/[^a-zA-Z0-9]/g, '_');
        // 다수 결과일 때 라디오 네임: selected_exercise_${safeName}
        let exId = null;
        const selectedRadio = document.querySelector(`input[name="selected_exercise_${safeName}"]:checked`);
        if (selectedRadio) {
            exId = selectedRadio.value;
        } else {
            // 단일 결과일 때 라디오가 없으므로, 카드 내에 자동으로 첫 결과를 표시하는 구조를 따름
            // displayExerciseCard에서 단일 결과는 텍스트만 출력 -> 이 경우 서버가 검색한 첫 결과 ex_id를 얻을 수 없으므로
            // data- 속성으로 주입된 것이 없으면 ex_id는 null로 두고, 서버에서 값이 동일하면 영향 행 0이 정상
            // 필요 시 이후 개선: 단일 결과도 hidden 라디오를 추가하도록 displayExerciseCard를 확장
        }

        const exerciseData = {
            exercise_name: exercise.exercise_name,
            weight: exercise.weight,
            reps: exercise.reps,
            sets: exercise.sets,
            ex_id: exId
        };
        exercisesToUpdate.push(exerciseData);
    });
    
    // 디버그: 저장 직전 데이터 로깅
    console.log('[EDIT] saveEditExercises - wxId:', wxId);
    console.log('[EDIT] saveEditExercises - exercisesToUpdate:', JSON.stringify(exercisesToUpdate, null, 2));

    if (exercisesToUpdate.length > 0) {
        updateExerciseInSession(wxId, exercisesToUpdate[0]); // 첫 번째 운동만 수정
    } else {
        showCustomAlert('수정할 운동을 선택해주세요.', '선택 오류', 'exclamation-circle');
    }
}

// 세션에서 운동 수정
function updateExerciseInSession(wxId, exercise) {
    const formData = new FormData();
    formData.append('action', 'update_exercise');
    formData.append('wx_id', wxId);
    formData.append('weight', exercise.weight);
    formData.append('reps', exercise.reps);
    formData.append('sets', exercise.sets);
    if (exercise.ex_id) {
        formData.append('ex_id', exercise.ex_id);
    }
    
    // 디버그: 전송할 FormData 로깅
    const debugPayload = {};
    formData.forEach((v, k) => { debugPayload[k] = v; });
    console.log('[EDIT] updateExerciseInSession - payload:', debugPayload);

    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            console.log('[EDIT] updateExerciseInSession - response is JSON');
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('JSON이 아닌 응답:', text);
                console.log('[EDIT] updateExerciseInSession - raw response text:', text);
                throw new Error('서버에서 JSON이 아닌 응답을 반환했습니다: ' + text);
            });
        }
    })
    .then(data => {
        // 디버그: 서버 응답 로깅
        console.log('[EDIT] updateExerciseInSession - response JSON:', data);
        if (data && data.debug) {
            console.log('[EDIT] server debug.before:', data.debug.before);
            console.log('[EDIT] server debug.after:', data.debug.after);
            console.log('[EDIT] server debug.requested:', data.debug.requested);
            console.log('[EDIT] server debug.affected_rows:', data.debug.affected_rows);
        }
        if (data.success) {
            showCustomAlert('운동이 성공적으로 수정되었습니다.', '수정 완료', 'check-circle');
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('editExerciseModal')).hide();
            // 페이지 새로고침
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            console.warn('[EDIT] update failed - message:', data.message);
            showCustomAlert('운동 수정에 실패했습니다: ' + data.message, '수정 실패', 'exclamation-triangle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCustomAlert('운동 수정 중 오류가 발생했습니다: ' + error.message, '오류', 'exclamation-triangle');
    });
}

// 순서 변경 (위/아래)
function reorderExercise(wxId, direction) {
    if (!['up', 'down'].includes(direction)) return;
    const formData = new FormData();
    formData.append('action', 'reorder_exercise');
    formData.append('wx_id', wxId);
    formData.append('direction', direction);
    
    console.log('[REORDER] request:', { wxId, direction });
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        console.log('[REORDER] response:', data);
        if (data.success) {
            location.reload();
        } else {
            // 더 자연스러운 안내로 대체
            showCustomAlert(data.message || '더 이상 이동할 수 없습니다.', '안내', 'info-circle');
        }
    })
    .catch(err => {
        console.error('[REORDER] error:', err);
        showCustomAlert('순서 변경 중 오류가 발생했습니다.', '오류', 'exclamation-triangle');
    });
}

// 운동 추가 모달 열기
function openAddExerciseModal() {
    const modal = new bootstrap.Modal(document.getElementById('addExerciseModal'));
    modal.show();
    
    // 입력 필드 초기화
    document.getElementById('exerciseInputText').value = '';
    document.getElementById('parsedExercisesList').style.display = 'none';
    document.getElementById('exercisesContainer').innerHTML = '';
    
    // 파싱된 운동 초기화
    window.parsedExercises = null;
    window.exerciseResults = {};
}

// 검색 및 파싱 (today.php 방식)
function searchAndParseExercises() {
    const inputText = document.getElementById('exerciseInputText').value.trim();
    const container = document.getElementById('parsedExercisesList');
    const exercisesContainer = document.getElementById('exercisesContainer');
    
    if (!inputText) {
        showCustomAlert('운동을 입력해주세요.', '입력 오류', 'exclamation-circle');
        return;
    }
    
    const lines = inputText.split('\n').filter(line => line.trim());
    const parsedExercises = [];
    
    lines.forEach((line, index) => {
        const trimmedLine = line.trim();
        if (trimmedLine) {
            const parts = trimmedLine.split(/\s+/);
            if (parts.length >= 1) {
                const exercise = {
                    exercise_name: parts[0],
                    weight: parts[1] ? parseFloat(parts[1]) || 0 : 0,
                    reps: parts[2] ? parseInt(parts[2]) || 0 : 0,
                    sets: parts[3] ? parseInt(parts[3]) || 0 : 0
                };
                parsedExercises.push(exercise);
            }
        }
    });
    
    if (parsedExercises.length > 0) {
        // 각 운동에 대해 검색 수행
        searchExercisesForAdd(parsedExercises);
        container.style.display = 'block';
        window.parsedExercises = parsedExercises;
    } else {
        showCustomAlert('올바른 형식으로 운동을 입력해주세요.', '입력 형식 오류', 'exclamation-circle');
    }
}

// 운동 검색 및 표시 (today.php 방식)
function searchExercisesForAdd(exercises) {
    const container = document.getElementById('exercisesContainer');
    container.innerHTML = '';
    
    exercises.forEach((exercise, index) => {
        // 각 운동에 대해 검색 요청
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=search_exercises&search_term=${encodeURIComponent(exercise.exercise_name)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayExerciseCard(exercise, data.exercises, index);
            } else {
                displayExerciseCard(exercise, [], index);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayExerciseCard(exercise, [], index);
        });
    });
}

// 운동 카드 표시 (today.php 방식)
function displayExerciseCard(exercise, searchResults, index) {
    const container = document.getElementById('exercisesContainer');
    const exerciseName = exercise.exercise_name;
    const safeName = exerciseName.replace(/[^a-zA-Z0-9]/g, '_');
    
    let html = `
        <div class="card mb-3" data-index="${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0" style="font-size: 1.1em;">${exerciseName}</h6>
                    <span class="badge bg-primary" style="font-size: 1.05em; padding: 0.35em 0.5em;">${index + 1}</span>
                </div>
    `;
    
    if (searchResults.length === 0) {
        // 검색 결과가 없는 경우
        html += `
            <div class="text-warning">
                <strong>${exerciseName}</strong> - 검색 결과 없음 (임시 운동으로 추가)
            </div>
        `;
    } else if (searchResults.length === 1) {
        // 검색 결과가 1개인 경우: 추가/수정 모두에서 동일하게 첫 결과를 기본 선택되도록 hidden 라디오를 추가
        const result = searchResults[0];
        html += `
            <input type="radio" class="form-check-input d-none" 
                   name="selected_exercise_${safeName}" 
                   id="ex_${safeName}_0_hidden" 
                   value="${result.ex_id}" checked>
            <div class="text-success">
                ✓ ${result.name_kr}
                ${result.name_en ? `<small class="text-muted">(${result.name_en})</small>` : ''}
            </div>
        `;
    } else {
        // 검색 결과가 여러 개인 경우
        html += `
            <div class="form-check">
                <input class="form-check-input" type="radio" 
                       name="selected_exercise_${safeName}" 
                       id="ex_${safeName}_0" 
                       value="${searchResults[0].ex_id}" 
                       checked>
                <label class="form-check-label" for="ex_${safeName}_0">
                    ${searchResults[0].name_kr}
                    ${searchResults[0].name_en ? `<small class="text-muted">(${searchResults[0].name_en})</small>` : ''}
                    <button type="button" class="btn btn-sm btn-link p-0 ms-2" 
                            onclick="toggleMoreResults('${safeName}')"
                            title="더 보기">
                        🔽
                    </button>
                </label>
            </div>
            
            <div id="more_results_${safeName}" class="more-results" style="display: none;">
        `;
        
        for (let i = 1; i < searchResults.length; i++) {
            const result = searchResults[i];
            html += `
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="selected_exercise_${safeName}" 
                           id="ex_${safeName}_${i}" 
                           value="${result.ex_id}">
                    <label class="form-check-label" for="ex_${safeName}_${i}">
                        ${result.name_kr}
                        ${result.name_en ? `<small class="text-muted">(${result.name_en})</small>` : ''}
                    </label>
                </div>
            `;
        }
        
        html += `</div>`;
    }
    
    // 무게, 횟수, 세트 입력 필드
    html += `
        <div class="mt-2">
            <div class="row g-2">
                <div class="col-4">
                    <input type="number" 
                           class="form-control form-control-sm" 
                           placeholder="무게(kg)" 
                           min="0" 
                           step="0.5"
                           id="weight_${safeName}"
                           value="${exercise.weight || ''}">
                </div>
                <div class="col-4">
                    <input type="number" 
                           class="form-control form-control-sm" 
                           placeholder="횟수" 
                           min="0"
                           id="reps_${safeName}"
                           value="${exercise.reps || ''}">
                </div>
                <div class="col-4">
                    <input type="number" 
                           class="form-control form-control-sm" 
                           placeholder="세트" 
                           min="0"
                           id="sets_${safeName}"
                           value="${exercise.sets || ''}">
                </div>
            </div>
        </div>
    `;
    
    html += `</div></div>`;
    
    container.insertAdjacentHTML('beforeend', html);
}

// 더 보기 토글
function toggleMoreResults(safeName) {
    const moreResults = document.getElementById(`more_results_${safeName}`);
    const button = event.target;
    
    if (moreResults.style.display === 'none') {
        moreResults.style.display = 'block';
        button.textContent = '🔼';
    } else {
        moreResults.style.display = 'none';
        button.textContent = '🔽';
    }
}


// 선택된 운동 추가 (today.php 방식)
function addSelectedExercise() {
    if (!window.parsedExercises || window.parsedExercises.length === 0) {
        showCustomAlert('추가할 운동을 입력해주세요.', '입력 오류', 'exclamation-circle');
        return;
    }
    
    const exercisesToAdd = [];
    
    // 각 운동 카드에서 선택된 운동과 입력값 수집
    window.parsedExercises.forEach((exercise, index) => {
        const safeName = exercise.exercise_name.replace(/[^a-zA-Z0-9]/g, '_');
        const card = document.querySelector(`[data-index="${index}"]`);
        
        if (card) {
            // 라디오 버튼이 있는 운동들 (여러 검색 결과)
            const checkedRadio = card.querySelector('input[type="radio"]:checked');
            if (checkedRadio) {
                const exerciseId = checkedRadio.value;
                const exerciseName = exercise.exercise_name;
                
                // 무게, 횟수, 세트 값 가져오기
                const weight = parseFloat(document.getElementById(`weight_${safeName}`).value) || 0;
                const reps = parseInt(document.getElementById(`reps_${safeName}`).value) || 0;
                const sets = parseInt(document.getElementById(`sets_${safeName}`).value) || 0;
                
                exercisesToAdd.push({
                    ex_id: exerciseId,
                    exercise_name: exerciseName,
                    weight: weight,
                    reps: reps,
                    sets: sets,
                    type: 'search'
                });
            } else {
                // 검색 결과가 없는 경우 (임시 운동)
                const weight = parseFloat(document.getElementById(`weight_${safeName}`).value) || 0;
                const reps = parseInt(document.getElementById(`reps_${safeName}`).value) || 0;
                const sets = parseInt(document.getElementById(`sets_${safeName}`).value) || 0;
                
                exercisesToAdd.push({
                    exercise_name: exercise.exercise_name,
                    weight: weight,
                    reps: reps,
                    sets: sets,
                    type: 'manual'
                });
            }
        }
    });
    
    if (exercisesToAdd.length > 0) {
        addExercisesToSession(exercisesToAdd);
    } else {
        showCustomAlert('추가할 운동을 선택해주세요.', '선택 오류', 'exclamation-circle');
    }
}

// 세션에 운동들 추가
function addExercisesToSession(exercises) {
    const sessionId = <?= $sessionData['session']['session_id'] ?? 'null' ?>;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=add_exercises&session_id=${sessionId}&exercises_data=${encodeURIComponent(JSON.stringify(exercises))}`
    })
    .then(response => {
        // 응답이 JSON인지 확인
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // JSON이 아닌 경우 텍스트로 읽기
            return response.text().then(text => {
                console.error('JSON이 아닌 응답:', text);
                throw new Error('서버에서 JSON이 아닌 응답을 반환했습니다: ' + text);
            });
        }
    })
    .then(data => {
        if (data.success) {
            showCustomAlert(`${exercises.length}개의 운동이 성공적으로 추가되었습니다.`, '운동 추가 완료', 'check-circle');
            // 페이지 새로고침으로 목록 업데이트
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            console.error('운동 추가 실패:', data.message);
            showCustomAlert('운동 추가에 실패했습니다: ' + data.message, '추가 실패', 'exclamation-triangle');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCustomAlert('운동 추가 중 오류가 발생했습니다: ' + error.message, '오류', 'exclamation-triangle');
    });
}


// 세트 조정 모달 열기
function openSetAdjustModal(setNumber, currentWeight, currentReps, currentTime = 0) {
    console.log('=== openSetAdjustModal 함수 시작 ===');
    console.log('파라미터들:', { setNumber, currentWeight, currentReps, currentTime });
    console.log('현재 modalExerciseId:', modalExerciseId);
    
    // modalExerciseId가 설정되어 있는지 확인
    if (!modalExerciseId) {
        console.error('modalExerciseId가 설정되지 않았습니다. 세트 조정 모달을 열 수 없습니다.');
        showMessage('운동 정보가 없습니다. 다시 시도해주세요.', 'error');
        return;
    }
    
    // 완료된 세트인 경우 현재 시간 값을 가져오기
    const setElement = document.querySelector(`[data-set="${setNumber}"]`);
    if (setElement && setElement.classList.contains('completed')) {
        const timeElement = setElement.querySelector('.set-time');
        if (timeElement) {
            const timeText = timeElement.textContent.replace('초', '');
            currentTime = parseInt(timeText) || 0;
            console.log('완료된 세트의 현재 시간:', currentTime);
        }
    }
    
    // 슬라이더와 디스플레이 업데이트
    document.getElementById('weightSlider').value = currentWeight;
    document.getElementById('repsSlider').value = currentReps;
    document.getElementById('timeSlider').value = currentTime;
    document.getElementById('weightDisplay').textContent = `${currentWeight}kg`;
    document.getElementById('repsDisplay').textContent = `${currentReps}회`;
    document.getElementById('timeDisplay').textContent = `${currentTime}초`;
    
    // 현재 조정 중인 세트 번호와 세션 ID 저장
    window.currentAdjustingSet = setNumber;
    window.currentSessionId = <?= $sessionData['session']['session_id'] ?? 'null' ?>;
    
    console.log('설정된 값들:', {
        currentAdjustingSet: window.currentAdjustingSet,
        currentSessionId: window.currentSessionId,
        modalExerciseId: modalExerciseId
    });
    
    const modal = new bootstrap.Modal(document.getElementById('setAdjustModal'));
    modal.show();
    
    console.log('=== openSetAdjustModal 함수 끝 ===');
}

// 값 조정 함수
function adjustValue(type, change) {
    const slider = document.getElementById(`${type}Slider`);
    const display = document.getElementById(`${type}Display`);
    const currentValue = parseFloat(slider.value) || 0;
    // 유지: 0.5 단위 증감과 소수 첫째자리 고정
    const newValue = Math.max(0, parseFloat((currentValue + change).toFixed(1)));
    
    slider.value = newValue;
    if (type === 'weight') {
        const displayWeight = newValue == Math.floor(newValue) ? Math.floor(newValue) : newValue.toFixed(1);
        display.textContent = `${displayWeight}kg`;
    } else if (type === 'reps') {
        display.textContent = `${newValue}회`;
    } else if (type === 'time') {
        display.textContent = `${newValue}초`;
    }
}

// 슬라이더 값 변경 시 디스플레이 업데이트
function updateWeightDisplay(value) {
    const weight = parseFloat(value);
    const displayWeight = weight == Math.floor(weight) ? Math.floor(weight) : weight.toFixed(1);
    document.getElementById('weightDisplay').textContent = `${displayWeight}kg`;
}

function updateRepsDisplay(value) {
    document.getElementById('repsDisplay').textContent = `${value}회`;
}

function updateTimeDisplay(value) {
    document.getElementById('timeDisplay').textContent = `${value}초`;
}


// 세트 조정 적용
function applySetAdjustment() {
    console.log('=== applySetAdjustment 함수 시작 ===');
    
    const newWeight = parseFloat(document.getElementById('weightSlider').value) || 0;
    const newReps = parseInt(document.getElementById('repsSlider').value) || 0;
    const newTime = parseInt(document.getElementById('timeSlider').value) || 0;
    const setNumber = window.currentAdjustingSet;
    const sessionId = window.currentSessionId;
    
    console.log('새로운 값들:', { newWeight, newReps, newTime, setNumber, sessionId });
    
    // 해당 세트의 현재 값 확인
    const setElement = document.querySelector(`[data-set="${setNumber}"]`);
    if (setElement) {
        const currentWeight = parseFloat(setElement.getAttribute('data-weight')) || 0;
        const currentReps = parseInt(setElement.getAttribute('data-reps')) || 0;
        const currentTime = parseInt(setElement.getAttribute('data-time')) || 0;
        
        console.log('현재 값들:', { currentWeight, currentReps, currentTime });
        console.log('값 변경 여부:', {
            weightChanged: currentWeight !== newWeight,
            repsChanged: currentReps !== newReps,
            timeChanged: currentTime !== newTime
        });
        
        // 값이 변경되지 않은 경우
        if (currentWeight === newWeight && currentReps === newReps && currentTime === newTime) {
            console.log('값이 변경되지 않았습니다. 업데이트를 건너뜁니다.');
            showMessage('변경된 내용이 없습니다.', 'info');
            
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('setAdjustModal')).hide();
            return;
        }
        
        setElement.setAttribute('data-weight', newWeight);
        setElement.setAttribute('data-reps', newReps);
        setElement.setAttribute('data-time', newTime);
        
        // 화면에 표시된 값 업데이트
        const weightElement = setElement.querySelector('.set-weight');
        const repsElement = setElement.querySelector('.set-reps');
        
        if (weightElement) weightElement.textContent = `${newWeight}kg`;
        if (repsElement) repsElement.textContent = `${newReps}회`;
        
        // 완료된 세트인 경우 기존 시간 요소 업데이트
        if (setElement.classList.contains('completed')) {
            // 세트 버튼 안의 시간 요소 업데이트
            const setTimeElement = setElement.querySelector('.set-time');
            if (setTimeElement) {
                setTimeElement.textContent = `${newTime}초`;
            }
            
            // 세트 번호별 시간 요소 업데이트 (기존 시간 표시 영역)
            const timeElement = document.getElementById(`set-time-${setNumber}`);
            if (timeElement) {
                timeElement.textContent = `${newTime}초`;
            }
        }
        
        // 완료된 세트만 저장 (미완료 세트는 저장하지 않음)
        if (setElement.classList.contains('completed')) {
            // 데이터베이스에 저장
            saveSetAdjustment(sessionId, setNumber, newWeight, newReps, newTime);
        } else {
            // 미완료 세트는 저장하지 않고 조용히 종료
        }
    }
    
    // 모달 닫기
    bootstrap.Modal.getInstance(document.getElementById('setAdjustModal')).hide();
}

// 세트 조정 데이터베이스 저장
function saveSetAdjustment(sessionId, setNumber, weight, reps, time) {
    console.log('=== saveSetAdjustment 함수 시작 ===');
    console.log('파라미터들:', { sessionId, setNumber, weight, reps, time });
    
    // 현재 운동 ID 가져오기
    const exerciseId = modalExerciseId;
    console.log('modalExerciseId:', exerciseId);
    
    if (!exerciseId) {
        console.error('modalExerciseId가 설정되지 않았습니다.');
        showMessage('운동 ID가 설정되지 않았습니다.', 'error');
        return;
    }
    
    const requestBody = `action=update_set_data&wx_id=${exerciseId}&weight=${weight}&reps=${reps}&time=${time}`;
    console.log('요청 본문:', requestBody);
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: requestBody
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        console.log('Response headers:', [...response.headers.entries()]);
        
        // 응답이 JSON인지 확인
        const contentType = response.headers.get('content-type');
        console.log('Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            console.error('응답이 JSON이 아닙니다. Content-Type:', contentType);
            // 응답을 텍스트로 읽어서 확인
            return response.text().then(text => {
                console.error('응답 텍스트:', text);
                throw new Error(`JSON이 아닌 응답을 받았습니다: ${text.substring(0, 200)}...`);
            });
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            console.log('세트 데이터가 성공적으로 저장되었습니다.');
            
            // 원래 운동 카드의 정보 업데이트
            if (modalExerciseId && window.currentExerciseInfo) {
                // 첫 번째 세트의 정보를 가져와서 원래 카드 업데이트
                const firstSetElement = document.querySelector('[data-set="1"]');
                if (firstSetElement) {
                    const firstSetWeight = parseFloat(firstSetElement.getAttribute('data-weight')) || 0;
                    const firstSetReps = parseInt(firstSetElement.getAttribute('data-reps')) || 0;
                    
                    console.log('첫 번째 세트 정보로 카드 업데이트:', { firstSetWeight, firstSetReps });
                    
                    // 원래 카드 업데이트 (세트 수는 변경되지 않음)
                    updateOriginalExerciseList(modalExerciseId, firstSetWeight, firstSetReps, window.currentExerciseInfo.sets);
                }
            }
            
            showMessage('세트 데이터가 성공적으로 저장되었습니다.', 'success');
        } else {
            console.error('세트 데이터 저장 실패:', data.message);
            showMessage('세트 데이터 저장에 실패했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('=== saveSetAdjustment 오류 발생 ===');
        console.error('Error 객체:', error);
        console.error('Error name:', error.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        showMessage('세트 데이터 저장 중 오류가 발생했습니다: ' + error.message, 'error');
    });
    
    console.log('=== saveSetAdjustment 함수 끝 ===');
}

// 운동 기록 저장 함수
function saveWorkoutRecord() {
    const setTimes = [];
    const setData = []; // 각 세트의 무게, 횟수 정보
    
    // 모든 완료된 세트 수집 (기존 완료된 세트 + 새로 완료된 세트)
    const allCompletedSets = document.querySelectorAll('.set-square.completed');
    
    allCompletedSets.forEach((setCircle, index) => {
        const setNo = parseInt(setCircle.getAttribute('data-set')) || (index + 1);
        const setTimeElement = document.getElementById(`set-time-${setNo}`);
        
        let time = 0;
        let weight = 0;
        let reps = 0;
        
        if (setTimeElement && setTimeElement.textContent) {
            const timeText = setTimeElement.textContent.replace('초', '');
            time = parseInt(timeText) || 0;
        }
        
        if (setCircle) {
            weight = parseInt(setCircle.getAttribute('data-weight')) || 0;
            reps = parseInt(setCircle.getAttribute('data-reps')) || 0;
        }
        
        setTimes.push(time);
        setData.push({ weight, reps, time });
    });
    
    // 세트별 시간을 모두 합해서 총 운동 시간 계산
    const total_time = setTimes.reduce((sum, time) => sum + time, 0);
    
    // 실제 완료된 세트 수로 업데이트
    modalCompletedSets = allCompletedSets.length;
    
    const data = {
        wx_id: modalExerciseId,
        completed_sets: modalCompletedSets,
        total_sets: modalTotalSets,
        total_time: total_time, // 세트별 시간의 합
        set_times: setTimes,
        set_data: setData // 각 세트의 무게, 횟수 정보
    };
    
    console.log('운동 기록 저장:', data);
    
    // 서버에 운동 기록 저장 요청
    fetch('save_workout_record.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            console.log('운동 기록 저장 성공:', result.message);
            // 페이지 새로고침
            location.reload();
        } else {
            console.error('운동 기록 저장 실패:', result.message);
            showCustomAlert('운동 기록 저장에 실패했습니다: ' + result.message, '저장 실패', 'exclamation-triangle');
        }
    })
    .catch(error => {
        console.error('운동 기록 저장 오류:', error);
        showCustomAlert('운동 기록 저장 중 오류가 발생했습니다.', '저장 오류', 'exclamation-triangle');
    });
}

// 기록 없이 닫기
function closeModalWithoutSave() {
    showCustomWarning('운동 기록 없이 종료하시겠습니까?', '기록 없이 종료', 'times-circle', function() {
        bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
    });
}

// 완료된 운동 로드
function loadCompletedExercise(wxId) {
    // AJAX로 완료된 운동 데이터 가져오기
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=get_completed_exercise&wx_id=${wxId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 운동 모달 열기
            openExerciseModal(data.exercise.wx_id, data.exercise.name_kr, data.exercise.weight, data.exercise.reps, data.exercise.sets);
            
            // 완료된 세트들 로드
            loadCompletedSets(data.sets);
        } else {
            showMessage('완료된 운동 데이터를 불러올 수 없습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('완료된 운동 데이터 로드 중 오류가 발생했습니다.', 'error');
    });
}

// 완료된 세트들 로드
function loadCompletedSets(sets) {
    const setsContainer = document.getElementById('modalSetsContainer');
    setsContainer.innerHTML = '';
    
    if (sets && sets.length > 0) {
        // 완료된 세트들 표시
        sets.forEach((set, index) => {
            const setWrapper = document.createElement('div');
            setWrapper.className = 'set-wrapper';
            
            const setCircle = document.createElement('div');
            setCircle.className = 'set-square completed';
            setCircle.setAttribute('data-set', set.set_no);
            setCircle.setAttribute('data-weight', set.weight);
            setCircle.setAttribute('data-reps', set.reps);
            setCircle.setAttribute('data-time', set.time_seconds || 0);
            
            // 완료된 세트 표시 (시간은 세트 버튼 안에 표시하지 않음)
            setCircle.innerHTML = `
                <div class="set-weight">${set.weight == Math.floor(set.weight) ? Math.floor(set.weight) : set.weight.toFixed(1)}kg</div>
                <div class="set-divider"></div>
                <div class="set-reps">${set.reps}회</div>
            `;
            
            // 클릭 이벤트 추가
            setCircle.onclick = () => {
                const currentTime = parseInt(setCircle.getAttribute('data-time')) || 0;
                openSetAdjustModal(set.set_no, parseFloat(setCircle.getAttribute('data-weight')), parseInt(setCircle.getAttribute('data-reps')), currentTime);
            };
            
            setWrapper.appendChild(setCircle);
            
            // rest_time을 개별 세트 수행 시간으로 사용
            if (set.time_seconds && set.time_seconds > 0) {
                const timeDiv = document.createElement('div');
                timeDiv.className = 'set-time';
                timeDiv.id = `set-time-${set.set_no}`;
                timeDiv.textContent = `${set.time_seconds}초`;
                
                setWrapper.appendChild(timeDiv);
            }
            setsContainer.appendChild(setWrapper);
        });
        
        // 미완료 세트들도 미리 생성 (총 세트 수만큼)
        for (let i = sets.length + 1; i <= modalTotalSets; i++) {
            addNewSet(i, sets[0].weight, sets[0].reps); // 첫 번째 세트의 무게/횟수 사용
        }
        
        // 완료된 세트 수 업데이트
        modalCompletedSets = sets.length;
    }
}

// 시간 포맷팅 (초를 mm:ss로)
function formatTime(seconds) {
    if (!seconds) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

// 운동 정보 수정 모달 열기
function openExerciseInfoModal() {
    console.log('=== openExerciseInfoModal 함수 시작 ===');
    
    // 현재 운동 정보에서 무게, 횟수, 세트 추출
    const exerciseInfo = document.getElementById('modalExerciseInfo').textContent;
    const matches = exerciseInfo.match(/(\d+(?:\.\d+)?) × (\d+) × (\d+)/);
    
    let weight = 0, reps = 0, sets = 0;
    if (matches) {
        weight = parseFloat(matches[1]);
        reps = parseInt(matches[2]);
        sets = parseInt(matches[3]) || 0;  // undefined인 경우 0으로 설정
    }
    
    console.log('현재 운동 정보:', { weight, reps, sets });
    
    // 원래 값을 전역 변수에 저장 (변경사항 확인용)
    window.originalExerciseValues = {
        weight: weight,
        reps: reps,
        sets: sets
    };
    
    console.log('원래 값 저장:', window.originalExerciseValues);
    
    // 슬라이더와 디스플레이 업데이트
    document.getElementById('exerciseWeightSlider').value = weight;
    document.getElementById('exerciseRepsSlider').value = reps;
    document.getElementById('exerciseSetsSlider').value = sets;
    document.getElementById('exerciseWeightDisplay').textContent = `${weight}kg`;
    document.getElementById('exerciseRepsDisplay').textContent = `${reps}회`;
    document.getElementById('exerciseSetsDisplay').textContent = `${sets}세트`;
    
    // modalExerciseId는 이미 openExerciseModal에서 설정됨
    console.log('현재 modalExerciseId:', modalExerciseId);
    
    const modal = new bootstrap.Modal(document.getElementById('exerciseInfoModal'));
    modal.show();
}

// 운동 정보 값 조정 함수
function adjustExerciseValue(type, change) {
    const slider = document.getElementById(`exercise${type.charAt(0).toUpperCase() + type.slice(1)}Slider`);
    const display = document.getElementById(`exercise${type.charAt(0).toUpperCase() + type.slice(1)}Display`);
    
    if (type === 'weight') {
        const currentValue = parseFloat(slider.value) || 0;
        // 0.5 단위 증감과 소수 첫째자리 고정
        const newValue = Math.max(0, parseFloat((currentValue + change).toFixed(1)));
        
        slider.value = newValue;
        const displayWeight = newValue == Math.floor(newValue) ? Math.floor(newValue) : newValue.toFixed(1);
        display.textContent = `${displayWeight}kg`;
    } else {
        const currentValue = parseInt(slider.value) || 0;
        const newValue = Math.max(0, currentValue + change);
        
        slider.value = newValue;
        if (type === 'reps') {
            display.textContent = `${newValue}회`;
        } else if (type === 'sets') {
            display.textContent = `${newValue}세트`;
        }
    }
    
    // 값이 변경되면 UI만 업데이트 (저장은 적용 버튼에서)
}

// 운동 정보 슬라이더 값 변경 시 디스플레이 업데이트
function updateExerciseWeightDisplay(value) {
    const weight = parseFloat(value);
    const displayWeight = weight == Math.floor(weight) ? Math.floor(weight) : weight.toFixed(1);
    document.getElementById('exerciseWeightDisplay').textContent = `${displayWeight}kg`;
}

function updateExerciseRepsDisplay(value) {
    document.getElementById('exerciseRepsDisplay').textContent = `${value}회`;
}

function updateExerciseSetsDisplay(value) {
    document.getElementById('exerciseSetsDisplay').textContent = `${value}세트`;
}

// 운동 정보 조정 적용
function applyExerciseInfoAdjustment() {
    console.log('=== applyExerciseInfoAdjustment 함수 시작 ===');
    
    const weightSlider = document.getElementById('exerciseWeightSlider');
    const repsSlider = document.getElementById('exerciseRepsSlider');
    const setsSlider = document.getElementById('exerciseSetsSlider');
    
    console.log('슬라이더 요소들:', {
        weightSlider: weightSlider,
        repsSlider: repsSlider,
        setsSlider: setsSlider
    });
    
    const newWeight = parseFloat(weightSlider?.value) || 0;
    const newReps = parseInt(repsSlider?.value) || 0;
    const newSets = parseInt(setsSlider?.value) || 0;
    
    console.log('슬라이더 값들:', {
        weightSliderValue: weightSlider?.value,
        repsSliderValue: repsSlider?.value,
        setsSliderValue: setsSlider?.value,
        parsedWeight: newWeight,
        parsedReps: newReps,
        parsedSets: newSets
    });
    
    // 디버그: 전송할 데이터 확인
    console.log('modalExerciseId 값:', modalExerciseId);
    console.log('전송할 데이터:', {
        action: 'update_exercise_info',
        wx_id: modalExerciseId,
        weight: newWeight,
        reps: newReps,
        sets: newSets
    });
    
    // modalExerciseId가 없으면 에러
    if (!modalExerciseId) {
        console.error('modalExerciseId가 설정되지 않았습니다.');
        showMessage('운동 ID가 설정되지 않았습니다.', 'error');
        return;
    }
    
    // 원래 값과 비교
    const originalValues = window.originalExerciseValues;
    if (originalValues) {
        console.log('원래 값과 비교:', {
            original: originalValues,
            new: { weight: newWeight, reps: newReps, sets: newSets }
        });
        
        const hasChanges = (
            originalValues.weight !== newWeight ||
            originalValues.reps !== newReps ||
            originalValues.sets !== newSets
        );
        
        console.log('변경사항 여부:', hasChanges);
        
        if (!hasChanges) {
            console.log('값이 변경되지 않았습니다. 업데이트를 건너뜁니다.');
            showMessage('변경된 내용이 없습니다.', 'info');
            
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('exerciseInfoModal')).hide();
            return;
        }
    }
    
    const requestBody = `action=update_exercise_info&wx_id=${modalExerciseId}&weight=${newWeight}&reps=${newReps}&sets=${newSets}`;
    console.log('요청 본문:', requestBody);
    
    // 데이터베이스에 저장
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: requestBody
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);
        console.log('Response ok:', response.ok);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        console.log('modalExerciseId:', modalExerciseId);
        console.log('newWeight:', newWeight, 'newReps:', newReps, 'newSets:', newSets);
        
        
        if (data.success) {
            // 운동 정보 버튼 텍스트 업데이트
            const exerciseInfoButton = document.getElementById('modalExerciseInfo');
            const safeNewSets = newSets || 0;  // undefined인 경우 0으로 설정
            const displayWeight = newWeight == Math.floor(newWeight) ? Math.floor(newWeight) : newWeight.toFixed(1);
            exerciseInfoButton.innerHTML = `${displayWeight} × ${newReps} × ${safeNewSets}`;
            
            
            // modalTotalSets 업데이트
            console.log('운동 정보 업데이트 전 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
            modalTotalSets = safeNewSets;
            
            // modalCompletedSets 조정 (새로운 총 세트 수에 맞게 조정)
            if (modalCompletedSets > modalTotalSets) {
                modalCompletedSets = modalTotalSets;
            }
            // 새로운 세트 수가 0이면 완료된 세트 수도 0으로 설정
            else if (modalTotalSets === 0) {
                modalCompletedSets = 0;
            }
            console.log('운동 정보 업데이트 후 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
            
            // 완료된 세트들은 아예 건드리지 않음
            
            // 기존 세트들 업데이트 (수행된 내용 유지)
            const setsContainer = document.getElementById('modalSetsContainer');
            const existingSetWrappers = setsContainer.querySelectorAll('.set-wrapper');
            
            if (newSets > 0) {
                // 기존 세트들 업데이트 (미완료 세트만)
                existingSetWrappers.forEach((setWrapper, index) => {
                    const setSquare = setWrapper.querySelector('.set-square');
                    if (setSquare && index < newSets) {
                        // 완료된 세트인지 확인
                        const isCompleted = setSquare.classList.contains('completed');
                        
                        if (!isCompleted) {
                            // 미완료 세트만 무게와 횟수 업데이트
                            const weightElement = setSquare.querySelector('.set-weight');
                            const repsElement = setSquare.querySelector('.set-reps');
                            
                            if (weightElement) weightElement.textContent = `${newWeight}kg`;
                            if (repsElement) repsElement.textContent = `${newReps}회`;
                            
                            // data 속성 업데이트
                            setSquare.setAttribute('data-weight', newWeight);
                            setSquare.setAttribute('data-reps', newReps);
                        }
                        // 완료된 세트는 아무것도 건드리지 않음
                    }
                });
                
                // 세트 수가 늘어난 경우 추가 세트 생성
                if (newSets > existingSetWrappers.length) {
                    for (let i = existingSetWrappers.length + 1; i <= newSets; i++) {
                        addNewSet(i, newWeight, newReps);
                    }
                }
                // 세트 수가 줄어든 경우 초과 세트 제거
                else if (newSets < existingSetWrappers.length) {
                    for (let i = newSets; i < existingSetWrappers.length; i++) {
                        if (existingSetWrappers[i]) {
                            existingSetWrappers[i].remove();
                        }
                    }
                }
            } else {
                // 0세트일 때 모든 세트 제거하고 시작 메시지 표시
                setsContainer.innerHTML = '';
                const startSetWrapper = document.createElement('div');
                startSetWrapper.className = 'set-wrapper';
                startSetWrapper.style.textAlign = 'center';
                
                const startSetMessage = document.createElement('div');
                startSetMessage.className = 'start-set-message';
                startSetMessage.innerHTML = `
                    <div style="color: white; font-size: 16px; margin-bottom: 10px;">
                        초를 클릭하여 첫 번째 세트 시작
                    </div>
                    <div style="color: #ccc; font-size: 14px;">
                        운동을 시작하려면 타이머를 클릭하세요
                    </div>
                `;
                
                startSetWrapper.appendChild(startSetMessage);
                setsContainer.appendChild(startSetWrapper);
            }
            
            // 완료된 세트들의 상태를 새로운 세트 수에 맞게 조정
            if (modalTotalSets > 0) {
                // 기존 완료된 세트들 중 새로운 세트 수를 초과하는 것들을 미완료로 변경
                const completedSets = setsContainer.querySelectorAll('.set-square.completed');
                completedSets.forEach((setSquare, index) => {
                    const setNumber = parseInt(setSquare.getAttribute('data-set'));
                    if (setNumber > modalTotalSets) {
                        setSquare.classList.remove('completed');
                        const setTime = document.getElementById(`set-time-${setNumber}`);
                        if (setTime) {
                            setTime.textContent = '';
                        }
                    }
                });
                
                // modalCompletedSets를 실제 완료된 세트 수로 재계산
                modalCompletedSets = setsContainer.querySelectorAll('.set-square.completed').length;
                console.log('완료된 세트 수 재계산 후 - modalCompletedSets:', modalCompletedSets);
                
                // 백스페이스 버튼 가시성 업데이트
                updateUndoButtonVisibility();
            }
            
            // 원래 운동 리스트의 해당 운동 정보도 업데이트
            updateOriginalExerciseList(modalExerciseId, newWeight, newReps, newSets);
            
            // 성공 메시지 표시
            showMessage(data.message || '운동 정보가 성공적으로 저장되었습니다.', 'success');
            
            // 모달 닫기
            bootstrap.Modal.getInstance(document.getElementById('exerciseInfoModal')).hide();
        } else {
            console.error('운동 정보 저장 실패 - 응답 데이터:', data);
            showMessage('운동 정보 저장에 실패했습니다: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('=== applyExerciseInfoAdjustment 오류 발생 ===');
        console.error('Error 객체:', error);
        console.error('Error name:', error.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        console.error('modalExerciseId:', modalExerciseId);
        console.error('newWeight:', newWeight, 'newReps:', newReps, 'newSets:', newSets);
        console.error('요청 본문:', requestBody);
        showMessage('운동 정보 저장 중 오류가 발생했습니다: ' + error.message, 'error');
    });
    
    console.log('=== applyExerciseInfoAdjustment 함수 끝 ===');
}

// 운동 정보 데이터베이스 저장
function saveExerciseInfo(wxId, weight, reps, sets) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_exercise_info&wx_id=${wxId}&weight=${weight}&reps=${reps}&sets=${sets}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('운동 정보가 성공적으로 저장되었습니다.');
        } else {
            console.error('운동 정보 저장 실패:', data.message);
            showMessage('운동 정보 저장에 실패했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('운동 정보 저장 중 오류가 발생했습니다.', 'error');
    });
}

// 모달 세트 컨테이너 업데이트
function updateModalSetsContainer() {
    console.log('=== updateModalSetsContainer 함수 시작 ===');
    console.log('modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
    
    const setsContainer = document.getElementById('modalSetsContainer');
    if (!setsContainer) {
        console.error('modalSetsContainer 요소를 찾을 수 없습니다.');
        return;
    }
    
    // 현재 운동 정보에서 무게와 횟수 가져오기
    const exerciseInfo = document.getElementById('modalExerciseInfo').textContent;
    const matches = exerciseInfo.match(/(\d+(?:\.\d+)?) × (\d+)/);
    const currentWeight = matches ? parseFloat(matches[1]) : 0;
    const currentReps = matches ? parseInt(matches[2]) : 0;
    
    console.log('현재 운동 정보:', { currentWeight, currentReps });
    
    // 기존 세트들 모두 제거
    setsContainer.innerHTML = '';
    
    if (modalTotalSets === 0) {
        // 0세트인 경우 시작 메시지 표시
        const startSetWrapper = document.createElement('div');
        startSetWrapper.className = 'set-wrapper';
        startSetWrapper.style.textAlign = 'center';
        startSetWrapper.style.padding = '20px';
        
        const startSetMessage = document.createElement('div');
        startSetMessage.innerHTML = `
            <div class="text-muted">
                <i class="fas fa-play-circle fa-2x mb-2"></i><br>
                <strong>세트를 시작하려면</strong><br>
                <small>아래 버튼을 눌러주세요</small>
            </div>
        `;
        
        startSetWrapper.appendChild(startSetMessage);
        setsContainer.appendChild(startSetWrapper);
    } else {
        // 세트 동그라미들 생성
        for (let i = 1; i <= modalTotalSets; i++) {
            const setWrapper = document.createElement('div');
            setWrapper.className = 'set-wrapper';
            
            const setCircle = document.createElement('div');
            setCircle.className = 'set-square';
            setCircle.setAttribute('data-set', i);
            setCircle.setAttribute('data-weight', currentWeight);
            setCircle.setAttribute('data-reps', currentReps);
            setCircle.setAttribute('data-time', 0);
            
            // 완료된 세트인지 확인
            const isCompleted = i <= modalCompletedSets;
            if (isCompleted) {
                setCircle.classList.add('completed');
            }
            
            const displayWeight = currentWeight == Math.floor(currentWeight) ? Math.floor(currentWeight) : currentWeight.toFixed(1);
            
            if (isCompleted) {
                // 완료된 세트는 아예 건드리지 않음 - 기존 요소를 그대로 사용
                const existingSet = document.querySelector(`[data-set="${i}"]`);
                if (existingSet) {
                    // 기존 완료된 세트를 그대로 복사해서 사용
                    const clonedSet = existingSet.cloneNode(true);
                    
                    // 세트 버튼 안의 시간 표시 제거 (밑에 별도로 표시되므로)
                    const timeElement = clonedSet.querySelector('.set-time');
                    if (timeElement) {
                        timeElement.remove();
                    }
                    
                    setWrapper.appendChild(clonedSet);
                    continue; // 다음 세트로 넘어감
                }
            }
            
            // 미완료 세트만 새로 생성
            setCircle.innerHTML = `
                <div class="set-weight">${displayWeight}kg</div>
                <div class="set-divider"></div>
                <div class="set-reps">${currentReps}회</div>
            `;
            
            // 클릭 이벤트 추가
            setCircle.onclick = () => {
                const currentTime = parseInt(setCircle.getAttribute('data-time')) || 0;
                openSetAdjustModal(i, currentWeight, currentReps, currentTime);
            };
            
            setWrapper.appendChild(setCircle);
            setsContainer.appendChild(setWrapper);
        }
    }
    
    // 백스페이스 버튼 가시성 업데이트
    updateUndoButtonVisibility();
    
    console.log('세트 컨테이너 업데이트 완료 - 생성된 세트 수:', modalTotalSets);
}

// 원래 운동 리스트의 해당 운동 정보 업데이트
function updateOriginalExerciseList(exerciseId, weight, reps, sets) {
    console.log('=== updateOriginalExerciseList 함수 시작 ===');
    console.log('파라미터들:', { exerciseId, weight, reps, sets });
    
    // 저장된 현재 운동 정보 업데이트
    if (window.currentExerciseInfo && window.currentExerciseInfo.id === exerciseId) {
        window.currentExerciseInfo.weight = weight;
        window.currentExerciseInfo.reps = reps;
        window.currentExerciseInfo.sets = sets;
        console.log('window.currentExerciseInfo 업데이트 완료');
    }
    
    // 현재 페이지의 운동 카드들 업데이트
    const exerciseCards = document.querySelectorAll('.card[onclick*="openExerciseModal"]');
    console.log('찾은 운동 카드 수:', exerciseCards.length);
    
    exerciseCards.forEach((card, index) => {
        const onclickAttr = card.getAttribute('onclick');
        if (!onclickAttr) return;
        
        const match = onclickAttr.match(/openExerciseModal\((\d+),/);
        
        if (match && parseInt(match[1]) === exerciseId) {
            console.log(`운동 카드 ${index} 업데이트 중:`, card);
            
            // 카드의 onclick 속성 업데이트
            const exerciseName = window.currentExerciseInfo?.name || '운동';
            card.setAttribute('onclick', `openExerciseModal(${exerciseId}, '${exerciseName}', ${weight}, ${reps}, ${sets})`);
            
            // 카드 내부의 표시 텍스트 업데이트
            const weightRepsSetsElement = card.querySelector('.fs-5');
            if (weightRepsSetsElement) {
                weightRepsSetsElement.textContent = `${weight}kg × ${reps}회 × ${sets}세트`;
                console.log('카드 표시 텍스트 업데이트 완료:', weightRepsSetsElement.textContent);
            }
            
            // 수행세트수/총세트수 표시 업데이트
            const completedSetsElement = card.querySelector('span.ms-1.fw-bold');
            if (completedSetsElement) {
                // 현재 완료된 세트 수를 가져오고, 총 세트 수에 맞게 조정
                const currentText = completedSetsElement.textContent;
                let currentCompletedSets = parseInt(currentText.split('/')[0]) || 0;
                
                // 총 세트 수가 줄어들면 완료된 세트 수도 조정
                if (currentCompletedSets > sets) {
                    currentCompletedSets = sets;
                }
                
                completedSetsElement.textContent = `${currentCompletedSets}/${sets}`;
                console.log('수행세트수/총세트수 업데이트 완료:', completedSetsElement.textContent);
                
                // 완료 상태 버튼의 스타일도 업데이트
                const completionButton = completedSetsElement.closest('button');
                if (completionButton) {
                    if (currentCompletedSets >= sets && sets > 0) {
                        completionButton.className = completionButton.className.replace('btn-outline-secondary', 'btn-success');
                        completionButton.title = '완료됨';
                    } else {
                        completionButton.className = completionButton.className.replace('btn-success', 'btn-outline-secondary');
                        completionButton.title = '미완료';
                    }
                }
            }
        }
    });
    
    // 모든 운동 링크를 찾아서 해당 운동 정보 업데이트 (기존 코드 유지)
    const exerciseButtons = document.querySelectorAll('.exercise-row a[onclick*="openExerciseModal"]');
    let found = false;
    
    exerciseButtons.forEach((button, index) => {
        const onclickAttr = button.getAttribute('onclick');
        if (!onclickAttr) return; // onclick 속성이 없으면 건너뛰기
        
        const match = onclickAttr.match(/openExerciseModal\((\d+),/);
        
        if (match && parseInt(match[1]) === exerciseId) {
            found = true;
            console.log(`운동 ${exerciseId} 찾음, 업데이트 중...`);
            
            // 운동 정보 텍스트 업데이트 (a 태그 안의 small 태그)
            const exerciseInfoElement = button.querySelector('small.text-muted');
            if (exerciseInfoElement) {
                exerciseInfoElement.innerHTML = `${weight}kg × ${reps}회 × ${sets}세트`;
                console.log('운동 정보 텍스트 업데이트 완료');
            }
            
            // onclick 속성 업데이트
            const exerciseNameElement = button.querySelector('strong');
            const exerciseName = exerciseNameElement ? exerciseNameElement.textContent.trim() : '';
            button.setAttribute('onclick', `openExerciseModal(${exerciseId}, '${exerciseName}', ${weight}, ${reps}, ${sets})`);
            console.log('onclick 속성 업데이트 완료');
        }
    });
    
    if (!found) {
        console.log('운동을 찾을 수 없음. 모든 운동 ID 확인:');
        exerciseButtons.forEach((button, index) => {
            const onclickAttr = button.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/openExerciseModal\((\d+),/);
                if (match) {
                    console.log(`링크 ${index}: wx_id = ${match[1]}`);
                }
            }
        });
    }
}


</script>

<style>
/* 앱바 스타일 */
.app-bar {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 0;
}

.app-bar h4 {
    color: #212529 !important;
}

.app-bar i {
    color: #007bff !important;
}

.sets-circles {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.set-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.set-square {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: white;
    color: black;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
    padding: 4px;
}

.set-weight {
    font-size: 12px;
    color: #007bff;
    font-weight: bold;
    margin-bottom: 1px;
}

.set-divider {
    width: 35px;
    height: 1px;
    min-height: 1px;
    background-color: #ffffff;
    margin: 1px 0;
    border-radius: 0.5px;
    flex-shrink: 0;
    display: block;
    border: none;
    box-sizing: border-box;
}

.set-reps {
    font-size: 12px;
    color: #6c757d;
    font-weight: bold;
    margin-top: 1px;
}

.set-square:hover {
    background: #dee2e6;
    transform: scale(1.1);
}

.set-square.completed {
    background: #28a745;
    color: white;
    border-color: #28a745;
    cursor: default;
}

.set-square.completed:hover {
    transform: none;
    background: #28a745;
}

/* 운동 추가 모달 z-index 설정 */
#addExerciseModal {
    z-index: 1070 !important;
}

#addExerciseModal .modal-backdrop {
    z-index: 1069 !important;
}

/* 운동 정보 수정 모달 z-index 설정 */
#exerciseInfoModal {
    z-index: 1080 !important;
}

#exerciseInfoModal .modal-backdrop {
    z-index: 1079 !important;
}

/* 운동 수정 모달 z-index 설정 */
#editExerciseModal {
    z-index: 1090 !important;
}

#editExerciseModal .modal-backdrop {
    z-index: 1089 !important;
}

/* 세트 조정 모달 z-index 설정 */
#setAdjustModal {
    z-index: 1060 !important;
}

#setAdjustModal .modal-backdrop {
    z-index: 1059 !important;
}

.set-time {
    font-size: 16px;
    color: #6c757d;
    font-weight: bold;
    text-align: center;
    min-height: 20px;
    font-family: inherit;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.timer-display {
    font-family: inherit;
    font-weight: bold;
    font-size: 12rem;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
    line-height: 1;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.timer-display:hover {
    transform: scale(1.05);
}

.workout-modal {
    background-color: red !important;
    color: white !important;
    box-shadow: 0 15px 40px rgba(0,0,0,0.4), 0 5px 15px rgba(0,0,0,0.2) !important;
    border-radius: 20px !important;
}

.workout-modal * {
    color: white !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important;
}

/* 제목과 헤더 텍스트 강화 */
.workout-modal .modal-title,
.workout-modal h1,
.workout-modal h2,
.workout-modal h3,
.workout-modal h4,
.workout-modal h5,
.workout-modal h6 {
    text-shadow: 0 3px 6px rgba(0,0,0,0.7), 0 1px 2px rgba(0,0,0,0.5) !important;
}

/* 버튼 텍스트 강화 */
.workout-modal .btn {
    text-shadow: 0 2px 4px rgba(0,0,0,0.6) !important;
}

/* 세트 네모 내부 텍스트 강화 */
.workout-modal .set-weight,
.workout-modal .set-reps {
    text-shadow: 1px 1px 1px rgba(0,0,0,0.8) !important;
    font-weight: bold !important;
}

/* 시간 표시 강화 */
.workout-modal .set-time {
    text-shadow: 0 3px 6px rgba(0,0,0,0.8), 0 1px 2px rgba(0,0,0,0.6) !important;
    font-weight: bold !important;
}

/* 라벨과 작은 텍스트 */
.workout-modal .form-label,
.workout-modal .small,
.workout-modal .text-muted {
    text-shadow: 0 1px 3px rgba(0,0,0,0.6) !important;
}

/* 진행률 바 텍스트 */
.workout-modal .progress-bar {
    text-shadow: 0 2px 4px rgba(0,0,0,0.8) !important;
    font-weight: bold !important;
}

/* 모든 버튼에 그림자 효과 */
.workout-modal .btn {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2), 0 2px 4px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease !important;
}

.workout-modal .btn:hover {
    box-shadow: 0 6px 12px rgba(0,0,0,0.3), 0 3px 6px rgba(0,0,0,0.2) !important;
    transform: translateY(-1px) !important;
}

.workout-modal .btn:active {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
    transform: translateY(0) !important;
}

/* 세트 네모에 그림자 효과 */
.workout-modal .set-square {
    box-shadow: none !important;
    transition: all 0.3s ease !important;
}

.workout-modal .set-square:hover {
    box-shadow: none !important;
    transform: translateY(-2px) !important;
}

.workout-modal .set-square.completed {
    box-shadow: none !important;
}

/* 시간 표시에 그림자 효과 */
.workout-modal .set-time {
    text-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
}

/* 운동 정보 카드에 그림자 효과 */
.workout-modal .exercise-info {
    box-shadow: none !important;
    border-radius: 10px !important;
    padding: 1rem !important;
    margin-bottom: 1rem !important;
    background: rgba(255,255,255,0.05) !important;
}

/* 루틴 내용 스타일 */
.routine-content {
    white-space: pre-line;
    line-height: 1.6;
    font-size: 14px;
    color: #333;
}

/* 시작 세트 메시지 스타일 */
.start-set-message {
    background: rgba(255, 255, 255, 0.1);
    border: 2px dashed rgba(255, 255, 255, 0.3);
    border-radius: 15px;
    padding: 20px;
    margin: 10px 0;
    text-align: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

/* 진행률 바에 그림자 효과 */
.workout-modal .progress {
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1) !important;
}

.workout-modal .progress-bar {
    box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
}

/* 입력 필드에 그림자 효과 */
.workout-modal .form-control {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    border: 1px solid rgba(0,0,0,0.1) !important;
}

.workout-modal .form-control:focus {
    box-shadow: 0 4px 8px rgba(0,123,255,0.2), 0 2px 4px rgba(0,123,255,0.1) !important;
}

/* 모달 헤더와 푸터에 그림자 */
.workout-modal .modal-header {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
}

.workout-modal .modal-footer {
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
}

/* 모달 전체에 깊이감 추가 */
.workout-modal .modal-dialog {
    transform: perspective(1000px) rotateX(2deg) !important;
    transition: transform 0.3s ease !important;
}

.workout-modal.show .modal-dialog {
    transform: perspective(1000px) rotateX(0deg) !important;
}

.workout-time-edit {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.workout-time-edit .form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

/* 버튼 아이콘 스타일 */
.btn i.fas {
    display: inline-block !important;
    font-style: normal !important;
    font-variant: normal !important;
    text-rendering: auto !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    line-height: 1 !important;
}

.btn-sm i.fas {
    font-size: 0.875rem !important;
}

.btn-group-sm .btn i.fas {
    font-size: 0.875rem !important;
    width: 1em !important;
    height: 1em !important;
    line-height: 1 !important;
}

/* 운동 정보 버튼 스타일 */
#modalExerciseInfo {
    font-size: 1.3rem !important;
    font-weight: bold !important;
    position: relative !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 100% !important;
    transition: font-size 0.3s ease !important;
}

#modalExerciseInfo:active,
#modalExerciseInfo:focus {
    position: relative !important;
    transform: none !important;
}

/* 호버 효과 완전 제거 */
#modalExerciseInfo:hover {
    background-color: transparent !important;
    border-color: #fff !important;
    color: #fff !important;
    transform: none !important;
    box-shadow: none !important;
    font-size: 1.3rem !important;
    font-weight: bold !important;
}

#undoSetBtn:hover {
    background-color: transparent !important;
    border-color: #fff !important;
    color: #fff !important;
    transform: translateY(-50%) !important;
    box-shadow: none !important;
}

/* 취소 버튼 스타일 */
#undoSetBtn {
    z-index: 1000 !important;
}

/* 커스텀 Alert 모달 z-index */
#customAlertModal {
    z-index: 9999 !important;
}

#customAlertModal .modal-dialog {
    z-index: 10000 !important;
}

#customAlertModal .modal-content {
    z-index: 10001 !important;
}

/* SB Admin 2 스타일 카드 */
.card.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.card.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
</style>

<script>
// 뒤로가기 함수
function goBack() {
    // history.back()을 사용하여 이전 페이지로 이동
    window.history.back();
}

// 커스텀 Alert 함수들
let customAlertCallback = null;

// 커스텀 Alert 표시 (확인만)
function showCustomAlert(message, title = '알림', icon = 'exclamation-triangle') {
    document.getElementById('customAlertModalTitle').innerHTML = `<i class="fas fa-${icon} text-warning me-2"></i>${title}`;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertCancel').style.display = 'none';
    document.getElementById('customAlertConfirm').textContent = '확인';
    document.getElementById('customAlertConfirm').className = 'btn btn-primary btn-lg';
    
    const modal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    modal.show();
    
    customAlertCallback = null;
}

// 커스텀 Confirm 표시 (확인/취소)
function showCustomConfirm(message, title = '확인', icon = 'question-circle', onConfirm = null) {
    document.getElementById('customAlertModalTitle').innerHTML = `<i class="fas fa-${icon} text-primary me-2"></i>${title}`;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertCancel').style.display = 'inline-block';
    document.getElementById('customAlertConfirm').textContent = '확인';
    document.getElementById('customAlertConfirm').className = 'btn btn-primary btn-lg';
    
    const modal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    modal.show();
    
    customAlertCallback = onConfirm;
}

// 커스텀 경고 표시 (경고/취소)
function showCustomWarning(message, title = '경고', icon = 'exclamation-triangle', onConfirm = null) {
    document.getElementById('customAlertModalTitle').innerHTML = `<i class="fas fa-${icon} text-warning me-2"></i>${title}`;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertCancel').style.display = 'inline-block';
    document.getElementById('customAlertConfirm').textContent = '확인';
    document.getElementById('customAlertConfirm').className = 'btn btn-warning btn-lg';
    
    const modal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    modal.show();
    
    customAlertCallback = onConfirm;
}

// 커스텀 위험 표시 (삭제/취소)
function showCustomDanger(message, title = '위험', icon = 'exclamation-triangle', onConfirm = null) {
    document.getElementById('customAlertModalTitle').innerHTML = `<i class="fas fa-${icon} text-danger me-2"></i>${title}`;
    document.getElementById('customAlertMessage').textContent = message;
    document.getElementById('customAlertCancel').style.display = 'inline-block';
    document.getElementById('customAlertConfirm').textContent = '확인';
    document.getElementById('customAlertConfirm').className = 'btn btn-danger btn-lg';
    
    const modal = new bootstrap.Modal(document.getElementById('customAlertModal'));
    modal.show();
    
    customAlertCallback = onConfirm;
}

// 확인 버튼 클릭
function confirmCustomAlert() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('customAlertModal'));
    modal.hide();
    
    if (customAlertCallback) {
        customAlertCallback();
    }
}

// 취소 버튼 클릭
function hideCustomAlert() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('customAlertModal'));
    modal.hide();
    
    customAlertCallback = null;
}

// 무게 선택 모달 열기
function openTimePicker(timeType, sessionId) {
    console.log('=== openTimePicker 함수 시작 ===');
    console.log('파라미터들:', { timeType, sessionId });
    
    currentTimeType = timeType;
    currentSessionId = sessionId;
    
    // 모달 제목 설정
    const title = timeType === 'start' ? '시작 무게 설정' : '종료 무게 설정';
    document.getElementById('timePickerTitle').textContent = title;
    
    // 현재 무게로 초기화 (기본값 0kg)
    document.getElementById('timePickerMinute').value = '0.0';
    
    // 모달 표시
    const modal = new bootstrap.Modal(document.getElementById('timePickerModal'));
    modal.show();
    
    console.log('무게 선택 모달 열기 완료');
}

// 무게 선택 확인
function confirmTimeSelection() {
    console.log('=== confirmTimeSelection 함수 시작 ===');
    
    const selectedWeight = document.getElementById('timePickerMinute').value;
    const selectedTime = `${selectedWeight}kg`;
    
    console.log('선택된 무게:', selectedTime);
    console.log('무게 타입:', currentTimeType);
    console.log('세션 ID:', currentSessionId);
    
    // 버튼 텍스트 업데이트
    const displayElement = document.getElementById(`${currentTimeType}_time_display_${currentSessionId}`);
    if (displayElement) {
        displayElement.textContent = selectedTime;
        console.log('버튼 텍스트 업데이트 완료:', selectedTime);
    }
    
    // 모달 닫기
    const modal = bootstrap.Modal.getInstance(document.getElementById('timePickerModal'));
    modal.hide();
    
    // 즉시 서버에 저장
    saveTimeToServer();
    
    console.log('무게 선택 완료');
}

// 무게를 서버에 저장
function saveTimeToServer() {
    console.log('=== saveTimeToServer 함수 시작 ===');
    
    const startTimeElement = document.getElementById(`start_time_display_${currentSessionId}`);
    const endTimeElement = document.getElementById(`end_time_display_${currentSessionId}`);
    
    // 텍스트 내용을 가져와서 공백과 줄바꿈 제거
    const startWeight = startTimeElement ? startTimeElement.textContent.trim() : '';
    const endWeight = endTimeElement ? endTimeElement.textContent.trim() : '';
    
    console.log('현재 시작무게:', `"${startWeight}"`);
    console.log('현재 종료무게:', `"${endWeight}"`);
    
    // 시작무게와 종료무게가 모두 설정되었을 때만 저장
    if (startWeight !== '시작시간' && startWeight !== '' && endWeight !== '종료시간' && endWeight !== '') {
        // AJAX 요청으로 서버에 무게 업데이트
        fetch('my_workouts_ing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_workout_time&session_id=${currentSessionId}&start_time=${encodeURIComponent(startWeight)}&end_time=${encodeURIComponent(endWeight)}`
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was not JSON:', text);
                    throw new Error('서버 응답이 JSON 형식이 아닙니다: ' + text.substring(0, 100));
                }
            });
        })
        .then(data => {
            if (data.success) {
                console.log('운동 무게가 성공적으로 저장되었습니다.');
            } else {
                console.error('운동 무게 저장 실패:', data.message);
                showMessage('운동 무게 저장에 실패했습니다: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('운동 무게 저장 중 오류가 발생했습니다: ' + error.message, 'error');
        });
    } else {
        console.log('시작무게와 종료무게가 모두 설정되지 않아 저장하지 않습니다.');
    }
}

// 운동 시간 업데이트 (기존 함수 수정)
function updateWorkoutTime(sessionId) {
    console.log('=== updateWorkoutTime 함수 시작 ===');
    console.log('세션 ID:', sessionId);
    
    const startTime = document.getElementById(`start_time_display_${sessionId}`).textContent;
    const endTime = document.getElementById(`end_time_display_${sessionId}`).textContent;
    
    console.log('시작시간:', startTime);
    console.log('종료시간:', endTime);
    
    if (startTime === '시간 설정' || endTime === '시간 설정') {
        showMessage('시작시간과 종료시간을 모두 설정해주세요.', 'warning');
        return;
    }
    
    // AJAX 요청으로 서버에 시간 업데이트
    fetch('my_workouts_ing.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_workout_time&session_id=${sessionId}&start_time=${startTime}&end_time=${endTime}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('운동 시간이 성공적으로 업데이트되었습니다.', 'success');
        } else {
            showMessage('운동 시간 업데이트에 실패했습니다: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('운동 시간 업데이트 중 오류가 발생했습니다.', 'error');
    });
}
</script>

<?php
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
                // 운동 시간 수정
                $session_id = $_POST['session_id'];
                $start_time = $_POST['start_time'] ?: null;
                $end_time = $_POST['end_time'] ?: null;
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
                    exit;
                }
                
                // 시간 업데이트
                $stmt = $pdo->prepare("UPDATE m_workout_session SET start_time = ?, end_time = ? WHERE session_id = ?");
                $stmt->execute([$start_time, $end_time, $session_id]);
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => '운동 시간이 수정되었습니다.']);
                    exit;
                }
                
                $message = "운동 시간이 수정되었습니다.";
                
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
                
                // 운동 삭제 (CASCADE로 관련 세트들도 자동 삭제됨)
                $stmt = $pdo->prepare("DELETE FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                
                $message = "운동이 성공적으로 삭제되었습니다.";
                
            } elseif ($_POST['action'] === 'update_set_data') {
                // 세트 데이터 업데이트
                $wx_id = $_POST['wx_id'];
                $weight = $_POST['weight'];
                $reps = $_POST['reps'];
                
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
                
                // 세트 데이터 업데이트
                $stmt = $pdo->prepare("UPDATE m_workout_exercise SET weight = ?, reps = ? WHERE wx_id = ?");
                $stmt->execute([$weight, $reps, $wx_id]);
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => '세트 데이터가 성공적으로 업데이트되었습니다.']);
                    exit;
                }
                
                $message = "세트 데이터가 성공적으로 업데이트되었습니다.";
                
            } elseif ($_POST['action'] === 'update_exercise_info') {
                // 디버그: 받은 POST 데이터 확인
                error_log("update_exercise_info 요청 받음 - POST 데이터: " . print_r($_POST, true));
                
                // 운동 정보 업데이트 (무게, 횟수, 세트)
                $wx_id = $_POST['wx_id'];
                $weight = $_POST['weight'];
                $reps = $_POST['reps'];
                $sets = $_POST['sets'];
                
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
                $stmt = $pdo->prepare("SELECT weight, reps, sets FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $current_values = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 데이터 타입 변환 확인
                $weight = (float)$weight;
                $reps = (int)$reps;
                $sets = (int)$sets;
                
                // 운동 정보 업데이트
                $stmt = $pdo->prepare("UPDATE m_workout_exercise SET weight = ?, reps = ?, sets = ? WHERE wx_id = ?");
                $result = $stmt->execute([$weight, $reps, $sets, $wx_id]);
                
                // 디버깅: 업데이트 결과 확인
                $affected_rows = $stmt->rowCount();
                error_log("업데이트 시도 - wx_id: $wx_id, weight: $weight, reps: $reps, sets: $sets, result: " . ($result ? 'true' : 'false') . ", affected_rows: $affected_rows");
                
                // 업데이트 후 값 확인
                $stmt = $pdo->prepare("SELECT weight, reps, sets FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                $after_values = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // AJAX 요청인 경우 JSON 응답
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    if ($result && $affected_rows > 0) {
                        echo json_encode([
                            'success' => true, 
                            'message' => "운동 정보가 성공적으로 업데이트되었습니다. (영향받은 행: {$affected_rows})",
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false, 
                            'message' => "업데이트 실패. 영향받은 행: {$affected_rows}"
                        ]);
                    }
                    exit;
                }
                
                $message = "운동 정보가 성공적으로 업데이트되었습니다.";
                
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
                    SELECT set_no, weight, reps, rest_time, completed_at
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
                            $exerciseData['weight'],
                            $exerciseData['reps'],
                            $exerciseData['sets'],
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
                $pdo->commit();
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
               te.exercise_name as temp_exercise_name
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
               te.exercise_name as temp_exercise_name
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
    
    $sessionsWithExercises[] = [
        'session' => $session,
        'exercises' => $exercises,
        'round' => $index + 1, // 1회차, 2회차...
        'session_volume' => $sessionVolume,
        'session_percentage' => $totalDayVolume > 0 ? round(($sessionVolume / $totalDayVolume) * 100, 1) : 0,
        'pre_routine' => $preRoutine,
        'post_routine' => $postRoutine
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

<!-- 날짜 네비게이션 -->
<div class="date-navigation">
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="btn btn-outline-primary btn-custom">
        <i class="fas fa-chevron-left"></i>
    </a>
    <div class="date-display">
        <input type="date" id="datePicker" value="<?= $date ?>" onchange="changeDate(this.value)" class="form-control">
    </div>
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="btn btn-outline-primary btn-custom">
        <i class="fas fa-chevron-right"></i>
    </a>
</div>


<?php if (!empty($sessionsWithExercises)): ?>
    <!-- 각 세션별 운동 목록 -->
    <?php foreach ($sessionsWithExercises as $sessionData): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white">
                <i class="fas fa-play-circle"></i> <?= $sessionData['round'] ?>
            </h5>
            <div class="btn-group btn-group-sm">
                <?php if ($sessionData['session']['start_time'] && !$sessionData['session']['end_time']): ?>
                    <button type="button" class="btn btn-success btn-sm" 
                            onclick="endWorkout(<?= $sessionData['session']['session_id'] ?>)">
                        <i class="fas fa-flag-checkered"></i> 운동 완료
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-light btn-sm border" 
                        onclick="confirmEditSession(<?= $sessionData['session']['session_id'] ?>, '<?= $date ?>')">
                    <i class="fas fa-edit"></i> 수정
                </button>
                <button type="button" class="btn btn-light btn-sm border text-danger" 
                        onclick="deleteSession(<?= $sessionData['session']['session_id'] ?>)">
                    <i class="fas fa-trash"></i> 삭제
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- 1. 본운동 목록 -->
            <div class="mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-dumbbell"></i> 본운동</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($sessionData['exercises'] as $exercise): ?>
                    <div class="exercise-row d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div class="exercise-name">
                            <a href="#" 
                               class="text-decoration-none text-dark"
                               onclick="openExerciseModal(<?= $exercise['wx_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>', <?= number_format($exercise['weight'], 0) ?>, <?= $exercise['reps'] ?>, <?= $exercise['sets'] ?>)">
                                <strong><?= htmlspecialchars($exercise['name_kr']) ?></strong>
                                <?php if ($exercise['is_temp']): ?>
                                    <span class="badge bg-warning text-dark ms-1">임시</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    <?= number_format($exercise['weight'], 0) ?>kg × <?= $exercise['reps'] ?>회 × <?= $exercise['sets'] ?>세트
                                    <?php if ($exercise['note']): ?>
                                        <br><em><?= htmlspecialchars($exercise['note']) ?></em>
                                    <?php endif; ?>
                                </small>
                            </a>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <!-- 완료 상태 버튼 -->
                            <button type="button" class="btn btn-sm border <?= $exercise['is_completed'] ? 'btn-success' : 'btn-outline-secondary' ?>" 
                                    title="<?= $exercise['is_completed'] ? '완료됨' : '미완료' ?>"
                                    onclick="loadCompletedExercise(<?= $exercise['wx_id'] ?>)">
                                <i class="fas fa-check"></i>
                                <small><?= $exercise['completed_sets'] ?>/<?= $exercise['sets'] ?></small>
                            </button>
                            <a href="today.php?edit_exercise=<?= $exercise['wx_id'] ?>&date=<?= $date ?>" 
                               class="btn btn-light btn-sm border">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-light btn-sm border text-danger" 
                                    onclick="deleteExercise(<?= $exercise['wx_id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                        <?php endforeach; ?>
                        
                        <!-- 운동 추가 버튼 (하단) -->
                        <div class="mt-3 text-center p-3">
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="openAddExerciseModal()">
                                <i class="fas fa-plus"></i> 운동 추가
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3. 엔드루틴 -->
            <?php if (!empty($postRoutine)): ?>
            <div class="mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="fas fa-stop"></i> 엔드루틴 (운동 후)</h6>
                    </div>
                    <div class="card-body">
                        <div class="routine-content"><?= nl2br(htmlspecialchars($postRoutine)) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 4. 프리루틴 -->
            <?php if (!empty($preRoutine)): ?>
            <div class="mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-play"></i> 프리루틴 (운동 전)</h6>
                    </div>
                    <div class="card-body">
                        <div class="routine-content"><?= nl2br(htmlspecialchars($preRoutine)) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 5. 운동 시간 정보 -->
            <div class="workout-time-edit mb-3">
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label">시작시간</label>
                        <input type="time" 
                               class="form-control form-control-sm" 
                               id="start_time_<?= $sessionData['session']['session_id'] ?>"
                               value="<?= $sessionData['session']['start_time'] ? date('H:i', strtotime($sessionData['session']['start_time'])) : '' ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">종료시간</label>
                        <input type="time" 
                               class="form-control form-control-sm" 
                               id="end_time_<?= $sessionData['session']['session_id'] ?>"
                               value="<?= $sessionData['session']['end_time'] ? date('H:i', strtotime($sessionData['session']['end_time'])) : '' ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" 
                                class="btn btn-primary btn-sm" 
                                onclick="updateWorkoutTime(<?= $sessionData['session']['session_id'] ?>)">
                            <i class="fas fa-save"></i> 수정
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- 전체 운동 분석 (한 번만 표시) -->
    <?php if (!empty($allMuscleAnalysis)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="text-primary mb-0">
                    <i class="fas fa-chart-line"></i> 전체 운동 분석
                </h5>
                <div class="mt-2">
                    <small class="text-muted">
                        총 볼륨: <?= number_format($totalDayVolume) ?>kg | 
                        가중치 볼륨: <?= number_format($totalWeightedVolume) ?>kg
                    </small>
                </div>
            </div>
            <div class="card-body">
                <!-- 운동 수행률 요약 (계획 vs 수행) -->
                <div class="muscle-summary-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-info mb-0">
                            <i class="fas fa-chart-bar"></i> 운동 수행률 요약
                        </h6>
                        <!-- 범례 -->
                        <div>
                            <span class="badge bg-success me-2">수행률</span>
                            <span class="badge bg-info">계획률</span>
                        </div>
                    </div>
                    
                    <?php
                    // 계획된 운동 부위별 데이터
                    $plannedParts = [];
                    foreach ($allMuscleAnalysis as $muscleCode => $muscleData) {
                        if ($muscleData['percentage'] > 0) {
                            $partName = $muscleData['part_name'];
                            if (!isset($plannedParts[$partName])) {
                                $plannedParts[$partName] = 0;
                            }
                            $plannedParts[$partName] += $muscleData['percentage'];
                        }
                    }
                    
                    // 수행된 운동 부위별 데이터
                    $performedParts = [];
                    foreach ($performanceByBodyPart as $partCode => $partData) {
                        if ($partData['percentage'] > 0) {
                            $partName = $partData['part_name'];
                            $performedParts[$partName] = $partData['percentage'];
                        }
                    }
                    
                    // 모든 부위 통합 (계획 + 수행)
                    $allParts = array_unique(array_merge(array_keys($plannedParts), array_keys($performedParts)));
                    
                    // 퍼센트 기준으로 정렬 (계획 기준)
                    uasort($allParts, function($a, $b) use ($plannedParts) {
                        $aPercent = $plannedParts[$a] ?? 0;
                        $bPercent = $plannedParts[$b] ?? 0;
                        return $bPercent <=> $aPercent;
                    });
                    
                    // 1, 2등과 기타 분리
                    $topParts = array_slice($allParts, 0, 2, true);
                    $otherParts = array_slice($allParts, 2, null, true);
                    ?>
                    
                    <div class="row">
                        <!-- 1, 2등 부위 -->
                        <?php foreach ($topParts as $partName): ?>
                            <?php 
                            $plannedPercent = $plannedParts[$partName] ?? 0;
                            $performedPercent = $performedParts[$partName] ?? 0;
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="part-summary-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($partName) ?></strong>
                                            <?php if ($performedPercent > 0): ?>
                                                <span class="badge bg-success ms-2"><?= round($performedPercent, 1) ?>%</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info ms-1"><?= round($plannedPercent, 1) ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                        <!-- 100% 회색 배경 -->
                                        <!-- 계획된 부분 (파란색) -->
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $plannedPercent ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                        <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                        <?php if ($performedPercent > 0): ?>
                                            <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $performedPercent ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- 기타 부위들 -->
                        <?php if (!empty($otherParts)): ?>
                            <?php 
                            $otherPlannedTotal = 0;
                            $otherPerformedTotal = 0;
                            foreach ($otherParts as $partName) {
                                $otherPlannedTotal += $plannedParts[$partName] ?? 0;
                                $otherPerformedTotal += $performedParts[$partName] ?? 0;
                            }
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="part-summary-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>기타</strong>
                                            <?php if ($otherPerformedTotal > 0): ?>
                                                <span class="badge bg-success ms-2"><?= round($otherPerformedTotal, 1) ?>%</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info ms-1"><?= round($otherPlannedTotal, 1) ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                        <!-- 100% 회색 배경 -->
                                        <!-- 계획된 부분 (파란색) -->
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPlannedTotal ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                        <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                        <?php if ($otherPerformedTotal > 0): ?>
                                            <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPerformedTotal ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 부위별 수행률 요약 (각 부위 100% 기준) -->
                <div class="muscle-summary-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-info mb-0">
                            <i class="fas fa-chart-bar"></i> 부위별 수행률 요약
                        </h6>
                        <!-- 범례 -->
                        <div>
                            <span class="badge bg-success me-2">수행률</span>
                            <span class="badge bg-info">계획률</span>
                        </div>
                    </div>
                    
                    <?php
                    // 각 부위별로 100% 기준으로 계산
                    $partSummary100 = [];
                    foreach ($allParts as $partName) {
                        $plannedPercent = $plannedParts[$partName] ?? 0;
                        $performedPercent = $performedParts[$partName] ?? 0;
                        
                        if ($plannedPercent > 0) {
                            // 각 부위를 100%로 정규화
                            $partSummary100[$partName] = [
                                'planned' => 100, // 항상 100%
                                'performed' => $plannedPercent > 0 ? round(($performedPercent / $plannedPercent) * 100, 1) : 0
                            ];
                        }
                    }
                    
                    // 퍼센트 기준으로 정렬 (계획 기준)
                    uasort($partSummary100, function($a, $b) use ($plannedParts, $partSummary100) {
                        $aKey = array_search($a, $partSummary100);
                        $bKey = array_search($b, $partSummary100);
                        $aPercent = $plannedParts[$aKey] ?? 0;
                        $bPercent = $plannedParts[$bKey] ?? 0;
                        return $bPercent <=> $aPercent;
                    });
                    
                    // 1, 2등과 기타 분리
                    $topParts100 = array_slice($partSummary100, 0, 2, true);
                    $otherParts100 = array_slice($partSummary100, 2, null, true);
                    ?>
                    
                    <div class="row">
                        <!-- 1, 2등 부위 -->
                        <?php foreach ($topParts100 as $partName => $data): ?>
                            <div class="col-md-6 mb-3">
                                <div class="part-summary-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($partName) ?></strong>
                                            <?php if ($data['performed'] > 0): ?>
                                                <span class="badge bg-success ms-2"><?= $data['performed'] ?>%</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info ms-1"><?= $data['planned'] ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                        <!-- 100% 회색 배경 -->
                                        <!-- 계획된 부분 (파란색) - 항상 100% -->
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: 100%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                        <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                        <?php if ($data['performed'] > 0): ?>
                                            <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $data['performed'] ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                        <?php endif; ?>
                                    </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                        
                        <!-- 기타 부위들 -->
                        <?php if (!empty($otherParts100)): ?>
                            <?php 
                            $otherPlannedTotal100 = 0;
                            $otherPerformedTotal100 = 0;
                            foreach ($otherParts100 as $partName => $data) {
                                $otherPlannedTotal100 += $data['planned'];
                                $otherPerformedTotal100 += $data['performed'];
                            }
                            $otherCount = count($otherParts100);
                            $otherPlannedAvg = $otherCount > 0 ? $otherPlannedTotal100 / $otherCount : 0;
                            $otherPerformedAvg = $otherCount > 0 ? $otherPerformedTotal100 / $otherCount : 0;
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="part-summary-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>기타</strong>
                                            <?php if ($otherPerformedAvg > 0): ?>
                                                <span class="badge bg-success ms-2"><?= round($otherPerformedAvg, 1) ?>%</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info ms-1"><?= round($otherPlannedAvg, 1) ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                        <!-- 100% 회색 배경 -->
                                        <!-- 계획된 부분 (파란색) - 항상 100% -->
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: 100%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                        <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                        <?php if ($otherPerformedAvg > 0): ?>
                                            <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPerformedAvg ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 근육 사용률 분석 (상세) -->
                <div class="muscle-analysis-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-primary mb-0">
                            <i class="fas fa-chart-pie"></i> 근육 사용률 분석 (상세)
                        </h6>
                        <!-- 범례 -->
                        <div>
                            <span class="badge bg-success me-2">수행률</span>
                            <span class="badge bg-info">계획률</span>
                        </div>
                    </div>
                    
                    <div class="muscle-analysis">
                        <?php 
                        // 근육별 수행 데이터 수집
                        $musclePerformance = [];
                        foreach ($performanceByMuscle as $muscleCode => $muscleData) {
                            if ($muscleData['percentage'] > 0) {
                                $musclePerformance[$muscleCode] = $muscleData['percentage'];
                            }
                        }
                        
                        // 계획된 근육 데이터와 수행된 근육 데이터 통합
                        $allMuscleCodes = array_unique(array_merge(array_keys($allMuscleAnalysis), array_keys($musclePerformance)));
                        
                        // 퍼센트 기준으로 정렬 (계획 기준)
                        uasort($allMuscleCodes, function($a, $b) use ($allMuscleAnalysis) {
                            $aPercent = $allMuscleAnalysis[$a]['percentage'] ?? 0;
                            $bPercent = $allMuscleAnalysis[$b]['percentage'] ?? 0;
                            return $bPercent <=> $aPercent;
                        });
                        ?>
                        
                        <?php foreach ($allMuscleCodes as $muscleCode): ?>
                            <?php 
                            $muscleData = $allMuscleAnalysis[$muscleCode] ?? null;
                            $plannedPercent = $muscleData['percentage'] ?? 0;
                            $performedPercent = $musclePerformance[$muscleCode] ?? 0;
                            
                            if ($plannedPercent > 0): 
                            ?>
                                <div class="muscle-item mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($muscleData['muscle_name']) ?></strong>
                                            <small class="text-muted">(<?= htmlspecialchars($muscleData['part_name']) ?>)</small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($performedPercent > 0): ?>
                                                <span class="badge bg-success me-1"><?= round($performedPercent, 1) ?>%</span>
                                            <?php endif; ?>
                                            <span class="badge bg-info"><?= round($plannedPercent, 1) ?>%</span>
                                            <br>
                                            <small class="text-muted"><?= number_format($muscleData['weighted_volume']) ?>kg</small>
                                        </div>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px; background-color: #e9ecef; position: relative;">
                                        <!-- 100% 회색 배경 -->
                                        <!-- 계획된 부분 (파란색) -->
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $plannedPercent ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                        <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                        <?php if ($performedPercent > 0): ?>
                                            <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $performedPercent ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>
        </div>
    <?php endif; ?>

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

<?php include 'footer.php'; ?>

<script>
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
    if (confirm('운동을 완료하시겠습니까?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="end_workout">
            <input type="hidden" name="session_id" value="${sessionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// 운동 시간 수정
function updateWorkoutTime(sessionId) {
    const startTime = document.getElementById(`start_time_${sessionId}`).value;
    const endTime = document.getElementById(`end_time_${sessionId}`).value;
    
    if (!startTime && !endTime) {
        alert('시작시간 또는 종료시간을 입력해주세요.');
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
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show alert-message`;
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
                        <button class="btn btn-outline-secondary btn-lg me-3" onclick="adjustExerciseValue('weight', -1)">-</button>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseWeightDisplay">0kg</div>
                            <input type="range" class="form-range" id="exerciseWeightSlider" min="0" max="200" step="1" value="0" oninput="updateExerciseWeightDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0kg</span>
                                <span>200kg</span>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-lg ms-3" onclick="adjustExerciseValue('weight', 1)">+</button>
                    </div>
                </div>
                
                <!-- 횟수 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">횟수</label>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary btn-lg me-3" onclick="adjustExerciseValue('reps', -1)">-</button>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseRepsDisplay">0회</div>
                            <input type="range" class="form-range" id="exerciseRepsSlider" min="0" max="50" step="1" value="0" oninput="updateExerciseRepsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0회</span>
                                <span>50회</span>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-lg ms-3" onclick="adjustExerciseValue('reps', 1)">+</button>
                    </div>
                </div>
                
                <!-- 세트 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">세트</label>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary btn-lg me-3" onclick="adjustExerciseValue('sets', -1)">-</button>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="exerciseSetsDisplay">0세트</div>
                            <input type="range" class="form-range" id="exerciseSetsSlider" min="0" max="20" step="1" value="0" oninput="updateExerciseSetsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0세트</span>
                                <span>20세트</span>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-lg ms-3" onclick="adjustExerciseValue('sets', 1)">+</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
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
                        <button class="btn btn-outline-secondary btn-lg me-3" onclick="adjustValue('weight', -1)">-</button>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="weightDisplay">0kg</div>
                            <input type="range" class="form-range" id="weightSlider" min="0" max="200" step="1" value="0" oninput="updateWeightDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0kg</span>
                                <span>200kg</span>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-lg ms-3" onclick="adjustValue('weight', 1)">+</button>
                    </div>
                </div>
                
                <!-- 횟수 조정 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">횟수</label>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-outline-secondary btn-lg me-3" onclick="adjustValue('reps', -1)">-</button>
                        <div class="flex-grow-1 text-center">
                            <div class="h3 mb-3" id="repsDisplay">0회</div>
                            <input type="range" class="form-range" id="repsSlider" min="0" max="50" step="1" value="0" oninput="updateRepsDisplay(this.value)">
                            <div class="d-flex justify-content-between text-muted small mt-1">
                                <span>0회</span>
                                <span>50회</span>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-lg ms-3" onclick="adjustValue('reps', 1)">+</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="applySetAdjustment()">적용</button>
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
                <div class="exercise-info mb-4 text-center position-relative">
                    <button type="button" class="btn btn-outline-light btn-lg" id="modalExerciseInfo" onclick="openExerciseInfoModal()" style="pointer-events: auto; transition: none !important; background-color: transparent !important; border-color: #fff !important; color: #fff !important;">
                        <i class="fas fa-edit"></i> 20kg × 15회 × 5세트
                    </button>
                    <button type="button" class="btn btn-outline-light btn-lg position-absolute end-0" id="undoSetBtn" onclick="undoLastSet()" style="display: none; top: 50%; transform: translateY(-50%); position: absolute !important; pointer-events: auto; transition: none !important; background-color: transparent !important; border-color: #fff !important; color: #fff !important;">
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
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="finishModalExercise()">
                    <i class="fas fa-flag-checkered"></i> 운동 완료
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModalWithoutSave()">
                    <i class="fas fa-times"></i> 닫기
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

// 모달 열기
function openExerciseModal(exerciseId, exerciseName, weight, reps, sets) {
    modalExerciseId = exerciseId;
    modalTotalSets = sets;
    modalCompletedSets = 0;
    
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
    document.getElementById('modalExerciseInfo').textContent = `${Math.floor(weight)}kg × ${reps}회 × ${sets}세트`;
    
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
        for (let i = 1; i <= sets; i++) {
            const setWrapper = document.createElement('div');
            setWrapper.className = 'set-wrapper';
            
            const setCircle = document.createElement('div');
            setCircle.className = 'set-square';
            setCircle.setAttribute('data-set', i);
            setCircle.setAttribute('data-weight', weight);
            setCircle.setAttribute('data-reps', reps);
            
            // 무게, 횟수 표시
            setCircle.innerHTML = `
                <div class="set-weight">${weight}kg</div>
                <div class="set-divider"></div>
                <div class="set-reps">${reps}회</div>
            `;
            
            // 클릭 이벤트 추가
            setCircle.onclick = () => openSetAdjustModal(i, weight, reps);
            
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
    const colors = ['red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'purple'];
    
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
        const matches = exerciseInfo.match(/(\d+)kg × (\d+)회/);
        weight = matches ? parseInt(matches[1]) : 0;
        reps = matches ? parseInt(matches[2]) : 0;
    }
    
    const setWrapper = document.createElement('div');
    setWrapper.className = 'set-wrapper';
    
    const setCircle = document.createElement('div');
    setCircle.className = 'set-square';
    setCircle.setAttribute('data-set', setNumber);
    setCircle.setAttribute('data-weight', weight);
    setCircle.setAttribute('data-reps', reps);
    
    // 무게, 횟수 표시
    setCircle.innerHTML = `
        <div class="set-weight">${weight}kg</div>
        <div class="set-divider"></div>
        <div class="set-reps">${reps}회</div>
    `;
    
    // 클릭 이벤트 추가
    setCircle.onclick = () => openSetAdjustModal(setNumber, weight, reps);
    
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
        if (confirm('모든 세트를 완료하셨습니다. 운동을 기록하고 종료하시겠습니까?')) {
            // 운동 기록 저장
            saveWorkoutRecord();
            bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
        }
    } else {
        if (confirm(`아직 ${modalTotalSets - modalCompletedSets}세트가 남았습니다. 운동을 기록하고 종료하시겠습니까?`)) {
            // 운동 기록 저장
            saveWorkoutRecord();
            bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
        }
    }
}

// 세션 수정 확인 함수
function confirmEditSession(sessionId, date) {
    if (confirm('⚠️ 주의: 이 운동 세션을 수정하면 현재 세션의 운동 목록이 새로 교체되고, 기존에 기록된 세트별 수행 기록(무게, 횟수, 시간 등)이 삭제됩니다.\n\n정말로 수정하시겠습니까?')) {
        window.location.href = `today.php?edit_session=${sessionId}&date=${date}`;
    }
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
        alert('운동을 입력해주세요.');
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
        alert('올바른 형식으로 운동을 입력해주세요.');
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
                    <h6 class="mb-0">${exerciseName}</h6>
                    <span class="badge bg-primary">${index + 1}</span>
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
        // 검색 결과가 1개인 경우
        const result = searchResults[0];
        html += `
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
        alert('추가할 운동을 입력해주세요.');
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
        alert('추가할 운동을 선택해주세요.');
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`${exercises.length}개의 운동이 성공적으로 추가되었습니다.`, 'success');
            // 페이지 새로고침으로 목록 업데이트
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            console.error('운동 추가 실패:', data.message);
            showMessage('운동 추가에 실패했습니다: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('운동 추가 중 오류가 발생했습니다.', 'error');
    });
}


// 세트 조정 모달 열기
function openSetAdjustModal(setNumber, currentWeight, currentReps) {
    // 슬라이더와 디스플레이 업데이트
    document.getElementById('weightSlider').value = currentWeight;
    document.getElementById('repsSlider').value = currentReps;
    document.getElementById('weightDisplay').textContent = `${currentWeight}kg`;
    document.getElementById('repsDisplay').textContent = `${currentReps}회`;
    
    // 현재 조정 중인 세트 번호와 세션 ID 저장
    window.currentAdjustingSet = setNumber;
    window.currentSessionId = <?= $sessionData['session']['session_id'] ?? 'null' ?>;
    
    const modal = new bootstrap.Modal(document.getElementById('setAdjustModal'));
    modal.show();
}

// 값 조정 함수
function adjustValue(type, change) {
    const slider = document.getElementById(`${type}Slider`);
    const display = document.getElementById(`${type}Display`);
    const currentValue = parseInt(slider.value) || 0;
    const newValue = Math.max(0, currentValue + change);
    
    slider.value = newValue;
    if (type === 'weight') {
        display.textContent = `${newValue}kg`;
    } else {
        display.textContent = `${newValue}회`;
    }
}

// 슬라이더 값 변경 시 디스플레이 업데이트
function updateWeightDisplay(value) {
    document.getElementById('weightDisplay').textContent = `${value}kg`;
}

function updateRepsDisplay(value) {
    document.getElementById('repsDisplay').textContent = `${value}회`;
}

// 세트 조정 적용
function applySetAdjustment() {
    const newWeight = parseFloat(document.getElementById('weightSlider').value) || 0;
    const newReps = parseInt(document.getElementById('repsSlider').value) || 0;
    const setNumber = window.currentAdjustingSet;
    const sessionId = window.currentSessionId;
    
    // 해당 세트의 표시 업데이트
    const setElement = document.querySelector(`[data-set="${setNumber}"]`);
    if (setElement) {
        setElement.setAttribute('data-weight', newWeight);
        setElement.setAttribute('data-reps', newReps);
        
        // 화면에 표시된 값 업데이트
        const weightElement = setElement.querySelector('.set-weight');
        const repsElement = setElement.querySelector('.set-reps');
        
        if (weightElement) weightElement.textContent = `${newWeight}kg`;
        if (repsElement) repsElement.textContent = `${newReps}회`;
        
        // 데이터베이스에 저장
        saveSetAdjustment(sessionId, setNumber, newWeight, newReps);
    }
    
    // 모달 닫기
    bootstrap.Modal.getInstance(document.getElementById('setAdjustModal')).hide();
}

// 세트 조정 데이터베이스 저장
function saveSetAdjustment(sessionId, setNumber, weight, reps) {
    // 현재 운동 ID 가져오기
    const exerciseId = modalExerciseId;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_set_data&wx_id=${exerciseId}&weight=${weight}&reps=${reps}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('세트 데이터가 성공적으로 저장되었습니다.');
        } else {
            console.error('세트 데이터 저장 실패:', data.message);
            showMessage('세트 데이터 저장에 실패했습니다.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('세트 데이터 저장 중 오류가 발생했습니다.', 'error');
    });
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
            alert('운동 기록 저장에 실패했습니다: ' + result.message);
        }
    })
    .catch(error => {
        console.error('운동 기록 저장 오류:', error);
        alert('운동 기록 저장 중 오류가 발생했습니다.');
    });
}

// 기록 없이 닫기
function closeModalWithoutSave() {
    if (confirm('운동 기록 없이 종료하시겠습니까?')) {
        bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
    }
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
            
            // 완료된 세트 표시
            setCircle.innerHTML = `
                <div class="set-weight">${Math.floor(set.weight)}kg</div>
                <div class="set-divider"></div>
                <div class="set-reps">${set.reps}회</div>
            `;
            
            setWrapper.appendChild(setCircle);
            
            // 시간을 별도 div로 추가 (마지막 세트와 동일한 구조)
            const timeDiv = document.createElement('div');
            timeDiv.className = 'set-time';
            timeDiv.id = `set-time-${set.set_no}`;
            timeDiv.textContent = `${Math.floor(set.rest_time)}초`;
            
            setWrapper.appendChild(timeDiv);
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
    // 현재 운동 정보에서 무게, 횟수, 세트 추출
    const exerciseInfo = document.getElementById('modalExerciseInfo').textContent;
    const matches = exerciseInfo.match(/(\d+)kg × (\d+)회 × (\d+)세트/);
    
    let weight = 0, reps = 0, sets = 0;
    if (matches) {
        weight = parseInt(matches[1]);
        reps = parseInt(matches[2]);
        sets = parseInt(matches[3]);
    }
    
    // 슬라이더와 디스플레이 업데이트
    document.getElementById('exerciseWeightSlider').value = weight;
    document.getElementById('exerciseRepsSlider').value = reps;
    document.getElementById('exerciseSetsSlider').value = sets;
    document.getElementById('exerciseWeightDisplay').textContent = `${weight}kg`;
    document.getElementById('exerciseRepsDisplay').textContent = `${reps}회`;
    document.getElementById('exerciseSetsDisplay').textContent = `${sets}세트`;
    
    // modalExerciseId는 이미 openExerciseModal에서 설정됨
    
    const modal = new bootstrap.Modal(document.getElementById('exerciseInfoModal'));
    modal.show();
}

// 운동 정보 값 조정 함수
function adjustExerciseValue(type, change) {
    const slider = document.getElementById(`exercise${type.charAt(0).toUpperCase() + type.slice(1)}Slider`);
    const display = document.getElementById(`exercise${type.charAt(0).toUpperCase() + type.slice(1)}Display`);
    const currentValue = parseInt(slider.value) || 0;
    const newValue = Math.max(0, currentValue + change);
    
    slider.value = newValue;
    if (type === 'weight') {
        display.textContent = `${newValue}kg`;
    } else if (type === 'reps') {
        display.textContent = `${newValue}회`;
    } else if (type === 'sets') {
        display.textContent = `${newValue}세트`;
    }
    
    // 값이 변경되면 UI만 업데이트 (저장은 적용 버튼에서)
}

// 운동 정보 슬라이더 값 변경 시 디스플레이 업데이트
function updateExerciseWeightDisplay(value) {
    document.getElementById('exerciseWeightDisplay').textContent = `${value}kg`;
}

function updateExerciseRepsDisplay(value) {
    document.getElementById('exerciseRepsDisplay').textContent = `${value}회`;
}

function updateExerciseSetsDisplay(value) {
    document.getElementById('exerciseSetsDisplay').textContent = `${value}세트`;
}

// 운동 정보 조정 적용
function applyExerciseInfoAdjustment() {
    const newWeight = parseFloat(document.getElementById('exerciseWeightSlider').value) || 0;
    const newReps = parseInt(document.getElementById('exerciseRepsSlider').value) || 0;
    const newSets = parseInt(document.getElementById('exerciseSetsSlider').value) || 0;
    
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
        showMessage('운동 ID가 설정되지 않았습니다.', 'error');
        return;
    }
    
    // 데이터베이스에 저장
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=update_exercise_info&wx_id=${modalExerciseId}&weight=${newWeight}&reps=${newReps}&sets=${newSets}`
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        console.log('modalExerciseId:', modalExerciseId);
        console.log('newWeight:', newWeight, 'newReps:', newReps, 'newSets:', newSets);
        
        
        if (data.success) {
            // 운동 정보 버튼 텍스트 업데이트
            const exerciseInfoButton = document.getElementById('modalExerciseInfo');
            exerciseInfoButton.innerHTML = `<i class="fas fa-edit"></i> ${newWeight}kg × ${newReps}회 × ${newSets}세트`;
            
            // modalTotalSets 업데이트
            console.log('운동 정보 업데이트 전 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
            modalTotalSets = newSets;
            
            // modalCompletedSets 조정 (새로운 총 세트 수에 맞게 조정)
            if (modalCompletedSets > modalTotalSets) {
                modalCompletedSets = modalTotalSets;
            }
            // 새로운 세트 수가 0이면 완료된 세트 수도 0으로 설정
            else if (modalTotalSets === 0) {
                modalCompletedSets = 0;
            }
            console.log('운동 정보 업데이트 후 - modalTotalSets:', modalTotalSets, 'modalCompletedSets:', modalCompletedSets);
            
            // 기존 세트들 업데이트 (수행된 내용 유지)
            const setsContainer = document.getElementById('modalSetsContainer');
            const existingSets = setsContainer.querySelectorAll('.set-wrapper');
            
            if (newSets > 0) {
                // 기존 세트들 업데이트
                existingSets.forEach((setWrapper, index) => {
                    const setSquare = setWrapper.querySelector('.set-square');
                    if (setSquare && index < newSets) {
                        // 무게와 횟수 업데이트
                        const weightElement = setSquare.querySelector('.set-weight');
                        const repsElement = setSquare.querySelector('.set-reps');
                        
                        if (weightElement) weightElement.textContent = `${newWeight}kg`;
                        if (repsElement) repsElement.textContent = `${newReps}회`;
                        
                        // data 속성 업데이트
                        setSquare.setAttribute('data-weight', newWeight);
                        setSquare.setAttribute('data-reps', newReps);
                    }
                });
                
                // 세트 수가 늘어난 경우 추가 세트 생성
                if (newSets > existingSets.length) {
                    for (let i = existingSets.length + 1; i <= newSets; i++) {
                        addNewSet(i, newWeight, newReps);
                    }
                }
                // 세트 수가 줄어든 경우 초과 세트 제거
                else if (newSets < existingSets.length) {
                    for (let i = newSets; i < existingSets.length; i++) {
                        existingSets[i].remove();
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
            showMessage('운동 정보 저장에 실패했습니다: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('운동 정보 저장 중 오류가 발생했습니다.', 'error');
    });
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

// 원래 운동 리스트의 해당 운동 정보 업데이트
function updateOriginalExerciseList(exerciseId, weight, reps, sets) {
    console.log('updateOriginalExerciseList 호출됨:', exerciseId, weight, reps, sets);
    
    // 저장된 현재 운동 정보 업데이트
    if (window.currentExerciseInfo && window.currentExerciseInfo.id === exerciseId) {
        window.currentExerciseInfo.weight = weight;
        window.currentExerciseInfo.reps = reps;
        window.currentExerciseInfo.sets = sets;
    }
    
    // 모든 운동 링크를 찾아서 해당 운동 정보 업데이트
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

/* 호버 효과 완전 제거 */
#modalExerciseInfo:hover,
#undoSetBtn:hover {
    background-color: transparent !important;
    border-color: #fff !important;
    color: #fff !important;
    transform: none !important;
    box-shadow: none !important;
}
</style>

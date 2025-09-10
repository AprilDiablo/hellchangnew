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
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('데이터가 올바르지 않습니다.');
    }

    // 수정 모드인지 확인
    $editMode = isset($data['editMode']) ? $data['editMode'] : false;
    $editSessionId = isset($data['editSessionId']) ? $data['editSessionId'] : null;
    $editExerciseId = isset($data['editExerciseId']) ? $data['editExerciseId'] : null;
    $workoutDate = isset($data['workoutDate']) ? $data['workoutDate'] : date('Y-m-d');
    $workouts = isset($data['workouts']) ? $data['workouts'] : $data;

    if (!$workouts || !is_array($workouts)) {
        throw new Exception('운동 데이터가 올바르지 않습니다.');
    }

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        if ($editMode) {
            // 수정 모드
            if ($editSessionId) {
                // 운동 세션 수정
                // 기존 운동들 삭제
                $stmt = $pdo->prepare('DELETE FROM m_workout_exercise WHERE session_id = ?');
                $stmt->execute([$editSessionId]);
                
                $sessionId = $editSessionId;
                
            } elseif ($editExerciseId) {
                // 개별 운동 수정 - 삭제하지 않고 업데이트
                $stmt = $pdo->prepare('SELECT session_id FROM m_workout_exercise WHERE wx_id = ?');
                $stmt->execute([$editExerciseId]);
                $sessionId = $stmt->fetchColumn();
                
                if (!$sessionId) {
                    throw new Exception('세션을 찾을 수 없습니다.');
                }
            } else {
                throw new Exception('수정할 세션이나 운동을 지정해주세요.');
            }
        } else {
            // 새로 생성 모드
            $stmt = $pdo->prepare('
                INSERT INTO m_workout_session (user_id, workout_date, note) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE session_id = LAST_INSERT_ID(session_id)
            ');
            $stmt->execute([$user['id'], $workoutDate, '']);
            
            $sessionId = $pdo->lastInsertId();
            if (!$sessionId) {
                // 이미 존재하는 세션 ID 가져오기
                $stmt = $pdo->prepare('SELECT session_id FROM m_workout_session WHERE user_id = ? AND workout_date = ?');
                $stmt->execute([$user['id'], $workoutDate]);
                $sessionId = $stmt->fetchColumn();
            }
        }

        // 운동 기록 저장 (순서대로)
        if ($editMode && $editExerciseId) {
            // 개별 운동 수정 - UPDATE
            $workout = $workouts[0]; // 첫 번째 운동만 사용
            $isTemp = isset($workout['is_temp']) && $workout['is_temp'];
            
            if ($isTemp) {
                // 임시 운동인 경우
                // 1. 임시 운동 마스터에 저장 (중복 체크)
                $stmt = $pdo->prepare('SELECT temp_ex_id FROM m_temp_exercise WHERE user_id = ? AND exercise_name = ?');
                $stmt->execute([$user['id'], $workout['exercise_name']]);
                $tempExId = $stmt->fetchColumn();
                
                if (!$tempExId) {
                    // 존재하지 않으면 새로 생성
                    $stmt = $pdo->prepare('INSERT INTO m_temp_exercise (user_id, exercise_name, status) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], $workout['exercise_name'], 'pending']);
                    $tempExId = $pdo->lastInsertId();
                }
                
                // 2. 기존 운동 업데이트
                $stmt = $pdo->prepare('
                    UPDATE m_workout_exercise 
                    SET ex_id = ?, weight = ?, reps = ?, sets = ?, original_exercise_name = ?, temp_ex_id = ?, is_temp = ?
                    WHERE wx_id = ?
                ');
                
                $stmt->execute([
                    null, // ex_id는 null
                    $workout['weight'],
                    $workout['reps'],
                    $workout['sets'],
                    $workout['exercise_name'],
                    $tempExId,
                    1, // is_temp = 1
                    $editExerciseId
                ]);
            } else {
                // 정식 운동인 경우
                $stmt = $pdo->prepare('
                    UPDATE m_workout_exercise 
                    SET ex_id = ?, weight = ?, reps = ?, sets = ?, original_exercise_name = ?, is_temp = ?
                    WHERE wx_id = ?
                ');
                
                $stmt->execute([
                    $workout['exercise_id'],
                    $workout['weight'],
                    $workout['reps'],
                    $workout['sets'],
                    $workout['exercise_name'],
                    0, // is_temp = 0
                    $editExerciseId
                ]);
            }
        } else {
            // 새로 생성 또는 세션 수정 - INSERT
            foreach ($workouts as $workout) {
                $isTemp = isset($workout['is_temp']) && $workout['is_temp'];
                $orderNo = isset($workout['order_no']) ? $workout['order_no'] : 1;
                
                if ($isTemp) {
                    // 임시 운동인 경우
                    // 1. 임시 운동 마스터에 저장 (중복 체크)
                    $stmt = $pdo->prepare('SELECT temp_ex_id FROM m_temp_exercise WHERE user_id = ? AND exercise_name = ?');
                    $stmt->execute([$user['id'], $workout['exercise_name']]);
                    $tempExId = $stmt->fetchColumn();
                    
                    if (!$tempExId) {
                        // 존재하지 않으면 새로 생성
                        $stmt = $pdo->prepare('INSERT INTO m_temp_exercise (user_id, exercise_name, status) VALUES (?, ?, ?)');
                        $stmt->execute([$user['id'], $workout['exercise_name'], 'pending']);
                        $tempExId = $pdo->lastInsertId();
                    }
                    
                    // 2. 운동 계획에 임시 운동으로 저장
                    $stmt = $pdo->prepare('
                        INSERT INTO m_workout_exercise 
                        (session_id, ex_id, order_no, weight, reps, sets, original_exercise_name, temp_ex_id, is_temp) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    
                    $stmt->execute([
                        $sessionId,
                        null, // ex_id는 null
                        $orderNo,
                        $workout['weight'],
                        $workout['reps'],
                        $workout['sets'],
                        $workout['exercise_name'],
                        $tempExId,
                        1 // is_temp = 1
                    ]);
                } else {
                    // 정식 운동인 경우
                    $stmt = $pdo->prepare('
                        INSERT INTO m_workout_exercise 
                        (session_id, ex_id, order_no, weight, reps, sets, original_exercise_name, is_temp) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    
                    $stmt->execute([
                        $sessionId,
                        $workout['exercise_id'],
                        $orderNo,
                        $workout['weight'],
                        $workout['reps'],
                        $workout['sets'],
                        $workout['exercise_name'],
                        0 // is_temp = 0
                    ]);
                }
            }
        }

        $pdo->commit();
        
        // 운동 날짜 가져오기
        $stmt = $pdo->prepare('SELECT workout_date FROM m_workout_session WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        $workoutDate = $stmt->fetchColumn();
        
        // 리다이렉트 URL 결정
        if ($editMode && $editExerciseId) {
            // 개별 운동 수정 시 해당 세션 상세 페이지로
            $redirectUrl = 'my_workouts_ing.php?session_id=' . $sessionId;
        } else {
            // 세션 수정 또는 새로 생성 시 일별 운동 목록으로
            $redirectUrl = 'my_workouts.php?date=' . $workoutDate;
        }
        
        echo json_encode([
            'success' => true,
            'message' => $editMode ? '운동이 성공적으로 수정되었습니다.' : '운동이 성공적으로 기록되었습니다.',
            'session_id' => $sessionId,
            'redirect_url' => $redirectUrl
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

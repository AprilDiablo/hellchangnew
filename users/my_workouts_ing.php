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
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'start_workout') {
                // 운동 시작시간 저장
                $session_id = $_POST['session_id'];
                
                // 사용자 권한 확인
                $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ? AND user_id = ?");
                $stmt->execute([$session_id, $user['id']]);
                if (!$stmt->fetch()) {
                    throw new Exception("권한이 없습니다.");
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
                    throw new Exception("권한이 없습니다.");
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
                    throw new Exception("권한이 없습니다.");
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
                    throw new Exception("삭제 권한이 없습니다.");
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
                    throw new Exception("삭제 권한이 없습니다.");
                }
                
                // 운동 삭제 (CASCADE로 관련 세트들도 자동 삭제됨)
                $stmt = $pdo->prepare("DELETE FROM m_workout_exercise WHERE wx_id = ?");
                $stmt->execute([$wx_id]);
                
                $message = "운동이 성공적으로 삭제되었습니다.";
            }
        }
        
        $pdo->commit();
        
        // 페이지 새로고침으로 목록 업데이트
        header('Location: my_workouts.php?date=' . $date . '&message=' . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
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
    
    $sessionsWithExercises[] = [
        'session' => $session,
        'exercises' => $exercises,
        'round' => $index + 1, // 1회차, 2회차...
        'session_volume' => $sessionVolume,
        'session_percentage' => $totalDayVolume > 0 ? round(($sessionVolume / $totalDayVolume) * 100, 1) : 0
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
                <a href="today.php?edit_session=<?= $sessionData['session']['session_id'] ?>&date=<?= $date ?>" 
                   class="btn btn-light btn-sm border">
                    <i class="fas fa-edit"></i> 수정
                </a>
                <button type="button" class="btn btn-light btn-sm border text-danger" 
                        onclick="deleteSession(<?= $sessionData['session']['session_id'] ?>)">
                    <i class="fas fa-trash"></i> 삭제
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- 운동 시간 정보 -->
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
            
            <!-- 운동 목록 -->
            <div class="mb-4">
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
                                    title="<?= $exercise['is_completed'] ? '완료됨' : '미완료' ?>">
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

<!-- 운동 수행 모달 -->
<div class="modal fade" id="exerciseModal" tabindex="-1" aria-labelledby="exerciseModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content workout-modal" style="background-color:red">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="exerciseModalLabel">
                    <i class="fas fa-dumbbell"></i> <span id="modalExerciseName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- 운동 정보 -->
                <div class="exercise-info mb-4 text-center">
                    <h4 class="text-primary" id="modalExerciseInfo">20kg × 15회 × 5세트</h4>
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
    
    // 모달 내용 설정
    document.getElementById('modalExerciseName').textContent = exerciseName;
    document.getElementById('modalExerciseInfo').textContent = `${weight}kg × ${reps}회 × ${sets}세트`;
    
    // 세트 컨테이너 초기화
    const setsContainer = document.getElementById('modalSetsContainer');
    setsContainer.innerHTML = '';
    
    // 세트 동그라미들 생성
    for (let i = 1; i <= sets; i++) {
        const setWrapper = document.createElement('div');
        setWrapper.className = 'set-wrapper';
        
        const setCircle = document.createElement('div');
        setCircle.className = 'set-circle';
        setCircle.setAttribute('data-set', i);
        setCircle.innerHTML = i;
        setCircle.onclick = () => completeModalSet(i);
        
        const setTime = document.createElement('div');
        setTime.className = 'set-time';
        setTime.id = `set-time-${i}`;
        setTime.innerHTML = '';
        
        setWrapper.appendChild(setCircle);
        setWrapper.appendChild(setTime);
        setsContainer.appendChild(setWrapper);
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
    // 다음 완료할 세트 찾기
    const nextSet = modalCompletedSets + 1;
    
    if (nextSet <= modalTotalSets) {
        // 세트 완료 처리
        completeModalSet(nextSet);
    }
    
    // 모든 세트에서 타이머 리셋 (마지막 세트도 동일하게)
    resetModalTimer();
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

// 모달 세트 완료 처리
function completeModalSet(setNumber) {
    const setCircle = document.querySelector(`[data-set="${setNumber}"]`);
    const setTime = document.getElementById(`set-time-${setNumber}`);
    
    // 이미 완료된 세트인지 확인
    if (setCircle.classList.contains('completed')) {
        return; // 이미 완료된 세트는 무시
    }
    
    // 현재 타이머 시간 가져오기
    const currentTime = document.getElementById('modalTimer').textContent;
    
    // 세트 완료 표시
    setCircle.classList.add('completed');
    setCircle.onclick = null; // 클릭 비활성화
    
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

// 운동 기록 저장 함수
function saveWorkoutRecord() {
    const setTimes = [];
    
    // 각 세트의 완료 시간 수집
    for (let i = 1; i <= modalCompletedSets; i++) {
        const setTimeElement = document.querySelector(`[data-set="${i}"] + .set-time`);
        if (setTimeElement && setTimeElement.textContent) {
            const timeText = setTimeElement.textContent.replace('초', '');
            setTimes.push(parseInt(timeText) || 0);
        } else {
            setTimes.push(0);
        }
    }
    
    // 세트별 시간을 모두 합해서 총 운동 시간 계산
    const total_time = setTimes.reduce((sum, time) => sum + time, 0);
    
    const data = {
        wx_id: modalExerciseId,
        completed_sets: modalCompletedSets,
        total_sets: modalTotalSets,
        total_time: total_time, // 세트별 시간의 합
        set_times: setTimes
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
        alert('운동 기록 없이 종료되었습니다.');
        bootstrap.Modal.getInstance(document.getElementById('exerciseModal')).hide();
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

.set-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    color: black;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
}

.set-circle:hover {
    background: #dee2e6;
    transform: scale(1.1);
}

.set-circle.completed {
    background: #28a745;
    color: white;
    border-color: #28a745;
    cursor: default;
}

.set-circle.completed:hover {
    transform: none;
    background: #28a745;
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
}

.workout-modal * {
    color: white !important;
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
</style>

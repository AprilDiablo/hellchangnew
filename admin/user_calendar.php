<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 사용자 ID 확인
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if (!$user_id) {
    header("Location: user_workout_history.php");
    exit;
}

// 사용자 정보 가져오기
$stmt = $pdo->prepare('SELECT id, username, email, profile_image, created_at FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: user_workout_history.php");
    exit;
}

// 이번 달 데이터
$currentMonth = date('Y-m');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;

// 해당 월의 운동 세션들
$stmt = $pdo->prepare('
    SELECT 
        ws.session_id,
        ws.workout_date,
        ws.note,
        ws.duration,
        ws.start_time,
        ws.end_time,
        COUNT(we.wx_id) as exercise_count,
        GROUP_CONCAT(
            CONCAT(
                COALESCE(e.name_kr, we.original_exercise_name, "알 수 없음"), 
                " (", CAST(COALESCE(we.weight, 0) AS UNSIGNED), "kg × ", COALESCE(we.reps, 0), "회 × ", COALESCE(we.sets, 0), "세트)"
            )
            ORDER BY we.order_no SEPARATOR ", "
        ) as exercise_summary,
        GROUP_CONCAT(
            CONCAT(
                we.wx_id, ":", 
                COALESCE(e.name_kr, we.original_exercise_name, "알 수 없음"), ":",
                CAST(COALESCE(we.weight, 0) AS UNSIGNED), ":",
                COALESCE(we.reps, 0), ":",
                COALESCE(we.sets, 0), ":",
                COALESCE(we.note, "")
            )
            ORDER BY we.order_no SEPARATOR "|"
        ) as exercise_details
    FROM m_workout_session ws
    LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
    LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
    WHERE ws.user_id = ? 
    AND DATE_FORMAT(ws.workout_date, "%Y-%m") = ?
    GROUP BY ws.session_id, ws.workout_date, ws.note, ws.duration, ws.start_time, ws.end_time
    ORDER BY ws.workout_date DESC
');
$stmt->execute([$user_id, $selectedMonth]);
$dailyWorkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 날짜별로 그룹화
$groupedWorkouts = [];
foreach ($dailyWorkouts as $workout) {
    $date = $workout['workout_date'];
    if (!isset($groupedWorkouts[$date])) {
        $groupedWorkouts[$date] = [];
    }
    $groupedWorkouts[$date][] = $workout;
}
$dailyWorkouts = $groupedWorkouts;

// 해당 월의 부위별 운동 데이터 (users/history.php와 동일)
$stmt = $pdo->prepare('
    SELECT 
        ws.workout_date,
        GROUP_CONCAT(DISTINCT bp.part_name_kr ORDER BY bp.part_name_kr SEPARATOR ", ") as body_parts
    FROM m_workout_session ws
    LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
    LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
    LEFT JOIN m_exercise_muscle_target emt ON e.ex_id = emt.ex_id
    LEFT JOIN m_muscle m ON emt.muscle_code = m.muscle_code
    LEFT JOIN m_body_part bp ON m.part_code = bp.part_code
    WHERE ws.user_id = ? 
    AND DATE_FORMAT(ws.workout_date, "%Y-%m") = ?
    AND bp.part_name_kr IS NOT NULL
    GROUP BY ws.workout_date
    ORDER BY ws.workout_date
');
$stmt->execute([$user_id, $selectedMonth]);
$bodyPartsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dailyBodyParts = [];
foreach ($bodyPartsData as $data) {
    $dailyBodyParts[$data['workout_date']] = explode(', ', $data['body_parts']);
}

// 각 운동의 수행세트 정보 가져오기
$exerciseSetsInfo = [];
if (!empty($dailyWorkouts)) {
    foreach ($dailyWorkouts as $date => $workouts) {
        foreach ($workouts as $workout) {
            // 키 존재 여부 확인
            if (!isset($workout['session_id']) || !isset($workout['workout_date'])) {
                continue;
            }
            
            $sessionId = $workout['session_id'];
            $workoutDate = $workout['workout_date'];
        
        // my_workouts_ing.php와 동일한 방식으로 운동 목록 가져오기
        $stmt = $pdo->prepare('
            SELECT we.wx_id,
               we.weight,
               we.reps,
               we.sets,
               we.note,
               we.is_warmup,
               COALESCE(e.name_kr, te.exercise_name) as exercise_name,
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
        $stmt->execute([$sessionId]);
        $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 각 운동의 완료 상태 확인 (my_workouts_ing.php와 동일)
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

            // 세트 상세 (set_no, weight, reps, rest_time)
            $stmt = $pdo->prepare('
                SELECT set_no,
                       CAST(COALESCE(weight, 0) AS UNSIGNED) AS weight,
                       COALESCE(reps, 0) AS reps,
                       COALESCE(rest_time, 0) AS rest_time
                FROM m_workout_set
                WHERE wx_id = ?
                ORDER BY set_no ASC
            ');
            $stmt->execute([$exercise['wx_id']]);
            $exercise['sets_detail_rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
            $exerciseSetsInfo[$workoutDate][$sessionId] = $exercises;
        }
    }
}

// 달력 생성 (users/history.php와 동일한 방식)
$weeklyCalendar = [];
$firstDayOfMonth = new DateTime($selectedMonth . '-01');
$lastDayOfMonth = clone $firstDayOfMonth;
$lastDayOfMonth->modify('last day of this month');

// 해당 월의 첫 번째 월요일 찾기
$firstMonday = clone $firstDayOfMonth;
if ($firstMonday->format('N') != 1) {
    $firstMonday->modify('last monday');
    if ($firstMonday->format('Y-m') !== $selectedMonth) {
        $firstMonday->modify('next monday');
    }
}

// 해당 월의 마지막 주 일요일 찾기
$lastSunday = clone $lastDayOfMonth;
$lastSunday->modify('sunday this week');
if ($lastSunday->format('Y-m') !== $selectedMonth) {
    $lastSunday->modify('last sunday');
}

$weekNumber = 1;
$currentWeek = clone $firstMonday;

while ($currentWeek <= $lastSunday) {
    $week = [];
    $weekStart = clone $currentWeek;
    
    // 일주일 (월~일) 데이터 생성
    for ($i = 0; $i < 7; $i++) {
        $day = clone $currentWeek;
        $day->modify("+{$i} days");
        $dateStr = $day->format('Y-m-d');
        
        // 해당 날짜의 운동 데이터 찾기
        $workoutData = isset($dailyWorkouts[$dateStr]) ? $dailyWorkouts[$dateStr] : null;
        
        $week[] = [
            'date' => $dateStr,
            'day_name' => $day->format('D'),
            'day_number' => $day->format('j'),
            'is_current_month' => $day->format('Y-m') === $selectedMonth,
            'has_workout' => $workoutData !== null,
            'workout_data' => $workoutData
        ];
    }
    
    $weeklyCalendar[] = [
        'week_number' => $weekNumber,
        'week_start' => $weekStart->format('Y-m-d'),
        'days' => $week
    ];
    
    $weekNumber++;
    $currentWeek->modify('+1 week');
}

// 메시지 처리
$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?>의 운동 이력 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* users/history.php와 동일한 달력 스타일 */
        .week-row {
            margin-bottom: 0px;
        }
        
        .week-days {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 2px 0;
        }
        
        .day-cell {
            width: 80px;
            height: 60px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
            position: relative;
        }
        
        .day-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .day-cell.has-workout {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-color: #28a745;
            color: white;
        }
        
        .day-cell.current-month {
            opacity: 1;
        }
        
        .day-cell.other-month {
            opacity: 0.4;
        }
        
        .day-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .day-number {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .workout-indicator {
            position: absolute;
            bottom: 4px;
            font-size: 12px;
        }
        
        /* 토요일 스타일 (하늘색) */
        .day-cell.saturday {
            color: #17a2b8 !important;
            border-color: #17a2b8 !important;
        }
        
        .day-cell.saturday.has-workout {
            background: linear-gradient(135deg, #17a2b8, #20c997) !important;
            border-color: #17a2b8 !important;
            color: white !important;
        }
        
        /* 일요일 스타일 (빨간색) */
        .day-cell.sunday {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        
        .day-cell.sunday.has-workout {
            background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .exercise-card {
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- 헤더 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-user-clock me-2"></i>개인 운동 이력</h2>
                    <a href="user_workout_history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>사용자 목록으로 돌아가기
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- 프로필 정보 (왼쪽) -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white" style="cursor: pointer;" 
                         data-bs-toggle="collapse" data-bs-target="#userInfoCollapse" 
                         aria-expanded="true" aria-controls="userInfoCollapse">
                        <h5 class="mb-0">
                            <i class="fas fa-user"></i> 사용자 정보
                            <i class="fas fa-chevron-down float-end" id="userInfoChevron"></i>
                        </h5>
                    </div>
                    <div class="collapse show" id="userInfoCollapse">
                        <div class="card-body text-center">
                        <!-- 프로필 사진 -->
                        <div class="mb-3">
                            <div class="profile-image-container" style="width: 120px; height: 120px; margin: 0 auto; border-radius: 50%; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center; border: 3px solid #dee2e6;">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="프로필 사진" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user fa-4x text-muted"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- 사용자 정보 -->
                        <h4 class="mb-2"><?= htmlspecialchars($user['username']) ?></h4>
                        <p class="text-muted mb-3"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <!-- 가입일 -->
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar-plus me-1"></i>
                                가입일: <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                            </small>
                        </div>
                        
                        <!-- 운동 통계 (간단한 요약) -->
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h5 class="text-primary mb-1"><?= count($dailyWorkouts) ?></h5>
                                    <small class="text-muted">이번 달 운동일</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success mb-1"><?= array_sum(array_map('count', $dailyWorkouts)) ?></h5>
                                <small class="text-muted">총 운동 세션</small>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                
                <!-- 달력 카드 -->
                <div class="card mt-3">
                    <div class="card-header">
                        <form method="get" class="d-flex align-items-center justify-content-between">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            <input type="month" class="form-control" id="month" name="month" 
                                   value="<?= $selectedMonth ?>" onchange="this.form.submit()" style="max-width: 200px;">
                            <button type="button" class="btn btn-primary" onclick="goToCurrentMonth()">
                                <i class="fas fa-calendar-day me-1"></i>이번 달
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-2">
                        
                        <!-- 요일 헤더 -->
                        <div class="week-days d-flex mb-2">
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">월</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">화</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">수</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">목</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">금</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">토</div>
                            </div>
                            <div class="day-cell" style="cursor: default; background: #f8f9fa; border-color: #dee2e6;">
                                <div class="day-name fw-bold">일</div>
                            </div>
                        </div>
                        
                        <!-- 달력 -->
                        <div id="weeklyWorkoutCalendar">
                            <?php foreach ($weeklyCalendar as $week): ?>
                            <div class="week-row">
                                <div class="week-days d-flex">
                                    <?php foreach ($week['days'] as $day): ?>
                                    <div class="day-cell <?= $day['is_current_month'] ? 'current-month' : 'other-month' ?> <?= $day['has_workout'] ? 'has-workout' : 'no-workout' ?> <?= strtolower($day['day_name']) ?>" 
                                         onclick="goToDate('<?= $day['date'] ?>')" 
                                         style="cursor: pointer;">
                                        <div class="day-number"><?= $day['day_number'] ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 오른쪽 운동 요약 정보 -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>운동 요약</h5>
                    </div>
                    <div class="card-body" id="workoutSummary">
                        <p class="text-muted text-center">날짜를 선택하면 운동 요약이 표시됩니다.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 페이지 로드 시 메시지 표시
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($message)): ?>
        alert('<?= addslashes($message) ?>');
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        alert('오류: <?= addslashes($error) ?>');
        <?php endif; ?>
    });
    
    // 이번 달로 이동
    function goToCurrentMonth() {
        const currentMonth = new Date().toISOString().slice(0, 7);
        window.location.href = `?user_id=<?= $user_id ?>&month=${currentMonth}`;
    }
    
    // 사용자 정보 카드 접기/펼치기
    document.addEventListener('DOMContentLoaded', function() {
        const userInfoCollapse = document.getElementById('userInfoCollapse');
        const userInfoChevron = document.getElementById('userInfoChevron');
        
        userInfoCollapse.addEventListener('show.bs.collapse', function() {
            userInfoChevron.classList.remove('fa-chevron-right');
            userInfoChevron.classList.add('fa-chevron-down');
        });
        
        userInfoCollapse.addEventListener('hide.bs.collapse', function() {
            userInfoChevron.classList.remove('fa-chevron-down');
            userInfoChevron.classList.add('fa-chevron-right');
        });
    });
    
    // 날짜 클릭 - 운동 리스트 표시
    function goToDate(date) {
        showWorkoutList(date);
    }
    
    // 운동 리스트 표시
    function showWorkoutList(date) {
        // 기존 페이지 데이터에서 해당 날짜 정보 찾기
        const dailyWorkouts = <?= json_encode($dailyWorkouts) ?>;
        const dailyBodyParts = <?= json_encode($dailyBodyParts) ?>;
        
        const workoutData = dailyWorkouts[date];
        
        // 오른쪽 영역에 운동 리스트 표시
        const rightColumn = document.querySelector('.col-md-8 .card .card-body');
        
        if (workoutData && workoutData.length > 0) {
            // 운동 데이터가 있는 경우
            const bodyParts = dailyBodyParts[date] || [];
            displayWorkoutList(workoutData, bodyParts, date, rightColumn);
        } else {
            // 운동 데이터가 없는 경우
            displayNoWorkoutList(date, rightColumn);
        }
    }
    
    // 운동 리스트 표시 (users/history_detail.php 스타일)
    function displayWorkoutList(workoutData, bodyParts, date, container) {
        const exerciseSetsInfo = <?= json_encode($exerciseSetsInfo) ?>;
        
        // 운동 요약 정보 표시
        displayWorkoutSummary(workoutData, exerciseSetsInfo, date);
        
        let html = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-dumbbell me-2"></i>${date} 운동 기록
                </h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearWorkoutList()">
                    <i class="fas fa-times"></i> 닫기
                </button>
            </div>
        `;
        
        // 부위별 운동 정보
        if (bodyParts.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6><i class="fas fa-dumbbell me-2"></i>운동 부위</h6>';
            html += '<div class="d-flex flex-wrap gap-2">';
            bodyParts.forEach(part => {
                html += `<span class="badge bg-info">${part}</span>`;
            });
            html += '</div></div>';
        }
        
        // 각 세션별 상세 정보 (users/history_detail.php 스타일)
        workoutData.forEach((session, index) => {
            html += '<div class="card mb-3">';
            html += '<div class="card-header bg-primary text-white">';
            html += '<div class="d-flex justify-content-between align-items-center">';
            html += `<h6 class="mb-0" style="cursor:pointer" onclick="toggleSessionSets('${date}', ${session.session_id})"><i class="fas fa-play-circle"></i> ${index + 1}회차</h6>`;
            
            if (session.duration) {
                html += '<div class="text-end">';
                html += `<small><i class="fas fa-clock"></i> ${Math.round(session.duration)}분`;
                if (session.start_time) {
                    html += ` (${session.start_time} - ${session.end_time})`;
                }
                html += '</small></div>';
            }
            html += '</div></div>';
            
            html += '<div class="card-body p-2">';
            
            if (session.note) {
                html += `<div class="alert alert-info py-2 mb-3">`;
                html += `<i class="fas fa-sticky-note me-1"></i>${session.note}`;
                html += '</div>';
            }
            
            // 세트 정보가 있는 경우 상세 표시
            const sessionSetsInfo = exerciseSetsInfo[date] && exerciseSetsInfo[date][session.session_id] ? 
                exerciseSetsInfo[date][session.session_id] : [];
            
            if (sessionSetsInfo.length > 0) {
                // users/my_workouts_ing.php와 동일한 본운동 섹션으로 표시
                html += '<div class="mb-4">';
                html += '<div class="card">';
                html += '<div class="card-header bg-info text-white">';
                html += '<h6 class="mb-0"><i class="fas fa-dumbbell"></i> 본운동</h6>';
                html += '</div>';
                html += `<div class="card-body p-0" id="session-sets-${session.session_id}">`;
                
                sessionSetsInfo.forEach((exercise, exIndex) => {
                    // 각 운동마다 몇 세트가 할당되어 있는지 명확히 표시
                    html += '<div class="exercise-row d-flex justify-content-between align-items-center mb-2 p-2 border rounded">';
                    html += '<div class="exercise-name">';
                    html += '<div class="text-decoration-none text-dark">';
                    html += '<strong>' + exercise.exercise_name + '</strong>';
                    html += '<br>';
                    html += '<small class="text-muted">';
                    html += exercise.weight + 'kg × ' + exercise.reps + '회 × <span class="text-primary fw-bold">' + exercise.sets + '세트</span>';
                    html += '</small>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="text-end">';
                    html += '<small class="text-muted">';
                    html += `<span class="text-success">${exercise.completed_sets}</span>/<span class="text-muted">${exercise.sets}</span> 완료`;
                    html += '</small>';
                    html += '</div>';
                    html += '</div>';

                    // 세트 상세 테이블 (history_detail 스타일 요약)
                    if (exercise.sets_detail_rows && exercise.sets_detail_rows.length > 0) {
                        html += '<div class="table-responsive mb-3">';
                        html += '<table class="table table-sm table-bordered align-middle mb-0">';
                        html += '<thead class="table-light">';
                        html += '<tr><th style="width:60px">세트</th><th style="width:80px">무게</th><th style="width:80px">횟수</th><th style="width:100px">휴식(초)</th></tr>';
                        html += '</thead><tbody>';
                        exercise.sets_detail_rows.forEach(row => {
                            html += `<tr><td>${row.set_no}</td><td>${row.weight}</td><td>${row.reps}</td><td>${row.rest_time}</td></tr>`;
                        });
                        html += '</tbody></table></div>';
                    }
                });
                
                html += '</div>';
                html += '</div>';
                html += '</div>';
            } else {
                // 세트 정보가 없는 경우에도 수행한 세트 수 표시
                const exercises = session.exercise_summary.split(', ');
                exercises.forEach((exercise, exIndex) => {
                    // exercise_summary에서 세트 정보 추출 시도
                    const setMatch = exercise.match(/\((\d+)kg × (\d+)회 × (\d+)세트\)/);
                    const weight = setMatch ? setMatch[1] : '0';
                    const reps = setMatch ? setMatch[2] : '0';
                    const totalSets = setMatch ? setMatch[3] : '0';
                    const exerciseName = exercise.replace(/\(\d+kg × \d+회 × \d+세트\)/, '').trim();
                    
                    html += '<div class="exercise-detail-card mb-2 p-3 border rounded">';
                    html += '<div class="d-flex justify-content-between align-items-center">';
                    html += '<div>';
                    html += `<h6 class="text-primary mb-0">`;
                    html += `<span class="badge bg-primary me-2">${exIndex + 1}</span>${exerciseName}`;
                    html += '</h6>';
                    html += '<small class="text-muted">';
                    html += `${weight}kg × ${reps}회 × <span class="text-primary fw-bold">${totalSets}세트</span>`;
                    html += '</small>';
                    html += '</div>';
                    html += '<div class="text-end">';
                    html += '<span class="badge bg-secondary">0/' + totalSets + ' 완료</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
            }
            
            html += '</div></div>';
        });
        
        container.innerHTML = html;
        
    }
    
    // 운동 없음 정보 표시
    function displayNoWorkoutList(date, container) {
        const html = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>${date} 운동 기록
                </h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearWorkoutList()">
                    <i class="fas fa-times"></i> 닫기
                </button>
            </div>
            <div class="text-center py-4">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">운동 기록이 없습니다</h5>
                <p class="text-muted">이 날짜에는 운동을 하지 않았습니다.</p>
            </div>
        `;
        container.innerHTML = html;
    }
    
    // 운동 요약 정보 표시
    function displayWorkoutSummary(workoutData, exerciseSetsInfo, date) {
        const summaryContainer = document.getElementById('workoutSummary');
        
        if (!workoutData || workoutData.length === 0) {
            summaryContainer.innerHTML = '<p class="text-muted text-center">선택한 날짜에 운동 기록이 없습니다.</p>';
            return;
        }
        
        let totalPlannedSets = 0;
        let totalCompletedSets = 0;
        let exerciseList = [];
        
        // 각 세션별로 운동 정보 수집
        workoutData.forEach((session, sessionIndex) => {
            const sessionSetsInfo = exerciseSetsInfo[date] && exerciseSetsInfo[date][session.session_id] ? 
                exerciseSetsInfo[date][session.session_id] : [];
            
            if (sessionSetsInfo.length > 0) {
                sessionSetsInfo.forEach((exercise, exIndex) => {
                    totalPlannedSets += parseInt(exercise.total_sets);
                    totalCompletedSets += parseInt(exercise.completed_sets);
                    
                    exerciseList.push({
                        name: exercise.exercise_name,
                        planned: parseInt(exercise.total_sets),
                        completed: parseInt(exercise.completed_sets),
                        session: sessionIndex + 1
                    });
                });
            }
        });
        
        // 요약 정보 HTML 생성
        let html = `
            <div class="mb-3">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-calendar-day me-2"></i>${date} 운동 요약
                </h6>
                
                <!-- 전체 통계 -->
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h5 class="text-primary mb-1">${totalPlannedSets}</h5>
                                <small class="text-muted">계획 세트</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-light">
                            <div class="card-body py-2">
                                <h5 class="text-success mb-1">${totalCompletedSets}</h5>
                                <small class="text-muted">완료 세트</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 완료율 -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">완료율</small>
                        <small class="text-muted">${totalPlannedSets > 0 ? Math.round((totalCompletedSets / totalPlannedSets) * 100) : 0}%</small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: ${totalPlannedSets > 0 ? (totalCompletedSets / totalPlannedSets) * 100 : 0}%"></div>
                    </div>
                </div>
                
                <!-- 운동별 세트 현황 -->
                <div class="exercise-summary">
                    <h6 class="mb-2">운동별 세트 현황</h6>
        `;
        
        if (exerciseList.length > 0) {
            exerciseList.forEach((exercise, index) => {
                const completionRate = exercise.planned > 0 ? (exercise.completed / exercise.planned) * 100 : 0;
                const isCompleted = exercise.completed >= exercise.planned;
                const badgeClass = isCompleted ? 'bg-success' : 'bg-warning';
                
                html += `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                        <div>
                            <span class="badge bg-primary me-2">${exercise.session}회차</span>
                            <strong>${exercise.name}</strong>
                        </div>
                        <div class="text-end">
                            <span class="badge ${badgeClass} me-2">
                                ${exercise.completed}/${exercise.planned}
                            </span>
                            <small class="text-muted">${Math.round(completionRate)}%</small>
                        </div>
                    </div>
                `;
            });
        } else {
            html += '<p class="text-muted text-center">세트 정보가 없습니다.</p>';
        }
        
        html += '</div></div>';
        summaryContainer.innerHTML = html;
    }
    
    // 운동 리스트 초기화
    function clearWorkoutList() {
        const rightColumn = document.querySelector('.col-md-8 .card .card-body');
        rightColumn.innerHTML = '<p class="text-muted">추가 기능이 여기에 표시됩니다.</p>';
        
        // 요약 정보도 초기화
        const summaryContainer = document.getElementById('workoutSummary');
        summaryContainer.innerHTML = '<p class="text-muted text-center">날짜를 선택하면 운동 요약이 표시됩니다.</p>';
    }

    // 세션 헤더 클릭 시 세트 상세 토글
    function toggleSessionSets(date, sessionId) {
        const el = document.getElementById('session-sets-' + sessionId);
        if (!el) return;
        el.style.display = (el.style.display === 'none') ? '' : 'none';
    }
    
    </script>
</body>
</html>

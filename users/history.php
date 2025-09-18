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
$pageTitle = '운동 기록 전체';
$pageSubtitle = '전체 운동 기록을 확인해보세요';

// 달력 네비게이션 처리
$selectedMonth = $_GET['month'] ?? date('Y-m');
$currentDate = new DateTime($selectedMonth . '-01');
$prevMonth = (clone $currentDate)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $currentDate)->modify('+1 month')->format('Y-m');

// 전체 운동 데이터 수집 (선택된 달) - 실제 수행한 운동만
$pdo = getDB();
$currentMonth = $selectedMonth;

// 실제 수행한 운동 데이터 수집 (m_workout_set 테이블 기준)
$stmt = $pdo->prepare('
    SELECT 
        we.wx_id,
        we.weight,
        we.reps,
        we.sets,
        we.ex_id,
        we.is_temp,
        COALESCE(e.name_kr, te.exercise_name) as name_kr,
        e.name_en, 
        e.equipment,
        te.exercise_name as temp_exercise_name,
        COUNT(ws.set_id) as completed_sets
    FROM m_workout_exercise we
    LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
    LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
    JOIN m_workout_session wss ON we.session_id = wss.session_id
    LEFT JOIN m_workout_set ws ON we.wx_id = ws.wx_id
    WHERE wss.user_id = ? AND DATE_FORMAT(wss.workout_date, "%Y-%m") = ?
    GROUP BY we.wx_id, we.weight, we.reps, we.sets, we.ex_id, we.is_temp, e.name_kr, e.name_en, e.equipment, te.exercise_name
    HAVING completed_sets > 0
    ORDER BY we.order_no ASC
');
$stmt->execute([$user['id'], $currentMonth]);
$allExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 부위별 분석 데이터 수집 (실제 수행 기준)
$bodyPartAnalysis = [
    '가슴' => 0,
    '어깨' => 0,
    '등' => 0,
    '팔' => 0,
    '하체' => 0
];

// 하체 관련 부위들을 정의
$lowerBodyParts = ['엉덩이', '허벅지', '종아리', '발목'];

$totalVolume = 0;

foreach ($allExercises as $exercise) {
    // 실제 수행한 세트 수만큼만 계산
    $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['completed_sets'];
    $totalVolume += $exerciseVolume;
    
    // 해당 운동의 근육 타겟 정보 가져오기
    if ($exercise['ex_id']) {
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
        
        // 각 근육별 가중치 계산 (중복 계산 방지를 위해 정규화)
        $totalWeight = array_sum(array_column($muscleTargets, 'weight'));
        if ($totalWeight > 0) {
            foreach ($muscleTargets as $target) {
                $partName = $target['part_name_kr'];
                $weight = $target['weight'];
                
                // 가중치를 정규화하여 중복 계산 방지
                $normalizedWeight = $weight / $totalWeight;
                $weightedVolume = $exerciseVolume * $normalizedWeight;
                
                // 부위별로 분류
                if (isset($bodyPartAnalysis[$partName])) {
                    // 직접 매칭되는 부위 (가슴, 어깨, 등, 팔)
                    $bodyPartAnalysis[$partName] += $weightedVolume;
                } elseif (in_array($partName, $lowerBodyParts)) {
                    // 하체 관련 부위들을 '하체'로 통합
                    $bodyPartAnalysis['하체'] += $weightedVolume;
                }
            }
        }
    }
}

// 0-10 스케일로 정규화
$maxVolume = max($bodyPartAnalysis);
$normalizedData = [];
foreach ($bodyPartAnalysis as $part => $volume) {
    $normalizedData[$part] = $maxVolume > 0 ? round(($volume / $maxVolume) * 10, 1) : 0;
}

// 운동한 날짜별 데이터 수집 (이번 달) - 세션 기준으로 먼저 조회
$stmt = $pdo->prepare('
    SELECT 
        ws.workout_date,
        COUNT(DISTINCT ws.session_id) as session_count,
        SUM(ws.duration) as total_duration,
        AVG(ws.duration) as avg_duration,
        MIN(ws.start_time) as first_start_time,
        MAX(ws.end_time) as last_end_time
    FROM m_workout_session ws
    WHERE ws.user_id = ? AND DATE_FORMAT(ws.workout_date, "%Y-%m") = ?
    GROUP BY ws.workout_date
    ORDER BY ws.workout_date DESC
');
$stmt->execute([$user['id'], $currentMonth]);
$dailyWorkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);


// workoutDates 배열을 미리 생성 (foreach 루프 전에)
$workoutDates = [];
foreach ($dailyWorkouts as $idx => $workoutDay) {
    $workoutDates[] = $workoutDay['workout_date'];
}

// 각 날짜별로 운동 정보 추가 조회 (수행률 포함)
foreach ($dailyWorkouts as $key => $workoutDay) {
    $exerciseStmt = $pdo->prepare('
        SELECT 
            COUNT(we.wx_id) as exercise_count,
            SUM(we.weight * we.reps * we.sets) as daily_volume,
            SUM(we.sets) as total_planned_sets,
            GROUP_CONCAT(DISTINCT COALESCE(e.name_kr, te.exercise_name) SEPARATOR ",") as exercise_names
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE ws.user_id = ? AND ws.workout_date = ?
    ');
    $exerciseStmt->execute([$user['id'], $workoutDay['workout_date']]);
    $exerciseData = $exerciseStmt->fetch(PDO::FETCH_ASSOC);
    
    // 실제 수행한 세트 수 조회
    $completedStmt = $pdo->prepare('
        SELECT COUNT(ws.set_id) as completed_sets
        FROM m_workout_session wss
        LEFT JOIN m_workout_exercise we ON wss.session_id = we.session_id
        LEFT JOIN m_workout_set ws ON we.wx_id = ws.wx_id
        WHERE wss.user_id = ? AND wss.workout_date = ?
    ');
    $completedStmt->execute([$user['id'], $workoutDay['workout_date']]);
    $completedData = $completedStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalPlannedSets = $exerciseData['total_planned_sets'] ?: 0;
    $completedSets = $completedData['completed_sets'] ?: 0;
    $completionRate = $totalPlannedSets > 0 ? round(($completedSets / $totalPlannedSets) * 100, 1) : 0;
    
    $dailyWorkouts[$key]['exercise_count'] = $exerciseData['exercise_count'] ?: 0;
    $dailyWorkouts[$key]['daily_volume'] = $exerciseData['daily_volume'] ?: 0;
    $dailyWorkouts[$key]['exercise_names'] = $exerciseData['exercise_names'] ?: '';
    $dailyWorkouts[$key]['total_planned_sets'] = $totalPlannedSets;
    $dailyWorkouts[$key]['completed_sets'] = $completedSets;
    $dailyWorkouts[$key]['completion_rate'] = $completionRate;
}


// 실제 운동시간 계산 (시작시간과 종료시간이 있는 경우)
foreach ($dailyWorkouts as $key => $workoutDay) {
    if ($workoutDay['first_start_time'] && $workoutDay['last_end_time']) {
        $start = new DateTime($workoutDay['first_start_time']);
        $end = new DateTime($workoutDay['last_end_time']);
        $diff = $start->diff($end);
        $actualDuration = ($diff->h * 60) + $diff->i; // 분 단위로 변환
        
        // 실제 운동시간이 있으면 duration 대신 사용
        if ($actualDuration > 0) {
            $dailyWorkouts[$key]['total_duration'] = $actualDuration;
        }
    }
}




// 각 날짜별 부위 정보 수집 (볼륨 기준으로 정렬)
$dailyBodyParts = [];
$workoutDates = array_column($dailyWorkouts, 'workout_date');
foreach ($workoutDates as $workoutDate) {
    $stmt = $pdo->prepare('
        SELECT bp.part_name_kr, SUM(we.weight * we.reps * we.sets * emt.weight) as total_volume
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_exercise_muscle_target emt ON e.ex_id = emt.ex_id
        LEFT JOIN m_muscle m ON emt.muscle_code = m.muscle_code
        LEFT JOIN m_body_part bp ON m.part_code = bp.part_code
        JOIN m_workout_session ws ON we.session_id = ws.session_id
        WHERE ws.user_id = ? AND ws.workout_date = ? AND we.ex_id IS NOT NULL
        GROUP BY bp.part_name_kr
        ORDER BY total_volume DESC
    ');
    $stmt->execute([$user['id'], $workoutDate]);
    $bodyParts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $dailyBodyParts[$workoutDate] = $bodyParts;
}


// 월별 통계 계산
$monthlyStats = [];
$totalWorkoutDays = count($dailyWorkouts);






// 실제 운동시간 사용 (시작시간-종료시간이 있으면 그것을, 없으면 duration, 그것도 없으면 추정값)
$totalWorkoutTime = 0;
foreach ($dailyWorkouts as $key => $day) {
    if ($day['total_duration'] !== null && $day['total_duration'] > 0) {
        $totalWorkoutTime += $day['total_duration'];
    } else {
        // 세트당 3분, 운동간 휴식 1분으로 추정
        $estimatedDuration = ($day['exercise_count'] * 3) + (($day['exercise_count'] - 1) * 1);
        $totalWorkoutTime += max($estimatedDuration, 30); // 최소 30분
    }
}

$avgDailyTime = $totalWorkoutDays > 0 ? round($totalWorkoutTime / $totalWorkoutDays, 1) : 0;

// 이번 달이면 오늘까지의 일수, 다른 달이면 해당 달의 전체 일수
$today = new DateTime();
$isCurrentMonth = $selectedMonth === $today->format('Y-m');
$daysInMonth = $isCurrentMonth ? (int)$today->format('j') : (int)$currentDate->format('t');

// 월별로 그룹화
foreach ($dailyWorkouts as $day) {
    $month = date('Y-m', strtotime($day['workout_date']));
    if (!isset($monthlyStats[$month])) {
        $monthlyStats[$month] = [
            'days' => 0,
            'total_time' => 0,
            'sessions' => 0,
            'volume' => 0
        ];
    }
    $monthlyStats[$month]['days']++;
    $monthlyStats[$month]['total_time'] += $day['total_duration'];
    $monthlyStats[$month]['sessions'] += $day['session_count'];
    $monthlyStats[$month]['volume'] += $day['daily_volume'];
}

// 주차별 달력 데이터 생성
$weeklyCalendar = [];
$currentMonth = new DateTime($selectedMonth . '-01');
$lastDayOfMonth = new DateTime($selectedMonth . '-' . $currentMonth->format('t'));

// 해당 월의 첫 번째 주 시작일 찾기 (월요일부터 시작)
$firstMonday = clone $currentMonth;
$firstMonday->modify('monday this week');
if ($firstMonday->format('Y-m') !== $selectedMonth) {
    $firstMonday->modify('next monday');
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
        $workoutData = null;
        foreach ($dailyWorkouts as $workout) {
            if ($workout['workout_date'] === $dateStr) {
                $workoutData = $workout;
                break;
            }
        }
        
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



include 'header.php';
?>

<div class="container-fluid mt-4 px-0">
    <div class="row">
        <div class="col-12">
            <h1 class="text-primary mb-3">
                <i class="fas fa-history"></i> <?= $pageTitle ?>
            </h1>
            <p class="text-muted mb-4"><?= $pageSubtitle ?></p>
        </div>
    </div>
    

    <!-- 주차별 운동 기록 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="text-primary mb-0">
                        <i class="fas fa-calendar-week"></i> 주차별 운동 기록
                    </h5>
                </div>
                <div class="card-body p-2">
                    <div id="weeklyWorkoutCalendar">
                        <?php foreach ($weeklyCalendar as $week): ?>
                        <div class="week-row">
                            <div class="week-header mb-2" style="display: none;">
                                <!-- 주차 표시 제거 - 공간 절약 -->
                            </div>
                            <div class="week-days d-flex">
                                <?php foreach ($week['days'] as $day): ?>
                                <div class="day-cell <?= $day['is_current_month'] ? 'current-month' : 'other-month' ?> <?= $day['has_workout'] ? 'has-workout' : 'no-workout' ?>" 
                                     onclick="goToDate('<?= $day['date'] ?>')" 
                                     style="cursor: pointer;">
                                    <div class="day-name"><?= $day['day_name'] ?></div>
                                    <div class="day-number"><?= $day['day_number'] ?></div>
                                    <?php if ($day['has_workout']): ?>
                                        <div class="workout-indicator">
                                            <i class="fas fa-dumbbell"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 운동 통계 요약 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-calendar-alt text-primary mb-1" style="font-size: 1.2rem;"></i>
                                <span class="fw-bold text-primary" style="font-size: 1.1rem;"><?= $totalWorkoutDays ?>일</span>
                                <small class="text-muted" style="font-size: 0.75rem;">운동한날</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-clock text-success mb-1" style="font-size: 1.2rem;"></i>
                                <span class="fw-bold text-success" style="font-size: 1.1rem;"><?= $avgDailyTime ?>분</span>
                                <small class="text-muted" style="font-size: 0.75rem;">하루평균</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-dumbbell text-info mb-1" style="font-size: 1.2rem;"></i>
                                <span class="fw-bold text-info" style="font-size: 1.1rem;"><?= number_format($totalVolume) ?>kg</span>
                                <small class="text-muted" style="font-size: 0.75rem;">총볼륨</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-chart-line text-warning mb-1" style="font-size: 1.2rem;"></i>
                                <span class="fw-bold text-warning" style="font-size: 1.1rem;"><?= round(($totalWorkoutDays / $daysInMonth) * 100, 1) ?>%</span>
                                <small class="text-muted" style="font-size: 0.75rem;">운동빈도</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 일별 운동 기록 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="text-primary mb-0">
                        <i class="fas fa-calendar-day"></i> 일별 운동 기록
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">날짜</th>
                                    <th class="text-center">요일</th>
                                    <th class="text-center">운동수</th>
                                    <th class="text-center">운동시간</th>
                                    <th class="text-center">상세보기</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailyWorkouts as $day): ?>
                                <tr>
                                    <td class="text-center">
                                        <strong class="text-primary">
                                            <?= date('m/d', strtotime($day['workout_date'])) ?>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?= date('D', strtotime($day['workout_date'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info">
                                            <?= $day['exercise_count'] ?>개
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-success">
                                            <?php 
                                            if ($day['total_duration'] !== null && $day['total_duration'] > 0) {
                                                echo round($day['total_duration'], 1) . '분';
                                            } else {
                                                $estimatedDuration = ($day['exercise_count'] * 3) + (($day['exercise_count'] - 1) * 1);
                                                echo round(max($estimatedDuration, 30), 1) . '분';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <a href="history_detail.php?date=<?= $day['workout_date'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 오각형 그래프 (레이더 차트) -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="text-primary mb-0">
                        <i class="fas fa-chart-area"></i> 부위별 운동 분석 (<?= date('Y년 m월', strtotime($selectedMonth . '-01')) ?>) - 실제 수행 기준
                    </h5>
                    <div class="mt-2">
                        <small class="text-muted">
                            총 볼륨: <?= number_format($totalVolume) ?>kg | 
                            최대 부위: <?= array_search(max($bodyPartAnalysis), $bodyPartAnalysis) ?>
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <canvas id="radarChart" width="400" height="400"></canvas>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-info mb-3">부위별 상세</h6>
                            <?php foreach ($normalizedData as $part => $score): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold"><?= $part ?></span>
                                        <span class="badge bg-primary"><?= $score ?>/10</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?= $score * 10 ?>%" 
                                             aria-valuenow="<?= $score ?>" 
                                             aria-valuemin="0" aria-valuemax="10">
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        볼륨: <?= number_format($bodyPartAnalysis[$part]) ?>kg
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-horizontal {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: center;
    padding: 10px 0;
}

.calendar-day {
    width: 80px;
    height: 100px;
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

.calendar-day:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.calendar-day.workout-day {
    background: linear-gradient(135deg, #28a745, #20c997);
    border-color: #28a745;
    color: white;
}

.calendar-day.today {
    border-color: #007bff;
    border-width: 3px;
    background: #f8f9fa;
}

.calendar-day.workout-day.today {
    background: linear-gradient(135deg, #007bff, #28a745);
    border-color: #007bff;
}

/* 토요일 스타일 (하늘색) */
.calendar-day.saturday {
    color: #17a2b8 !important;
    border-color: #17a2b8 !important;
}

.calendar-day.saturday.workout-day {
    background: linear-gradient(135deg, #17a2b8, #20c997) !important;
    border-color: #17a2b8 !important;
    color: white !important;
}

.calendar-day.saturday.today {
    border-color: #17a2b8 !important;
    border-width: 3px !important;
    background: #e3f2fd !important;
    color: #17a2b8 !important;
}

.calendar-day.saturday.workout-day.today {
    background: linear-gradient(135deg, #17a2b8, #20c997) !important;
    border-color: #17a2b8 !important;
    color: white !important;
}

/* 일요일 스타일 (빨간색) */
.calendar-day.sunday {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.calendar-day.sunday.workout-day {
    background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
    border-color: #dc3545 !important;
    color: white !important;
}

.calendar-day.sunday.today {
    border-color: #dc3545 !important;
    border-width: 3px !important;
    background: #f8d7da !important;
    color: #dc3545 !important;
}

.calendar-day.sunday.workout-day.today {
    background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
    border-color: #dc3545 !important;
    color: white !important;
}

.day-number {
    font-weight: bold;
    font-size: 20px;
    line-height: 1;
}

.day-name {
    font-size: 13px;
    opacity: 0.8;
    margin-top: 2px;
}

.workout-indicator {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 10px;
}

.session-count {
    background: rgba(255,255,255,0.9);
    color: #28a745;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    font-weight: bold;
    margin-left: 2px;
}

.body-parts {
    position: absolute;
    bottom: 2px;
    left: 2px;
    right: 2px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1px;
    flex-wrap: wrap;
}

.body-part-icon {
    font-size: 8px;
    line-height: 1;
    opacity: 0.9;
}

.more-parts {
    font-size: 6px;
    color: rgba(255,255,255,0.8);
    font-weight: bold;
}

.legend-item {
    display: flex;
    align-items: center;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 2px solid #e9ecef;
}

.legend-color.workout-day {
    background: linear-gradient(135deg, #28a745, #20c997);
    border-color: #28a745;
}

.legend-color.today {
    border-color: #007bff;
    border-width: 3px;
    background: #f8f9fa;
}

.legend-color.saturday {
    border-color: #17a2b8;
    background: #e3f2fd;
}

.legend-color.sunday {
    border-color: #dc3545;
    background: #f8d7da;
}

@media (max-width: 768px) {
    .calendar-day {
        width: 60px;
        height: 75px;
    }
    
    .day-number {
        font-size: 16px;
    }
    
    .day-name {
        font-size: 11px;
    }
}

/* 페이지 여백 조정 */
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
}

@media (max-width: 1200px) {
    .container-fluid {
        padding-left: 5px !important;
        padding-right: 5px !important;
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding-left: 3px !important;
        padding-right: 3px !important;
    }
}

/* 주차별 달력 스타일 */
.week-row {
    /* 경계선 제거 - 더 깔끔한 디자인 */
    padding-bottom: 3px;
}

.week-days {
    gap: 2px;
}

.day-cell {
    flex: 1;
    height: 60px;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    position: relative;
}

.day-cell:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.day-cell.current-month {
    background-color: #ffffff;
}

.day-cell.other-month {
    background-color: #f8f9fa;
    opacity: 0.6;
}

.day-cell.has-workout {
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    font-weight: bold;
}

.day-cell.no-workout {
    opacity: 0.5;
}

.day-name {
    font-size: 10px;
    font-weight: bold;
    margin-bottom: 2px;
}

.day-number {
    font-size: 14px;
    font-weight: bold;
}

.workout-indicator {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 8px;
    color: #28a745;
}

@media (max-width: 768px) {
    .day-cell {
        height: 50px;
    }
    
    .day-name {
        font-size: 9px;
    }
    
    .day-number {
        font-size: 12px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// 레이더 차트 생성
const ctx = document.getElementById('radarChart').getContext('2d');
const radarChart = new Chart(ctx, {
    type: 'radar',
    data: {
        labels: ['가슴', '어깨', '등', '팔', '하체'],
        datasets: [{
            label: '운동 강도',
            data: [
                <?= $normalizedData['가슴'] ?>,
                <?= $normalizedData['어깨'] ?>,
                <?= $normalizedData['등'] ?>,
                <?= $normalizedData['팔'] ?>,
                <?= $normalizedData['하체'] ?>
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgba(54, 162, 235, 1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            r: {
                beginAtZero: true,
                min: 0,
                max: 10,
                stepSize: 2,
                ticks: {
                    stepSize: 2,
                    callback: function(value) {
                        return value;
                    }
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                angleLines: {
                    color: 'rgba(0, 0, 0, 0.1)'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed.r + '/10';
                    }
                }
            }
        }
    }
});

// 달력 날짜 클릭 이벤트
document.querySelectorAll('.calendar-day').forEach(day => {
    day.addEventListener('click', function() {
        const date = this.getAttribute('data-date');
        if (date) {
            window.location.href = `history_detail.php?date=${date}`;
        }
    });
    });

// 주차별 달력 날짜 클릭 - 모달로 간략 정보 표시
function goToDate(date) {
    showWorkoutInfoModal(date);
}

// 운동 정보 모달 표시
function showWorkoutInfoModal(date) {
    // 날짜 표시
    document.getElementById('modalDate').textContent = date;
    
    // 기존 페이지 데이터에서 해당 날짜 정보 찾기
    const dailyWorkouts = <?= json_encode($dailyWorkouts) ?>;
    const dailyBodyParts = <?= json_encode($dailyBodyParts) ?>;
    
    const workoutData = dailyWorkouts.find(workout => workout.workout_date === date);
    
    if (workoutData) {
        // 운동 데이터가 있는 경우
        const bodyParts = dailyBodyParts[date] || [];
        displayWorkoutInfo(workoutData, bodyParts, date);
    } else {
        // 운동 데이터가 없는 경우
        displayNoWorkoutInfo();
    }
    
    // 모달 표시
    const modal = new bootstrap.Modal(document.getElementById('workoutInfoModal'));
    modal.show();
}

// 운동 정보 표시
function displayWorkoutInfo(workoutData, bodyParts, date) {
    let content = '';
    
    content = `
        <div class="text-center py-4">
            <div class="mb-3">
                <h4 class="text-primary mb-2">${workoutData.exercise_count}개 운동</h4>
                ${bodyParts && bodyParts.length > 0 ? `
                <div class="d-flex flex-wrap justify-content-center gap-1">
                    ${bodyParts.map(part => `
                        <span class="badge bg-info">${part}</span>
                    `).join('')}
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('modalContent').innerHTML = content;
    
    // 상세보기 버튼 이벤트 설정
    document.getElementById('goToDetailBtn').onclick = function() {
        window.location.href = `history_detail.php?date=${date}`;
    };
    
    // 상세보기 버튼 활성화
    document.getElementById('goToDetailBtn').disabled = false;
    document.getElementById('goToDetailBtn').innerHTML = '<i class="fas fa-eye"></i> 상세보기';
}

// 운동 기록 없음 표시
function displayNoWorkoutInfo() {
    const content = `
        <div class="text-center py-5">
            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">이 날의 운동 기록이 없습니다</h5>
            <p class="text-muted">운동을 기록해보세요!</p>
        </div>
    `;
    document.getElementById('modalContent').innerHTML = content;
    
    // 상세보기 버튼 비활성화
    document.getElementById('goToDetailBtn').disabled = true;
    document.getElementById('goToDetailBtn').innerHTML = '<i class="fas fa-eye"></i> 기록 없음';
}

// 에러 표시
function displayErrorInfo() {
    const content = `
        <div class="text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
            <h5 class="text-warning">정보를 불러올 수 없습니다</h5>
            <p class="text-muted">잠시 후 다시 시도해주세요.</p>
        </div>
    `;
    document.getElementById('modalContent').innerHTML = content;
    
    // 상세보기 버튼 비활성화
    document.getElementById('goToDetailBtn').disabled = true;
    document.getElementById('goToDetailBtn').innerHTML = '<i class="fas fa-eye"></i> 오류';
}
</script>

<style>
/* 테이블 스타일 */
.table th {
    border-top: none;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 1rem 0.75rem;
}

.table td {
    padding: 0.75rem;
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* 배지 스타일 */
.badge {
    font-size: 0.75rem;
    padding: 0.4em 0.6em;
}

/* 반응형 테이블 */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem 0.25rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.3em 0.5em;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<!-- 날짜별 운동 정보 모달 -->
<div class="modal fade" id="workoutInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-dumbbell"></i> <span id="modalDate"></span> 운동 기록
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalContent">
                    <!-- 동적으로 로드될 내용 -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-primary" id="goToDetailBtn">
                    <i class="fas fa-eye"></i> 상세보기
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

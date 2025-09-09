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

// 전체 운동 데이터 수집 (선택된 달)
$pdo = getDB();
$currentMonth = $selectedMonth;
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
    JOIN m_workout_session ws ON we.session_id = ws.session_id
    WHERE ws.user_id = ? AND DATE_FORMAT(ws.workout_date, "%Y-%m") = ?
    ORDER BY we.order_no ASC
');
$stmt->execute([$user['id'], $currentMonth]);
$allExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 부위별 분석 데이터 수집
$bodyPartAnalysis = [
    '가슴' => 0,
    '어깨' => 0,
    '등' => 0,
    '팔' => 0,
    '하체' => 0
];

$totalVolume = 0;

foreach ($allExercises as $exercise) {
    $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
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
        
        // 각 근육별 가중치 계산
        foreach ($muscleTargets as $target) {
            $partName = $target['part_name_kr'];
            $weight = $target['weight'];
            
            // 가중치 적용된 볼륨 계산
            $weightedVolume = $exerciseVolume * $weight;
            
            // 부위별로 분류
            if (isset($bodyPartAnalysis[$partName])) {
                $bodyPartAnalysis[$partName] += $weightedVolume;
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


// 각 날짜별로 운동 정보 추가 조회
foreach ($dailyWorkouts as &$day) {
    $exerciseStmt = $pdo->prepare('
        SELECT 
            COUNT(we.wx_id) as exercise_count,
            SUM(we.weight * we.reps * we.sets) as daily_volume,
            GROUP_CONCAT(DISTINCT COALESCE(e.name_kr, te.exercise_name) SEPARATOR ",") as exercise_names
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE ws.user_id = ? AND ws.workout_date = ?
    ');
    $exerciseStmt->execute([$user['id'], $day['workout_date']]);
    $exerciseData = $exerciseStmt->fetch(PDO::FETCH_ASSOC);
    
    $day['exercise_count'] = $exerciseData['exercise_count'] ?: 0;
    $day['daily_volume'] = $exerciseData['daily_volume'] ?: 0;
    $day['exercise_names'] = $exerciseData['exercise_names'] ?: '';
}


// 실제 운동시간 계산 (시작시간과 종료시간이 있는 경우)
foreach ($dailyWorkouts as &$day) {
    if ($day['first_start_time'] && $day['last_end_time']) {
        $start = new DateTime($day['first_start_time']);
        $end = new DateTime($day['last_end_time']);
        $diff = $start->diff($end);
        $actualDuration = ($diff->h * 60) + $diff->i; // 분 단위로 변환
        
        // 실제 운동시간이 있으면 duration 대신 사용
        if ($actualDuration > 0) {
            $day['total_duration'] = $actualDuration;
        }
    }
}

// 각 날짜별 부위 정보 수집
$dailyBodyParts = [];
foreach ($dailyWorkouts as $day) {
    $workoutDate = $day['workout_date'];
    $stmt = $pdo->prepare('
        SELECT DISTINCT bp.part_name_kr
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_exercise_muscle_target emt ON e.ex_id = emt.ex_id
        LEFT JOIN m_muscle m ON emt.muscle_code = m.muscle_code
        LEFT JOIN m_body_part bp ON m.part_code = bp.part_code
        JOIN m_workout_session ws ON we.session_id = ws.session_id
        WHERE ws.user_id = ? AND ws.workout_date = ? AND we.ex_id IS NOT NULL
        ORDER BY bp.part_name_kr
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
foreach ($dailyWorkouts as $day) {
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
$daysInMonth = $isCurrentMonth ? (int)$today->format('j') : (int)$firstDayOfMonth->format('t');

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

include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="text-primary mb-3">
                <i class="fas fa-history"></i> <?= $pageTitle ?>
            </h1>
            <p class="text-muted mb-4"><?= $pageSubtitle ?></p>
        </div>
    </div>
    
    <!-- 수평 달력 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-primary mb-0">
                            <i class="fas fa-calendar-alt"></i> 운동 달력 (<?= $currentDate->format('Y년 m월') ?>)
                        </h5>
                        <div class="btn-group" role="group">
                            <a href="?month=<?= $prevMonth ?>" class="btn btn-primary btn-sm text-white">
                                <i class="fas fa-chevron-left"></i> 이전달
                            </a>
                            <a href="?month=<?= date('Y-m') ?>" class="btn btn-secondary btn-sm text-white">
                                이번달
                            </a>
                            <a href="?month=<?= $nextMonth ?>" class="btn btn-primary btn-sm text-white">
                                다음달 <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="calendar-horizontal">
                        <?php
                        $today = new DateTime();
                        $firstDayOfMonth = new DateTime($selectedMonth . '-01');
                        $lastDayOfMonth = new DateTime($selectedMonth . '-' . $firstDayOfMonth->format('t'));
                        $workoutDates = array_column($dailyWorkouts, 'workout_date');
                        
                        
                        // 이번 달의 모든 날짜 생성
                        $currentDate = clone $firstDayOfMonth;
                        while ($currentDate <= $lastDayOfMonth) {
                            $dateStr = $currentDate->format('Y-m-d');
                            $isWorkoutDay = in_array($dateStr, $workoutDates);
                            $isToday = $dateStr === $today->format('Y-m-d');
                            
                            // 해당 날짜의 운동 정보 찾기
                            $dayInfo = null;
                            $dayBodyParts = [];
                            foreach ($dailyWorkouts as $day) {
                                if ($day['workout_date'] === $dateStr) {
                                    $dayInfo = $day;
                                    $dayBodyParts = $dailyBodyParts[$dateStr] ?? [];
                                    break;
                                }
                            }
                            ?>
                            <div class="calendar-day <?= $isWorkoutDay ? 'workout-day' : '' ?> <?= $isToday ? 'today' : '' ?>" 
                                 data-date="<?= $dateStr ?>"
                                 title="<?php 
                                    if ($isWorkoutDay) {
                                        // 실제 운동시간 계산 (시작시간-종료시간이 있는 경우)
                                        $actualDuration = $dayInfo['total_duration'];
                                        if ($dayInfo['first_start_time'] && $dayInfo['last_end_time']) {
                                            $start = new DateTime($dayInfo['first_start_time']);
                                            $end = new DateTime($dayInfo['last_end_time']);
                                            $diff = $start->diff($end);
                                            $actualDuration = ($diff->h * 60) + $diff->i; // 분 단위로 변환
                                        }
                                        
                                        if ($actualDuration !== null && $actualDuration > 0) {
                                            echo '운동함 - ' . round($actualDuration, 1) . '분';
                                        } else {
                                            $estimatedDuration = ($dayInfo['exercise_count'] * 3) + (($dayInfo['exercise_count'] - 1) * 1);
                                            echo '운동함 - ' . round(max($estimatedDuration, 30), 1) . '분 (추정)';
                                        }
                                    } else {
                                        echo '운동 안함';
                                    }
                                 ?>">
                                <div class="day-number"><?= $currentDate->format('j') ?></div>
                                <div class="day-name"><?= $currentDate->format('D') ?></div>
                                <?php if ($isWorkoutDay): ?>
                                    <div class="workout-indicator">
                                        <i class="fas fa-dumbbell"></i>
                                        <?php if ($dayInfo['session_count'] > 1): ?>
                                            <span class="session-count"><?= $dayInfo['session_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($dayBodyParts)): ?>
                                        <div class="body-parts">
                                            <?php 
                                            $partIcons = [
                                                '가슴' => '🫁',
                                                '어깨' => '💪',
                                                '등' => '🏋️',
                                                '팔' => '💪',
                                                '하체' => '🦵'
                                            ];
                                            $displayParts = array_slice($dayBodyParts, 0, 3); // 최대 3개만 표시
                                            foreach ($displayParts as $part): 
                                                $icon = $partIcons[$part] ?? '💪';
                                            ?>
                                                <span class="body-part-icon" title="<?= $part ?>"><?= $icon ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($dayBodyParts) > 3): ?>
                                                <span class="more-parts">+<?= count($dayBodyParts) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php
                            $currentDate->modify('+1 day');
                        }
                        ?>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="legend-item me-4">
                                <div class="legend-color workout-day"></div>
                                <span class="ms-2">운동한 날</span>
                            </div>
                            <div class="legend-item me-4">
                                <div class="legend-color today"></div>
                                <span class="ms-2">오늘</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color"></div>
                                <span class="ms-2">운동 안한 날</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 운동 통계 요약 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <h4><?= $totalWorkoutDays ?>일</h4>
                    <small>운동한 날</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h4><?= $avgDailyTime ?>분</h4>
                    <small>하루 평균</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-dumbbell fa-2x mb-2"></i>
                    <h4><?= number_format($totalVolume) ?>kg</h4>
                    <small>총 볼륨</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <h4><?= round(($totalWorkoutDays / $daysInMonth) * 100, 1) ?>%</h4>
                    <small>운동 빈도</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 월별 운동 현황 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="text-primary mb-0">
                        <i class="fas fa-calendar-week"></i> 월별 운동 현황
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($monthlyStats as $month => $stats): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body">
                                        <h6 class="card-title text-primary"><?= date('Y년 m월', strtotime($month . '-01')) ?></h6>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="text-muted small">운동일</div>
                                                <div class="h5 text-primary"><?= $stats['days'] ?>일</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">총 시간</div>
                                                <div class="h5 text-success"><?= round($stats['total_time'], 1) ?>분</div>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-6">
                                                <div class="text-muted small">세션</div>
                                                <div class="h6"><?= $stats['sessions'] ?>회</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-muted small">볼륨</div>
                                                <div class="h6"><?= number_format($stats['volume']) ?>kg</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
                        <i class="fas fa-list-alt"></i> 일별 운동 기록
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>날짜</th>
                                    <th>세션</th>
                                    <th>운동시간</th>
                                    <th>운동수</th>
                                    <th>볼륨</th>
                                    <th>상세</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailyWorkouts as $day): ?>
                                    <tr>
                                        <td>
                                            <strong><?= date('m/d (D)', strtotime($day['workout_date'])) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $day['session_count'] ?>회</span>
                                        </td>
                                        <td>
                                            <i class="fas fa-clock text-success"></i>
                                            <strong>
                                                <?php 
                                                if ($day['total_duration'] !== null && $day['total_duration'] > 0) {
                                                    echo round($day['total_duration'], 1) . '분';
                                                } else {
                                                    $estimatedDuration = ($day['exercise_count'] * 3) + (($day['exercise_count'] - 1) * 1);
                                                    echo round(max($estimatedDuration, 30), 1) . '분 (추정)';
                                                }
                                                ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $day['exercise_count'] ?>개</span>
                                        </td>
                                        <td>
                                            <strong><?= number_format($day['daily_volume']) ?>kg</strong>
                                        </td>
                                        <td>
                                            <a href="my_workouts.php?date=<?= $day['workout_date'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> 보기
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
                        <i class="fas fa-chart-area"></i> 부위별 운동 분석 (<?= $currentDate->format('Y년 m월') ?>)
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
    gap: 8px;
    justify-content: center;
    padding: 20px 0;
}

.calendar-day {
    width: 50px;
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

.day-number {
    font-weight: bold;
    font-size: 14px;
    line-height: 1;
}

.day-name {
    font-size: 10px;
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

@media (max-width: 768px) {
    .calendar-day {
        width: 40px;
        height: 50px;
    }
    
    .day-number {
        font-size: 12px;
    }
    
    .day-name {
        font-size: 9px;
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
            window.location.href = `my_workouts.php?date=${date}`;
        }
    });
});
</script>

<?php include 'footer.php'; ?>

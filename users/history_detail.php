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
$pageTitle = '운동 기록 상세';
$pageSubtitle = '운동 수행 시간과 상세 분석';

// 날짜 파라미터 (기본값: 오늘)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$pdo = getDB();

// 해당 날짜의 모든 운동 세션 가져오기
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

// 전체 운동 데이터 수집
$allExercises = [];
$totalDayVolume = 0;
$totalWorkoutTime = 0;
$totalSets = 0;
$completedSets = 0;

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
    
    // 각 운동의 완료 상태 및 시간 정보 확인
    foreach ($exercises as &$exercise) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_sets, 
                   MAX(set_no) as max_set_no,
                   SUM(rest_time) as total_rest_time,
                   AVG(rest_time) as avg_rest_time,
                   MIN(completed_at) as first_set_time,
                   MAX(completed_at) as last_set_time
            FROM m_workout_set 
            WHERE wx_id = ?
        ");
        $stmt->execute([$exercise['wx_id']]);
        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $exercise['completed_sets'] = $completion['completed_sets'] ?? 0;
        $exercise['is_completed'] = ($exercise['completed_sets'] >= $exercise['sets']);
        $exercise['total_rest_time'] = $completion['total_rest_time'] ?? 0;
        $exercise['avg_rest_time'] = $completion['avg_rest_time'] ?? 0;
        $exercise['first_set_time'] = $completion['first_set_time'];
        $exercise['last_set_time'] = $completion['last_set_time'];
        
        // 전체 통계에 추가
        $totalSets += $exercise['sets'];
        $completedSets += $exercise['completed_sets'];
        $totalWorkoutTime += $exercise['total_rest_time'];
    }
    
    foreach ($exercises as $exercise) {
        $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
        $totalDayVolume += $exerciseVolume;
        $allExercises[] = $exercise;
    }
}

// 세션별 상세 정보 수집
$sessionsWithDetails = [];
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
    
    // 각 운동의 세트별 상세 정보 가져오기
    foreach ($exercises as &$exercise) {
        $stmt = $pdo->prepare("
            SELECT set_no, weight, reps, completed_at, rest_time, total_time
            FROM m_workout_set 
            WHERE wx_id = ?
            ORDER BY set_no ASC
        ");
        $stmt->execute([$exercise['wx_id']]);
        $exercise['sets_detail'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 운동별 통계 계산
        $exercise['total_rest_time'] = array_sum(array_column($exercise['sets_detail'], 'rest_time'));
        $exercise['avg_rest_time'] = count($exercise['sets_detail']) > 0 ? 
            round($exercise['total_rest_time'] / count($exercise['sets_detail']), 1) : 0;
        $exercise['completed_sets'] = count($exercise['sets_detail']);
        $exercise['is_completed'] = ($exercise['completed_sets'] >= $exercise['sets']);
    }
    
    // 세션별 볼륨 계산
    $sessionVolume = 0;
    $sessionTime = 0;
    foreach ($exercises as $exercise) {
        $sessionVolume += $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
        $sessionTime += $exercise['total_rest_time'];
    }
    
    $sessionsWithDetails[] = [
        'session' => $session,
        'exercises' => $exercises,
        'round' => $index + 1,
        'session_volume' => $sessionVolume,
        'session_time' => $sessionTime,
        'session_percentage' => $totalDayVolume > 0 ? round(($sessionVolume / $totalDayVolume) * 100, 1) : 0
    ];
}

// 날짜 포맷팅
$formattedDate = date('Y년 m월 d일', strtotime($date));
$dayOfWeek = ['일', '월', '화', '수', '목', '금', '토'][date('w', strtotime($date))];

// 헤더 포함
include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="text-primary mb-1">
                        <i class="fas fa-chart-line"></i> <?= $pageTitle ?>
                    </h1>
                    <p class="text-muted mb-0"><?= $pageSubtitle ?></p>
                </div>
                <a href="history.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> 뒤로가기
                </a>
            </div>
        </div>
    </div>
    
    <!-- 날짜 표시 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-primary mb-1"><?= $formattedDate ?> (<?= $dayOfWeek ?>)</h3>
                    <?php
                    // 전체 운동의 시작/종료 시간 계산
                    $firstStartTime = null;
                    $lastEndTime = null;
                    $totalSessionTime = 0;
                    
                    foreach ($workoutSessions as $session) {
                        if ($session['start_time']) {
                            if (!$firstStartTime || $session['start_time'] < $firstStartTime) {
                                $firstStartTime = $session['start_time'];
                            }
                        }
                        if ($session['end_time']) {
                            if (!$lastEndTime || $session['end_time'] > $lastEndTime) {
                                $lastEndTime = $session['end_time'];
                            }
                        }
                        
                        // 세션별 실제 운동 시간 계산
                        if ($session['start_time'] && $session['end_time']) {
                            $start = new DateTime($session['start_time']);
                            $end = new DateTime($session['end_time']);
                            $totalSessionTime += $end->getTimestamp() - $start->getTimestamp();
                        }
                    }
                    ?>
                    <?php if ($firstStartTime && $lastEndTime): ?>
                        <div class="mt-3">
                            <h5 class="text-secondary mb-1">
                                <i class="fas fa-clock"></i> 
                                <?= date('H:i', strtotime($firstStartTime)) ?> - <?= date('H:i', strtotime($lastEndTime)) ?>
                            </h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php if (!empty($sessionsWithDetails)): ?>
    <!-- 전체 요약 통계 -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-primary">
                        <i class="fas fa-dumbbell"></i> 총 운동
                    </h5>
                    <h3 class="text-primary"><?= count($allExercises) ?>개</h3>
                    <p class="text-muted mb-0">운동 종류</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-success">
                        <i class="fas fa-check-circle"></i> 완료 세트
                    </h5>
                    <h3 class="text-success"><?= $completedSets ?>/<?= $totalSets ?></h3>
                    <p class="text-muted mb-0">세트 완료율</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-info">
                        <i class="fas fa-clock"></i> 총 운동시간
                    </h5>
                    <h3 class="text-info"><?= round($totalWorkoutTime / 60, 1) ?>분</h3>
                    <p class="text-muted mb-0">세트별 시간 합계</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title text-warning">
                        <i class="fas fa-weight-hanging"></i> 총 볼륨
                    </h5>
                    <h3 class="text-warning"><?= number_format($totalDayVolume) ?>kg</h3>
                    <p class="text-muted mb-0">무게 × 반복 × 세트</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 각 세션별 상세 정보 -->
    <?php foreach ($sessionsWithDetails as $sessionData): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-play-circle"></i> <?= $sessionData['round'] ?>회차
                </h5>
                <div class="text-end">
                    <small>
                        <i class="fas fa-clock"></i> <?= round($sessionData['session_time'] / 60, 1) ?>분 |
                        <i class="fas fa-weight-hanging"></i> <?= number_format($sessionData['session_volume']) ?>kg
                    </small>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- 운동 목록 -->
            <?php foreach ($sessionData['exercises'] as $exercise): ?>
            <div class="exercise-detail-card mb-3 p-3 border rounded">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-2">
                            <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($exercise['name_kr']) ?>
                            <?php if ($exercise['is_temp']): ?>
                                <span class="badge bg-warning text-dark ms-1">임시</span>
                            <?php endif; ?>
                        </h6>
                        <p class="text-muted mb-1">
                            <i class="fas fa-weight-hanging"></i> <?= number_format($exercise['weight'], 0) ?>kg × 
                            <i class="fas fa-redo"></i> <?= $exercise['reps'] ?>회 × 
                            <i class="fas fa-layer-group"></i> <?= $exercise['sets'] ?>세트
                        </p>
                        <?php if ($exercise['note']): ?>
                            <p class="text-muted mb-0"><small><em><?= htmlspecialchars($exercise['note']) ?></em></small></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-success mb-1"><?= $exercise['completed_sets'] ?>/<?= $exercise['sets'] ?></h6>
                                    <small class="text-muted">완료 세트</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-info mb-1"><?= round($exercise['total_rest_time'] / 60, 1) ?>분</h6>
                                    <small class="text-muted">총 시간</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-item">
                                    <h6 class="text-warning mb-1"><?= $exercise['avg_rest_time'] ?>초</h6>
                                    <small class="text-muted">평균 세트</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 세트별 상세 기록 -->
                <?php if (!empty($exercise['sets_detail'])): ?>
                <div class="mt-3">
                    <h6 class="text-secondary mb-2">
                        <i class="fas fa-list-ol"></i> 세트별 상세 기록
                    </h6>
                    <div class="row">
                        <?php foreach ($exercise['sets_detail'] as $set): ?>
                        <div class="col-md-2 col-sm-3 col-4 mb-2">
                            <div class="set-detail-card p-2 text-center border rounded">
                                <div class="set-number text-primary fw-bold"><?= $set['set_no'] ?>세트</div>
                                <div class="set-info text-muted small">
                                    <?= number_format($set['weight'], 0) ?>kg × <?= $set['reps'] ?>회
                                </div>
                                <div class="set-time text-success small">
                                    <i class="fas fa-clock"></i> <?= $set['rest_time'] ?>초
                                </div>
                                <?php if ($set['completed_at']): ?>
                                <div class="set-completed text-muted small">
                                    <?= date('H:i', strtotime($set['completed_at'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
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
            <a href="today.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 운동 기록하기
            </a>
        </div>
    </div>
<?php endif; ?>

</div>

<?php include 'footer.php'; ?>

<style>
.exercise-detail-card {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
}

.stat-item {
    padding: 5px;
}

.set-detail-card {
    background-color: #ffffff;
    transition: all 0.2s ease;
}

.set-detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.set-number {
    font-size: 0.9rem;
}

.set-info {
    font-size: 0.8rem;
}

.set-time {
    font-size: 0.8rem;
    font-weight: bold;
}

.set-completed {
    font-size: 0.7rem;
}
</style>

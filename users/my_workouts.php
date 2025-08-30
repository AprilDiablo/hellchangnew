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

// 해당 날짜의 모든 운동 세션 가져오기 (회차별로)
$pdo = getDB();
$stmt = $pdo->prepare('
    SELECT ws.*, 
           COUNT(we.wx_id) as exercise_count,
           SUM(we.weight * we.reps * we.sets) as total_volume
    FROM m_workout_session ws
    LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
    WHERE ws.user_id = ? AND ws.workout_date = ?
    GROUP BY ws.session_id
    ORDER BY ws.session_id ASC
');
$stmt->execute([$user['id'], $date]);
$workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 각 세션별로 운동 상세 정보 가져오기
$sessionsWithExercises = [];
foreach ($workoutSessions as $index => $session) {
    $stmt = $pdo->prepare('
        SELECT we.*, e.name_kr, e.name_en, e.equipment
        FROM m_workout_exercise we
        JOIN m_exercise e ON we.ex_id = e.ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$session['session_id']]);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sessionsWithExercises[] = [
        'session' => $session,
        'exercises' => $exercises,
        'round' => $index + 1 // 1회차, 2회차...
    ];
}

// 날짜 포맷팅
$formattedDate = date('Y년 m월 d일', strtotime($date));
$dayOfWeek = ['일', '월', '화', '수', '목', '금', '토'][date('w', strtotime($date))];

// 헤더 포함
include 'header.php';
?>

<!-- 날짜 네비게이션 -->
<div class="date-navigation">
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="btn btn-outline-primary btn-custom">
        <i class="fas fa-chevron-left"></i> 어제
    </a>
    <div class="date-display">
        <input type="date" id="datePicker" value="<?= $date ?>" onchange="changeDate(this.value)" class="form-control">
    </div>
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="btn btn-outline-primary btn-custom">
        내일 <i class="fas fa-chevron-right"></i>
    </a>
</div>

<?php if (!empty($sessionsWithExercises)): ?>
    <!-- 각 세션별 운동 목록 -->
    <?php foreach ($sessionsWithExercises as $sessionData): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h5><i class="fas fa-list-check"></i> <?= $sessionData['round'] ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- 오른쪽: 운동 목록 -->
                <div class="col-12">
                    <?php foreach ($sessionData['exercises'] as $exercise): ?>
                        <div class="exercise-row">
                            <div class="exercise-name">
                                <?= htmlspecialchars($exercise['name_kr']) ?>
                                <?= $exercise['weight'] ?>kg <?= $exercise['reps'] ?>회 <?= $exercise['sets'] ?>세트
                            </div>
                        </div>
                    <?php endforeach; ?>
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
            <a href="today.php" class="btn btn-primary btn-custom">
                <i class="fas fa-plus"></i> 운동 기록하기
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<script>
// 날짜 변경 함수
function changeDate(dateString) {
    window.location.href = '?date=' + dateString;
}
</script>

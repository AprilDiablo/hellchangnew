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

// 세션 ID 파라미터 확인
if (!isset($_GET['session_id'])) {
    header('Location: my_workouts.php');
    exit;
}

$sessionId = $_GET['session_id'];

// 페이지 제목과 부제목 설정
$pageTitle = '운동 수행';
$pageSubtitle = '운동을 시작해보세요';

// 해당 세션의 운동 정보 가져오기
$pdo = getDB();

// 세션 정보 확인
$stmt = $pdo->prepare('
    SELECT ws.*, u.name as user_name
    FROM m_workout_session ws
    JOIN users u ON ws.user_id = u.id
    WHERE ws.session_id = ? AND ws.user_id = ?
');
$stmt->execute([$sessionId, $user['id']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header('Location: my_workouts.php');
    exit;
}

// 운동 목록 가져오기
$stmt = $pdo->prepare('
    SELECT we.*, e.name_kr, e.name_en, e.equipment
    FROM m_workout_exercise we
    JOIN m_exercise e ON we.ex_id = e.ex_id
    WHERE we.session_id = ?
    ORDER BY we.order_no ASC
');
$stmt->execute([$sessionId]);
$exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 헤더 포함
include 'header.php';
?>

<div class="workout-session-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="text-primary mb-1">
                <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($session['user_name']) ?>님의 운동
            </h2>
            <p class="text-muted mb-0">
                <i class="fas fa-calendar"></i> <?= date('Y년 m월 d일', strtotime($session['workout_date'])) ?>
            </p>
        </div>
        <div class="text-end">
            <div class="workout-timer">
                <div class="timer-display h3 text-success mb-0" id="timer">00:00</div>
                <div class="timer-controls">
                    <button class="btn btn-success btn-sm" id="startBtn" onclick="startTimer()">
                        <i class="fas fa-play"></i> 시작
                    </button>
                    <button class="btn btn-warning btn-sm" id="pauseBtn" onclick="pauseTimer()" style="display: none;">
                        <i class="fas fa-pause"></i> 일시정지
                    </button>
                    <button class="btn btn-danger btn-sm" id="stopBtn" onclick="stopTimer()" style="display: none;">
                        <i class="fas fa-stop"></i> 정지
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($exercises)): ?>
    <div class="workout-exercises">
        <?php foreach ($exercises as $index => $exercise): ?>
            <div class="card mb-3 exercise-card" data-exercise-id="<?= $exercise['wx_id'] ?>">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <span class="badge bg-primary me-2"><?= $index + 1 ?></span>
                                <?= htmlspecialchars($exercise['name_kr']) ?>
                            </h5>
                            <small class="text-muted">
                                <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($exercise['equipment']) ?>
                            </small>
                        </div>
                        <div class="exercise-status">
                            <span class="badge bg-secondary" id="status-<?= $exercise['wx_id'] ?>">대기중</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-info">목표 세트</h6>
                            <div class="target-sets">
                                <?php for ($i = 1; $i <= $exercise['sets']; $i++): ?>
                                    <div class="set-item mb-2" data-set="<?= $i ?>">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-light text-dark me-2"><?= $i ?>세트</span>
                                            <span class="me-3"><?= $exercise['weight'] ?>kg × <?= $exercise['reps'] ?>회</span>
                                            <button class="btn btn-outline-success btn-sm" onclick="completeSet(<?= $exercise['wx_id'] ?>, <?= $i ?>)">
                                                <i class="fas fa-check"></i> 완료
                                            </button>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">실제 기록</h6>
                            <div class="actual-sets" id="actual-<?= $exercise['wx_id'] ?>">
                                <p class="text-muted">아직 기록이 없습니다.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="workout-controls text-center mt-4">
        <button class="btn btn-primary btn-lg" onclick="finishWorkout()">
            <i class="fas fa-flag-checkered"></i> 운동 완료
        </button>
        <a href="my_workouts.php" class="btn btn-secondary btn-lg ms-2">
            <i class="fas fa-arrow-left"></i> 목록으로
        </a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center">
            <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
            <h4 class="text-muted">운동 데이터가 없습니다</h4>
            <a href="my_workouts.php" class="btn btn-primary">목록으로 돌아가기</a>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<style>
.workout-session-header {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}

.workout-timer {
    text-align: center;
}

.timer-display {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.exercise-card {
    transition: all 0.3s ease;
}

.exercise-card.completed {
    border-left: 5px solid #28a745;
    background-color: #f8fff9;
}

.exercise-card.in-progress {
    border-left: 5px solid #ffc107;
    background-color: #fffdf5;
}

.set-item {
    padding: 0.5rem;
    border: 1px solid #e9ecef;
    border-radius: 0.25rem;
    background: #f8f9fa;
}

.set-item.completed {
    background: #d4edda;
    border-color: #c3e6cb;
}

.actual-sets .set-record {
    padding: 0.25rem 0.5rem;
    background: #e7f3ff;
    border-radius: 0.25rem;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
}
</style>

<script>
let timerInterval;
let startTime;
let elapsedTime = 0;
let isRunning = false;

// 타이머 관련 함수들
function startTimer() {
    if (!isRunning) {
        startTime = Date.now() - elapsedTime;
        timerInterval = setInterval(updateTimer, 1000);
        isRunning = true;
        
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('pauseBtn').style.display = 'inline-block';
        document.getElementById('stopBtn').style.display = 'inline-block';
    }
}

function pauseTimer() {
    if (isRunning) {
        clearInterval(timerInterval);
        isRunning = false;
        
        document.getElementById('startBtn').style.display = 'inline-block';
        document.getElementById('pauseBtn').style.display = 'none';
    }
}

function stopTimer() {
    clearInterval(timerInterval);
    isRunning = false;
    elapsedTime = 0;
    
    document.getElementById('timer').textContent = '00:00';
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display = 'none';
}

function updateTimer() {
    elapsedTime = Date.now() - startTime;
    const minutes = Math.floor(elapsedTime / 60000);
    const seconds = Math.floor((elapsedTime % 60000) / 1000);
    
    document.getElementById('timer').textContent = 
        String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
}

// 세트 완료 처리
function completeSet(exerciseId, setNumber) {
    const setItem = document.querySelector(`[data-exercise-id="${exerciseId}"] [data-set="${setNumber}"]`);
    const actualSetsDiv = document.getElementById(`actual-${exerciseId}`);
    
    // 세트 완료 표시
    setItem.classList.add('completed');
    setItem.querySelector('button').innerHTML = '<i class="fas fa-check"></i> 완료됨';
    setItem.querySelector('button').disabled = true;
    
    // 실제 기록 추가
    if (actualSetsDiv.querySelector('.text-muted')) {
        actualSetsDiv.innerHTML = '';
    }
    
    const setRecord = document.createElement('div');
    setRecord.className = 'set-record';
    setRecord.innerHTML = `${setNumber}세트: 완료`;
    actualSetsDiv.appendChild(setRecord);
    
    // 운동 상태 업데이트
    updateExerciseStatus(exerciseId);
}

// 운동 상태 업데이트
function updateExerciseStatus(exerciseId) {
    const exerciseCard = document.querySelector(`[data-exercise-id="${exerciseId}"]`);
    const totalSets = exerciseCard.querySelectorAll('.set-item').length;
    const completedSets = exerciseCard.querySelectorAll('.set-item.completed').length;
    const statusBadge = document.getElementById(`status-${exerciseId}`);
    
    if (completedSets === 0) {
        statusBadge.textContent = '대기중';
        statusBadge.className = 'badge bg-secondary';
        exerciseCard.className = 'card mb-3 exercise-card';
    } else if (completedSets === totalSets) {
        statusBadge.textContent = '완료';
        statusBadge.className = 'badge bg-success';
        exerciseCard.className = 'card mb-3 exercise-card completed';
    } else {
        statusBadge.textContent = '진행중';
        statusBadge.className = 'badge bg-warning';
        exerciseCard.className = 'card mb-3 exercise-card in-progress';
    }
}

// 운동 완료
function finishWorkout() {
    const totalExercises = document.querySelectorAll('.exercise-card').length;
    const completedExercises = document.querySelectorAll('.exercise-card.completed').length;
    
    if (completedExercises === totalExercises) {
        if (confirm('모든 운동을 완료하셨습니다. 운동을 종료하시겠습니까?')) {
            alert('운동을 완료했습니다! 수고하셨습니다.');
            window.location.href = 'my_workouts.php';
        }
    } else {
        if (confirm('아직 완료하지 않은 운동이 있습니다. 정말 종료하시겠습니까?')) {
            window.location.href = 'my_workouts.php';
        }
    }
}
</script>

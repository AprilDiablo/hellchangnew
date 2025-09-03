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

// 운동 ID 파라미터 확인
if (!isset($_GET['exercise_id'])) {
    header('Location: my_workouts.php');
    exit;
}

$exerciseId = $_GET['exercise_id'];

// 페이지 제목과 부제목 설정
$pageTitle = '운동 수행';
$pageSubtitle = '운동을 시작해보세요';

// 해당 운동의 정보 가져오기
$pdo = getDB();

// 운동 정보 확인
$stmt = $pdo->prepare('
    SELECT we.*, e.name_kr, e.name_en, e.equipment, ws.workout_date, u.username as user_name
    FROM m_workout_exercise we
    JOIN m_exercise e ON we.ex_id = e.ex_id
    JOIN m_workout_session ws ON we.session_id = ws.session_id
    JOIN users u ON ws.user_id = u.id
    WHERE we.wx_id = ? AND ws.user_id = ?
');
$stmt->execute([$exerciseId, $user['id']]);
$exercise = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exercise) {
    header('Location: my_workouts.php');
    exit;
}

// 헤더 포함
include 'header.php';
?>

<div class="exercise-session-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="text-white mb-1">
                <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($exercise['name_kr']) ?>
            </h2>
            <p class="text-white-50 mb-0">
                <i class="fas fa-calendar"></i> <?= date('Y년 m월 d일', strtotime($exercise['workout_date'])) ?>
                <span class="ms-3">
                    <i class="fas fa-dumbbell"></i> <?= htmlspecialchars($exercise['equipment']) ?>
                </span>
            </p>
        </div>
        <div class="text-end">
            <div class="exercise-timer">
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

<div class="exercise-details mb-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-target"></i> 운동 목표
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-primary"><?= $exercise['weight'] ?>kg</h3>
                        <p class="text-muted mb-0">무게</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-info"><?= $exercise['reps'] ?>회</h3>
                        <p class="text-muted mb-0">반복 횟수</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <h3 class="text-success"><?= $exercise['sets'] ?>세트</h3>
                        <p class="text-muted mb-0">세트 수</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sets-container">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list-check"></i> 세트 기록
            </h5>
        </div>
        <div class="card-body">
            <?php for ($i = 1; $i <= $exercise['sets']; $i++): ?>
                <div class="set-item mb-3 p-3 border rounded" data-set="<?= $i ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">
                                <span class="badge bg-primary me-2"><?= $i ?>세트</span>
                                목표: <?= $exercise['weight'] ?>kg × <?= $exercise['reps'] ?>회
                            </h6>
                        </div>
                        <div class="set-controls">
                            <button class="btn btn-outline-success btn-sm" onclick="completeSet(<?= $i ?>)">
                                <i class="fas fa-check"></i> 완료
                            </button>
                        </div>
                    </div>
                    <div class="set-record mt-2" id="record-<?= $i ?>" style="display: none;">
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle"></i> 
                            <strong>완료!</strong> 
                            <span id="record-text-<?= $i ?>"></span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<div class="exercise-controls text-center mt-4">
    <button class="btn btn-primary btn-lg" onclick="finishExercise()">
        <i class="fas fa-flag-checkered"></i> 운동 완료
    </button>
    <a href="my_workouts.php" class="btn btn-secondary btn-lg ms-2">
        <i class="fas fa-arrow-left"></i> 목록으로
    </a>
</div>

<?php include 'footer.php'; ?>

<style>
.exercise-session-header {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    padding: 2rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
}

.exercise-timer {
    text-align: center;
}

.timer-display {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.set-item {
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.set-item.completed {
    background: #d4edda;
    border-color: #c3e6cb;
}

.set-item.completed .set-controls button {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.exercise-details .card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.exercise-details .card-header {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border: none;
}
</style>

<script>
let timerInterval;
let startTime;
let elapsedTime = 0;
let isRunning = false;
let completedSets = 0;
const totalSets = <?= $exercise['sets'] ?>;

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
function completeSet(setNumber) {
    const setItem = document.querySelector(`[data-set="${setNumber}"]`);
    const recordDiv = document.getElementById(`record-${setNumber}`);
    const recordText = document.getElementById(`record-text-${setNumber}`);
    
    // 세트 완료 표시
    setItem.classList.add('completed');
    setItem.querySelector('button').innerHTML = '<i class="fas fa-check"></i> 완료됨';
    setItem.querySelector('button').disabled = true;
    
    // 기록 표시
    recordText.textContent = `${setNumber}세트 완료 - <?= $exercise['weight'] ?>kg × <?= $exercise['reps'] ?>회`;
    recordDiv.style.display = 'block';
    
    completedSets++;
    
    // 모든 세트 완료 시 알림
    if (completedSets === totalSets) {
        setTimeout(() => {
            alert('모든 세트를 완료했습니다! 🎉');
        }, 500);
    }
}

// 운동 완료
function finishExercise() {
    if (completedSets === totalSets) {
        if (confirm('모든 세트를 완료하셨습니다. 운동을 종료하시겠습니까?')) {
            alert('운동을 완료했습니다! 수고하셨습니다. 💪');
            window.location.href = 'my_workouts.php';
        }
    } else {
        if (confirm(`아직 ${totalSets - completedSets}세트가 남았습니다. 정말 종료하시겠습니까?`)) {
            window.location.href = 'my_workouts.php';
        }
    }
}
</script>

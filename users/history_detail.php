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

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'delete_session') {
            $sessionId = $_POST['session_id'];
            
            // 권한 확인
            $stmt = $pdo->prepare("SELECT user_id FROM m_workout_session WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session || $session['user_id'] != $user['id']) {
                throw new Exception('권한이 없습니다.');
            }
            
            // 관련된 모든 데이터 삭제
            $stmt = $pdo->prepare("DELETE FROM m_workout_set WHERE wx_id IN (SELECT wx_id FROM m_workout_exercise WHERE session_id = ?)");
            $stmt->execute([$sessionId]);
            
            $stmt = $pdo->prepare("DELETE FROM m_workout_exercise WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            
            $stmt = $pdo->prepare("DELETE FROM m_workout_session WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => '세션이 삭제되었습니다.']);
            exit;
            
        } elseif ($_POST['action'] === 'delete_exercise') {
            $wxId = $_POST['wx_id'];
            
            // 권한 확인
            $stmt = $pdo->prepare("SELECT ws.user_id FROM m_workout_exercise we JOIN m_workout_session ws ON we.session_id = ws.session_id WHERE we.wx_id = ?");
            $stmt->execute([$wxId]);
            $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$exercise || $exercise['user_id'] != $user['id']) {
                throw new Exception('권한이 없습니다.');
            }
            
            // 관련된 모든 데이터 삭제
            $stmt = $pdo->prepare("DELETE FROM m_workout_set WHERE wx_id = ?");
            $stmt->execute([$wxId]);
            
            $stmt = $pdo->prepare("DELETE FROM m_workout_exercise WHERE wx_id = ?");
            $stmt->execute([$wxId]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => '운동이 삭제되었습니다.']);
            exit;
            
        } elseif ($_POST['action'] === 'update_exercise') {
            $wxId = $_POST['wx_id'];
            $weight = $_POST['weight'];
            $reps = $_POST['reps'];
            $sets = $_POST['sets'];
            $note = $_POST['note'] ?? '';
            
            // 권한 확인
            $stmt = $pdo->prepare("SELECT ws.user_id FROM m_workout_exercise we JOIN m_workout_session ws ON we.session_id = ws.session_id WHERE we.wx_id = ?");
            $stmt->execute([$wxId]);
            $exercise = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$exercise || $exercise['user_id'] != $user['id']) {
                throw new Exception('권한이 없습니다.');
            }
            
            // 운동 정보 업데이트
            $stmt = $pdo->prepare("UPDATE m_workout_exercise SET weight = ?, reps = ?, sets = ?, note = ? WHERE wx_id = ?");
            $stmt->execute([$weight, $reps, $sets, $note, $wxId]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => '운동이 수정되었습니다.']);
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

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

<div class="container-fluid mt-4 px-0">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="text-primary mb-1">
                        <i class="fas fa-dumbbell"></i> <?= $pageTitle ?>
                    </h1>
                </div>
                <a href="history.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- 날짜 표시 및 변경 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary" onclick="changeDate(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <div class="text-center flex-grow-1 mx-4">
                            <input type="date" class="form-control" id="datePicker" value="<?= $date ?>" onchange="goToDate()" style="max-width: 200px; margin: 0 auto;">
                        </div>
                        
                        <button class="btn btn-outline-primary" onclick="changeDate(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php if (!empty($sessionsWithDetails)): ?>
    <!-- 전체 요약 통계 -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2">
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-dumbbell text-primary mb-1"></i>
                                <h6 class="text-primary mb-0"><?= count($allExercises) ?>개</h6>
                                <small class="text-muted">총 운동</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-check-circle text-success mb-1"></i>
                                <h6 class="text-success mb-0"><?= $completedSets ?>/<?= $totalSets ?></h6>
                                <small class="text-muted">완료 세트</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-clock text-info mb-1"></i>
                                <h6 class="text-info mb-0"><?= round($totalWorkoutTime / 60, 1) ?>분</h6>
                                <small class="text-muted">총 시간</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-weight-hanging text-warning mb-1"></i>
                                <h6 class="text-warning mb-0"><?= number_format($totalDayVolume) ?>kg</h6>
                                <small class="text-muted">총 볼륨</small>
                            </div>
                        </div>
                    </div>
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
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <small>
                            <i class="fas fa-clock"></i> <?= round($sessionData['session_time'] / 60, 1) ?>분 |
                            <i class="fas fa-weight-hanging"></i> <?= number_format($sessionData['session_volume']) ?>kg
                        </small>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteSession(<?= $sessionData['session']['session_id'] ?>, '<?= $date ?>')" title="세션 삭제">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-2">
            <!-- 운동 목록 -->
            <?php foreach ($sessionData['exercises'] as $index => $exercise): ?>
            <div class="exercise-detail-card mb-2 p-3 border rounded" id="exercise-<?= $exercise['wx_id'] ?>" onclick="toggleSetsDetail(<?= $exercise['wx_id'] ?>)" style="cursor: pointer;">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <!-- 보기 모드 -->
                                <div id="view-mode-<?= $exercise['wx_id'] ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-primary mb-0">
                                            <span class="badge bg-primary me-2"><?= $index + 1 ?></span><?= htmlspecialchars($exercise['name_kr']) ?>
                                            <?php if ($exercise['is_temp']): ?>
                                                <span class="badge bg-warning text-dark ms-1">임시</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="btn-group btn-group-sm" role="group" onclick="event.stopPropagation()">
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="toggleEditMode(<?= $exercise['wx_id'] ?>)" title="운동 수정">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteExercise(<?= $exercise['wx_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>')" title="운동 삭제">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <p class="text-muted mb-0">
                                            <span id="weight-display-<?= $exercise['wx_id'] ?>"><?= number_format($exercise['weight'], 0) ?></span>kg × 
                                            <span id="reps-display-<?= $exercise['wx_id'] ?>"><?= $exercise['reps'] ?></span>회 × 
                                            <span id="sets-display-<?= $exercise['wx_id'] ?>"><?= $exercise['sets'] ?></span>세트
                                        </p>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <span class="text-success"><?= $exercise['completed_sets'] ?>/<?= $exercise['sets'] ?></span> 완료
                                            </small>
                                        </div>
                                    </div>
                                    <?php if ($exercise['note']): ?>
                                        <p class="text-muted mb-0"><small><em id="note-display-<?= $exercise['wx_id'] ?>"><?= htmlspecialchars($exercise['note']) ?></em></small></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- 수정 모드 -->
                                <div id="edit-mode-<?= $exercise['wx_id'] ?>" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-primary mb-0">
                                            <span class="badge bg-primary me-2"><?= $index + 1 ?></span><?= htmlspecialchars($exercise['name_kr']) ?>
                                            <?php if ($exercise['is_temp']): ?>
                                                <span class="badge bg-warning text-dark ms-1">임시</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="btn-group btn-group-sm" role="group" onclick="event.stopPropagation()">
                                            <button type="button" class="btn btn-success btn-sm" onclick="saveExerciseEdit(<?= $exercise['wx_id'] ?>)" title="저장">
                                                <i class="fas fa-save"></i>
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="cancelExerciseEdit(<?= $exercise['wx_id'] ?>)" title="취소">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4">
                                            <label class="form-label small">무게 (kg)</label>
                                            <input type="number" class="form-control form-control-sm" id="edit-weight-<?= $exercise['wx_id'] ?>" value="<?= $exercise['weight'] ?>" min="0" step="0.5">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label small">횟수</label>
                                            <input type="number" class="form-control form-control-sm" id="edit-reps-<?= $exercise['wx_id'] ?>" value="<?= $exercise['reps'] ?>" min="1">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label small">세트</label>
                                            <input type="number" class="form-control form-control-sm" id="edit-sets-<?= $exercise['wx_id'] ?>" value="<?= $exercise['sets'] ?>" min="1">
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">메모</label>
                                        <textarea class="form-control form-control-sm" id="edit-note-<?= $exercise['wx_id'] ?>" rows="2" placeholder="메모를 입력하세요"><?= htmlspecialchars($exercise['note'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 세트별 상세 기록 -->
                <?php if (!empty($exercise['sets_detail'])): ?>
                <div class="mt-3" id="sets-detail-<?= $exercise['wx_id'] ?>" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">세트</th>
                                    <th class="text-center">무게</th>
                                    <th class="text-center">횟수</th>
                                    <th class="text-center">휴식시간</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exercise['sets_detail'] as $set): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $set['set_no'] ?>세트</span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-warning"><?= number_format($set['weight'], 0) ?>kg</strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-info"><?= $set['reps'] ?>회</span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-success">
                                            <i class="fas fa-clock"></i> <?= $set['rest_time'] ?>초
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        <div class="card-body text-center py-5">
            <h4 class="text-muted mb-4">이 날의 운동 기록이 없습니다</h4>
            <p class="text-muted mb-4">운동을 기록해보세요!</p>
            <a href="today.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 운동 기록하기
            </a>
        </div>
    </div>
<?php endif; ?>

</div>

<!-- 삭제 확인 모달 -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> 삭제 확인
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">정말로 삭제하시겠습니까?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> 
                    <strong>주의:</strong> 삭제된 데이터는 복구할 수 없습니다.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> 삭제
                </button>
            </div>
        </div>
    </div>
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

/* 세트별 상세 기록 테이블 스타일 */
.table-sm th,
.table-sm td {
    padding: 0.5rem 0.75rem;
    font-size: 1.05rem;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,0.02);
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
}

/* 세트별 기록 테이블 반응형 */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .table-sm th,
    .table-sm td {
        padding: 0.4rem 0.5rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.3em 0.5em;
    }
}

/* 수정 모드 스타일 */
[id^="edit-mode-"] {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    animation: slideIn 0.3s ease-out;
}

[id^="edit-mode-"] .form-control {
    border-color: #ffc107;
}

[id^="edit-mode-"] .form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 토스트 메시지 스타일 */
.toast-message {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
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
</style>

<script>
let currentDeleteAction = null;
let currentDeleteParams = null;


// 세션 삭제
function deleteSession(sessionId, date) {
    currentDeleteAction = 'session';
    currentDeleteParams = { session_id: sessionId };
    
    document.getElementById('deleteMessage').textContent = 
        `정말로 이 운동 세션을 삭제하시겠습니까?\n\n삭제 시 해당 세션의 모든 운동과 세트 기록이 영구적으로 삭제됩니다.`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// 운동 삭제
function deleteExercise(wxId, exerciseName) {
    currentDeleteAction = 'exercise';
    currentDeleteParams = { wx_id: wxId };
    
    document.getElementById('deleteMessage').textContent = 
        `정말로 "${exerciseName}" 운동을 삭제하시겠습니까?\n\n삭제 시 해당 운동의 모든 세트 기록이 영구적으로 삭제됩니다.`;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}

// 수정 모드 토글
function toggleEditMode(wxId) {
    // 보기 모드 숨기기
    document.getElementById(`view-mode-${wxId}`).style.display = 'none';
    document.getElementById(`view-buttons-${wxId}`).style.display = 'none';
    
    // 수정 모드 보이기
    document.getElementById(`edit-mode-${wxId}`).style.display = 'block';
    document.getElementById(`edit-buttons-${wxId}`).style.display = 'block';
}

// 수정 취소
function cancelExerciseEdit(wxId) {
    // 수정 모드 숨기기
    document.getElementById(`edit-mode-${wxId}`).style.display = 'none';
    document.getElementById(`edit-buttons-${wxId}`).style.display = 'none';
    
    // 보기 모드 보이기
    document.getElementById(`view-mode-${wxId}`).style.display = 'block';
    document.getElementById(`view-buttons-${wxId}`).style.display = 'block';
}

// 운동 수정 저장
function saveExerciseEdit(wxId) {
    const weight = document.getElementById(`edit-weight-${wxId}`).value;
    const reps = document.getElementById(`edit-reps-${wxId}`).value;
    const sets = document.getElementById(`edit-sets-${wxId}`).value;
    const note = document.getElementById(`edit-note-${wxId}`).value;
    
    if (!weight || !reps || !sets) {
        alert('모든 필드를 입력해주세요.');
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_exercise&wx_id=${wxId}&weight=${weight}&reps=${reps}&sets=${sets}&note=${encodeURIComponent(note)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 화면에 즉시 반영
            document.getElementById(`weight-display-${wxId}`).textContent = Math.round(weight);
            document.getElementById(`reps-display-${wxId}`).textContent = reps;
            document.getElementById(`sets-display-${wxId}`).textContent = sets;
            document.getElementById(`note-display-${wxId}`).textContent = note;
            
            // 보기 모드로 전환
            cancelExerciseEdit(wxId);
            
            // 성공 메시지
            showToast('운동이 수정되었습니다.', 'success');
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('오류가 발생했습니다.');
    });
}

// 토스트 메시지 표시
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // 3초 후 자동 제거
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

// 세트별 상세 기록 토글
function toggleSetsDetail(wxId) {
    const setsDetail = document.getElementById(`sets-detail-${wxId}`);
    if (setsDetail) {
        if (setsDetail.style.display === 'none') {
            setsDetail.style.display = 'block';
        } else {
            setsDetail.style.display = 'none';
        }
    }
}

// 날짜 변경 (이전/다음)
function changeDate(direction) {
    const currentDate = new Date('<?= $date ?>');
    currentDate.setDate(currentDate.getDate() + direction);
    const newDate = currentDate.toISOString().split('T')[0];
    window.location.href = `history_detail.php?date=${newDate}`;
}


// 날짜 선택기로 이동
function goToDate() {
    const selectedDate = document.getElementById('datePicker').value;
    if (selectedDate) {
        window.location.href = `history_detail.php?date=${selectedDate}`;
    }
}


// 삭제 확인 버튼 클릭
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!currentDeleteAction || !currentDeleteParams) return;
    
    let action = '';
    let body = '';
    
    if (currentDeleteAction === 'session') {
        action = 'delete_session';
        body = `session_id=${currentDeleteParams.session_id}`;
    } else if (currentDeleteAction === 'exercise') {
        action = 'delete_exercise';
        body = `wx_id=${currentDeleteParams.wx_id}`;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=${action}&${body}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = 'history.php';
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('오류가 발생했습니다.');
    });
    
    // 모달 닫기
    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
    modal.hide();
});
</script>

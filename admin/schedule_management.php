<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 사용자 목록 가져오기
$stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE is_active = 1 ORDER BY username ASC');
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 선택된 사용자와 날짜
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 선택된 사용자의 운동 기록 가져오기
$workoutSessions = [];
$selectedUser = null;

if ($selected_user_id) {
    // 선택된 사용자 정보
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$selected_user_id]);
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 해당 사용자의 운동 세션들 (최근 30일)
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
            ) as exercise_summary
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        WHERE ws.user_id = ? 
        AND ws.workout_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY ws.session_id, ws.workout_date, ws.note, ws.duration, ws.start_time, ws.end_time
        ORDER BY ws.workout_date DESC, ws.session_id DESC
    ');
    $stmt->execute([$selected_user_id]);
    $workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 특정 세션의 상세 운동 정보 가져오기
$sessionDetails = [];
if (isset($_GET['session_id'])) {
    $session_id = (int)$_GET['session_id'];
    $stmt = $pdo->prepare('
        SELECT 
            we.wx_id,
            we.ex_id,
            we.order_no,
            we.weight,
            we.reps,
            we.sets,
            we.note,
            we.original_exercise_name,
            we.is_temp,
            e.name_kr,
            e.name_en,
            te.exercise_name as temp_name
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$session_id]);
    $sessionDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>스케줄 관리 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exercise-card {
            border-left: 4px solid #007bff;
        }
        .session-card {
            transition: all 0.3s ease;
        }
        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- 헤더 -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-calendar-alt me-2"></i>스케줄 관리</h2>
                        <p class="text-muted">사용자들의 운동 기록을 조회하고 관리합니다</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
                    </a>
                </div>
            </div>
        </div>

        <!-- 사용자 목록 -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>사용자 목록</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>사용자명</th>
                                        <th>이메일</th>
                                        <th>사용자 ID</th>
                                        <th>상태</th>
                                        <th>액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr class="<?= $selected_user_id == $user['id'] ? 'table-primary' : '' ?>" 
                                        style="cursor: pointer;" 
                                        onclick="selectUser(<?= $user['id'] ?>)">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user me-2"></i>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <?php if ($selected_user_id == $user['id']): ?>
                                                <i class="fas fa-check-circle text-primary ms-2"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['email']): ?>
                                            <i class="fas fa-envelope me-1 text-muted"></i>
                                            <?= htmlspecialchars($user['email']) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $user['id'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($selected_user_id == $user['id']): ?>
                                            <span class="badge bg-primary">선택됨</span>
                                            <?php else: ?>
                                            <span class="badge bg-light text-dark">미선택</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="event.stopPropagation(); viewUserSchedule(<?= $user['id'] ?>)">
                                                <i class="fas fa-calendar-alt me-1"></i>보기
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($selected_user_id): ?>
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                날짜 필터를 사용하려면 아래 필터를 사용하세요.
                            </div>
                            <form method="get" class="row g-3">
                                <input type="hidden" name="user_id" value="<?= $selected_user_id ?>">
                                <div class="col-md-6">
                                    <label for="date" class="form-label">날짜 필터 (선택사항)</label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?= $selected_date ?>" onchange="this.form.submit()">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-secondary" onclick="clearDateFilter()">
                                            <i class="fas fa-times me-1"></i>날짜 필터 제거
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($selectedUser): ?>
        <!-- 선택된 사용자 정보 -->
        <div class="row mb-4">
            <div class="col">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4><?= htmlspecialchars($selectedUser['username']) ?></h4>
                                <small>선택된 사용자</small>
                            </div>
                            <div class="col-md-3">
                                <h4><?= count($workoutSessions) ?></h4>
                                <small>최근 30일 운동 횟수</small>
                            </div>
                            <div class="col-md-3">
                                <h4>
                                    <?php
                                    $totalExercises = 0;
                                    foreach ($workoutSessions as $session) {
                                        $totalExercises += $session['exercise_count'];
                                    }
                                    echo $totalExercises;
                                    ?>
                                </h4>
                                <small>총 운동 개수</small>
                            </div>
                            <div class="col-md-3">
                                <h4>
                                    <?php
                                    $totalDuration = 0;
                                    foreach ($workoutSessions as $session) {
                                        $totalDuration += $session['duration'] ?: 0;
                                    }
                                    echo round($totalDuration) . '분';
                                    ?>
                                </h4>
                                <small>총 운동 시간</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 운동 세션 목록 -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-dumbbell me-2"></i>운동 기록
                            <?php if ($selected_date): ?>
                            <small class="text-muted">- <?= $selected_date ?></small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($workoutSessions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">운동 기록이 없습니다</h5>
                            <p class="text-muted">이 사용자는 아직 운동을 기록하지 않았습니다.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($workoutSessions as $session): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card session-card h-100">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="fas fa-calendar-day me-1"></i>
                                                <?= date('m/d', strtotime($session['workout_date'])) ?>
                                            </h6>
                                            <span class="badge bg-primary"><?= $session['exercise_count'] ?>개</span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($session['duration']): ?>
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= round($session['duration']) ?>분
                                            <?php if ($session['start_time']): ?>
                                            (<?= date('H:i', strtotime($session['start_time'])) ?> - 
                                            <?= date('H:i', strtotime($session['end_time'])) ?>)
                                            <?php endif; ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($session['note']): ?>
                                        <p class="text-info small mb-2">
                                            <i class="fas fa-sticky-note me-1"></i>
                                            <?= htmlspecialchars($session['note']) ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <p class="text-muted small mb-3">
                                            <?= htmlspecialchars($session['exercise_summary']) ?>
                                        </p>
                                        
                                        <div class="d-grid">
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="viewSessionDetails(<?= $session['session_id'] ?>)">
                                                <i class="fas fa-eye me-1"></i>상세 보기
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 세션 상세 모달 -->
    <div class="modal fade" id="sessionDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-dumbbell me-2"></i>운동 세션 상세
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="sessionDetailContent">
                    <!-- 동적으로 로드됨 -->
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
    
    // 사용자 선택
    function selectUser(userId) {
        window.location.href = `?user_id=${userId}`;
    }
    
    // 사용자 스케줄 보기
    function viewUserSchedule(userId) {
        window.location.href = `?user_id=${userId}`;
    }
    
    // 날짜 필터 제거
    function clearDateFilter() {
        const url = new URL(window.location);
        url.searchParams.delete('date');
        window.location.href = url.toString();
    }
    
    // 세션 상세 보기
    function viewSessionDetails(sessionId) {
        // 로딩 표시
        document.getElementById('sessionDetailContent').innerHTML = 
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><br>로딩 중...</div>';
        
        // 모달 표시
        const modal = new bootstrap.Modal(document.getElementById('sessionDetailModal'));
        modal.show();
        
        // 세션 상세 정보 가져오기
        fetch(`?session_id=${sessionId}`)
        .then(response => response.text())
        .then(html => {
            // 세션 상세 정보만 추출 (PHP에서 처리)
            document.getElementById('sessionDetailContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('sessionDetailContent').innerHTML = 
                '<div class="alert alert-danger">세션 정보를 불러올 수 없습니다.</div>';
        });
    }
    </script>
</body>
</html>

<?php
// 세션 상세 정보가 요청된 경우
if (isset($_GET['session_id']) && !empty($sessionDetails)) {
    echo '<div class="row">';
    foreach ($sessionDetails as $exercise) {
        $exerciseName = $exercise['name_kr'] ?: $exercise['temp_name'] ?: $exercise['original_exercise_name'] ?: '알 수 없음';
        $isTemp = $exercise['is_temp'] ? ' (임시)' : '';
        ?>
        <div class="col-md-6 mb-3">
            <div class="card exercise-card">
                <div class="card-body">
                    <h6 class="card-title">
                        <?= $exercise['order_no'] ?>. <?= htmlspecialchars($exerciseName) ?><?= $isTemp ?>
                    </h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted">무게</small><br>
                            <strong><?= $exercise['weight'] ? (int)$exercise['weight'] . 'kg' : '-' ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">횟수</small><br>
                            <strong><?= $exercise['reps'] ? $exercise['reps'] . '회' : '-' ?></strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">세트</small><br>
                            <strong><?= $exercise['sets'] ? $exercise['sets'] . '세트' : '-' ?></strong>
                        </div>
                    </div>
                    <?php if ($exercise['note']): ?>
                    <p class="text-muted small mt-2 mb-0">
                        <i class="fas fa-sticky-note me-1"></i>
                        <?= htmlspecialchars($exercise['note']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    exit;
}
?>

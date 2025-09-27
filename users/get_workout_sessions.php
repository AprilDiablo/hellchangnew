<?php
session_start();
require_once 'auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 로그인 확인
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

$user = getCurrentUser();
$date = $_GET['date'] ?? date('Y-m-d');

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
    ORDER BY ws.session_id DESC
');
$stmt->execute([$user['id'], $date]);
$workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($workoutSessions)): ?>
    <?php 
    $totalSessions = count($workoutSessions);
    foreach ($workoutSessions as $idx => $session): 
        $roundNumber = $totalSessions - $idx;
    ?>
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <a href="my_workouts_ing.php?session_id=<?= $session['session_id'] ?>" class="text-decoration-none text-dark flex-grow-1">
                    <div>
                        <h6 class="mb-1">
                            <i class="fas fa-dumbbell text-primary me-2"></i>
                            <strong><?= $roundNumber ?>회차</strong>
                        </h6>
                        <small class="text-muted">
                            <i class="fas fa-list me-1"></i>
                            운동 수: <?= (int)$session['exercise_count'] ?>개
                        </small>
                    </div>
                </a>
                <div class="btn-group btn-group-sm ms-3">
                    <a href="today.php?edit_session=<?= $session['session_id'] ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit"></i> 수정
                    </a>
                    <button type="button" class="btn btn-outline-danger btn-sm" 
                            onclick="deleteSession(<?= $session['session_id'] ?>)">
                        <i class="fas fa-trash"></i> 삭제
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-info text-center">
        해당 날짜에 운동 세션이 없습니다.
    </div>
<?php endif; ?>

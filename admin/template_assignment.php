<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 사용자 목록 가져오기
$stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE is_active = 1 ORDER BY username ASC');
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 템플릿 목록 가져오기
$stmt = $pdo->prepare('
    SELECT t.*, a.name as created_by_name 
    FROM m_workout_template t 
    LEFT JOIN admins a ON t.created_by = a.id 
    ORDER BY t.created_at DESC
');
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 할당 기록 가져오기
$stmt = $pdo->prepare('
    SELECT ta.*, t.template_name, u.username as user_name, a.name as admin_name
    FROM m_template_assignment ta
    LEFT JOIN m_workout_template t ON ta.template_id = t.template_id
    LEFT JOIN users u ON ta.user_id = u.id
    LEFT JOIN admins a ON ta.assigned_by = a.id
    ORDER BY ta.created_at DESC
    LIMIT 50
');
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// POST 요청 처리
if ($_POST) {
    if ($_POST['action'] === 'assign_template') {
        $template_id = (int)$_POST['template_id'];
        $user_id = (int)$_POST['user_id'];
        $workout_date = $_POST['workout_date'];
        $note = trim($_POST['note']);
        
        try {
            $pdo->beginTransaction();
            
            // 할당 기록 저장
            $stmt = $pdo->prepare('
                INSERT INTO m_template_assignment 
                (template_id, user_id, assigned_by, workout_date, note) 
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$template_id, $user_id, $_SESSION['admin_id'], $workout_date, $note]);
            $assignment_id = $pdo->lastInsertId();
            
            // 템플릿의 운동들을 가져와서 사용자의 운동 세션으로 생성
            $stmt = $pdo->prepare('
                SELECT * FROM m_workout_template_exercise 
                WHERE template_id = ? 
                ORDER BY order_no ASC
            ');
            $stmt->execute([$template_id]);
            $template_exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($template_exercises)) {
                // 운동 세션 생성
                $stmt = $pdo->prepare('
                    INSERT INTO m_workout_session (user_id, workout_date, note) 
                    VALUES (?, ?, ?)
                ');
                $stmt->execute([$user_id, $workout_date, '템플릿 할당: ' . $note]);
                $session_id = $pdo->lastInsertId();
                
                // 세션 연결 저장
                $stmt = $pdo->prepare('
                    INSERT INTO m_template_workout_session (assignment_id, session_id) 
                    VALUES (?, ?)
                ');
                $stmt->execute([$assignment_id, $session_id]);
                
                // 각 운동을 세션에 추가
                foreach ($template_exercises as $exercise) {
                    $stmt = $pdo->prepare('
                        INSERT INTO m_workout_exercise 
                        (session_id, ex_id, order_no, weight, reps, sets, note, original_exercise_name) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $session_id,
                        $exercise['ex_id'],
                        $exercise['order_no'],
                        $exercise['weight'],
                        $exercise['reps'],
                        $exercise['sets'],
                        $exercise['note'],
                        $exercise['exercise_name']
                    ]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "템플릿이 성공적으로 할당되었습니다.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "할당 중 오류가 발생했습니다: " . $e->getMessage();
        }
        
        header("Location: template_assignment.php");
        exit;
    }
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
    <title>운동 템플릿 할당 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- 헤더 -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-user-plus me-2"></i>운동 템플릿 할당</h2>
                        <p class="text-muted">사용자에게 운동 템플릿을 할당합니다</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
                    </a>
                </div>
            </div>
        </div>

        <!-- 템플릿 할당 폼 -->
        <div class="row mb-5">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>새 할당</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="assign_template">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="template_id" class="form-label">운동 템플릿</label>
                                    <select class="form-select" id="template_id" name="template_id" required>
                                        <option value="">템플릿을 선택하세요</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?= $template['template_id'] ?>">
                                            <?= htmlspecialchars($template['template_name']) ?>
                                            <?php if ($template['description']): ?>
                                            - <?= htmlspecialchars($template['description']) ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="user_id" class="form-label">사용자</label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">사용자를 선택하세요</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['username']) ?>
                                            <?php if ($user['email']): ?>
                                            (<?= htmlspecialchars($user['email']) ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="workout_date" class="form-label">운동 예정일</label>
                                    <input type="date" class="form-control" id="workout_date" name="workout_date" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="note" class="form-label">메모 (선택사항)</label>
                                    <input type="text" class="form-control" id="note" name="note" 
                                           placeholder="할당 메모를 입력하세요">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>할당하기
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 통계 카드 -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>할당 통계</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_assignments = count($assignments);
                        $completed_assignments = count(array_filter($assignments, function($a) { return $a['status'] === 'completed'; }));
                        $active_assignments = count(array_filter($assignments, function($a) { return $a['status'] === 'assigned'; }));
                        ?>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4 class="text-primary"><?= $total_assignments ?></h4>
                                <small class="text-muted">총 할당</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?= $completed_assignments ?></h4>
                                <small class="text-muted">완료</small>
                            </div>
                            <div class="col-4">
                                <h4 class="text-warning"><?= $active_assignments ?></h4>
                                <small class="text-muted">진행중</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 할당 기록 -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>할당 기록</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">할당 기록이 없습니다</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>템플릿</th>
                                        <th>사용자</th>
                                        <th>운동일</th>
                                        <th>상태</th>
                                        <th>할당자</th>
                                        <th>할당일</th>
                                        <th>메모</th>
                                        <th>액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($assignment['template_name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($assignment['user_name']) ?></td>
                                        <td><?= $assignment['workout_date'] ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($assignment['status']) {
                                                case 'assigned':
                                                    $status_class = 'badge bg-warning';
                                                    $status_text = '할당됨';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'badge bg-success';
                                                    $status_text = '완료';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'badge bg-danger';
                                                    $status_text = '취소';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($assignment['admin_name']) ?></td>
                                        <td><?= date('m/d H:i', strtotime($assignment['created_at'])) ?></td>
                                        <td>
                                            <?php if ($assignment['note']): ?>
                                            <small class="text-muted"><?= htmlspecialchars($assignment['note']) ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['status'] === 'assigned'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="updateStatus(<?= $assignment['assignment_id'] ?>, 'completed')"
                                                        title="완료로 표시">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="updateStatus(<?= $assignment['assignment_id'] ?>, 'cancelled')"
                                                        title="취소로 표시">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
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
    
    // 할당 상태 업데이트
    function updateStatus(assignmentId, status) {
        const statusText = status === 'completed' ? '완료' : '취소';
        
        if (!confirm(`정말로 이 할당을 "${statusText}"로 표시하시겠습니까?`)) {
            return;
        }
        
        fetch('update_assignment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                assignment_id: assignmentId,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('오류: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('상태 업데이트 중 오류가 발생했습니다.');
        });
    }
    </script>
</body>
</html>

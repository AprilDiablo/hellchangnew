<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 관계 ID 확인
if (!isset($_GET['relationship_id'])) {
    header('Location: trainer_management.php');
    exit;
}

$relationship_id = $_GET['relationship_id'];

// 트레이너 관계 정보 가져오기
$relationship = null;
try {
    $stmt = $pdo->prepare("
        SELECT 
            tr.*,
            t.username as trainer_name,
            t.profile_image as trainer_image,
            m.username as member_name,
            m.profile_image as member_image,
            m.email as member_email
        FROM trainer_relationships tr
        JOIN users t ON tr.trainer_id = t.id
        JOIN users m ON tr.member_id = m.id
        WHERE tr.id = ?
    ");
    $stmt->execute([$relationship_id]);
    $relationship = $stmt->fetch();
    
    if (!$relationship) {
        header('Location: trainer_management.php');
        exit;
    }
} catch (Exception $e) {
    $error = "관계 정보를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 스케줄 목록 가져오기
$schedules = [];
if ($relationship && $relationship['status'] === 'approved') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM trainer_schedules 
            WHERE trainer_id = ? AND member_id = ?
            ORDER BY schedule_date DESC, start_time ASC
        ");
        $stmt->execute([$relationship['trainer_id'], $relationship['member_id']]);
        $schedules = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "스케줄 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 코멘트 목록 가져오기
$comments = [];
if ($relationship && $relationship['status'] === 'approved') {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, ws.workout_date as session_date
            FROM trainer_comments tc
            LEFT JOIN m_workout_session ws ON tc.workout_session_id = ws.session_id
            WHERE tc.trainer_id = ? AND tc.member_id = ?
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute([$relationship['trainer_id'], $relationship['member_id']]);
        $comments = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "코멘트 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 평가 목록 가져오기
$assessments = [];
if ($relationship && $relationship['status'] === 'approved') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM trainer_assessments 
            WHERE trainer_id = ? AND member_id = ?
            ORDER BY assessment_date DESC
        ");
        $stmt->execute([$relationship['trainer_id'], $relationship['member_id']]);
        $assessments = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "평가 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 회원의 운동 기록 가져오기
$workouts = [];
if ($relationship && $relationship['status'] === 'approved') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ws.session_id,
                ws.workout_date as session_date,
                COUNT(DISTINCT we.ex_id) as exercise_count,
                SUM(we.weight * we.reps * we.sets) as total_volume
            FROM m_workout_session ws
            LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
            WHERE ws.user_id = ?
            GROUP BY ws.session_id
            ORDER BY ws.workout_date DESC
            LIMIT 20
        ");
        $stmt->execute([$relationship['member_id']]);
        $workouts = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "운동 기록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 폼 처리
if ($_POST) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add_schedule') {
                // 새 스케줄 추가
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_schedules (trainer_id, member_id, schedule_date, start_time, end_time, activity_type, title, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $relationship['trainer_id'],
                    $relationship['member_id'],
                    $_POST['schedule_date'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    $_POST['activity_type'],
                    $_POST['title'],
                    $_POST['description']
                ]);
                
                $message = "스케줄이 성공적으로 추가되었습니다.";
                
            } elseif ($_POST['action'] === 'add_comment') {
                // 새 코멘트 추가
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_comments (trainer_id, member_id, workout_session_id, comment_type, title, content, is_private)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $relationship['trainer_id'],
                    $relationship['member_id'],
                    $_POST['workout_session_id'] ?: null,
                    $_POST['comment_type'],
                    $_POST['title'],
                    $_POST['content'],
                    isset($_POST['is_private']) ? 1 : 0
                ]);
                
                $message = "코멘트가 성공적으로 추가되었습니다.";
                
            } elseif ($_POST['action'] === 'add_assessment') {
                // 새 평가 추가
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_assessments (trainer_id, member_id, assessment_date, category, score, notes, next_goal)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $relationship['trainer_id'],
                    $relationship['member_id'],
                    $_POST['assessment_date'],
                    $_POST['category'],
                    $_POST['score'],
                    $_POST['notes'],
                    $_POST['next_goal']
                ]);
                
                $message = "평가가 성공적으로 추가되었습니다.";
            }
        }
        
        $pdo->commit();
        
        // 페이지 새로고침으로 목록 업데이트
        header('Location: trainer_detail.php?relationship_id=' . $relationship_id . '&message=' . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>트레이너 상세 관리 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .trainer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .trainer-card .card-body {
            padding: 1.5rem;
        }
        .trainer-card .card-header {
            padding: 1rem 1.5rem;
        }
        .back-btn {
            background: #6c757d;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        .profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .status-approved {
            color: #28a745;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-rejected {
            color: #dc3545;
        }
        .status-terminated {
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-color: #dee2e6 #dee2e6 #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="trainer_management.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>트레이너 관리로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-dumbbell me-3"></i>트레이너 상세 관리
            </h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($relationship): ?>
            <!-- 관계 정보 -->
            <div class="trainer-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>트레이너-회원 관계 정보
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-tie me-2"></i>트레이너</h6>
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($relationship['trainer_image']): ?>
                                    <img src="<?= htmlspecialchars($relationship['trainer_image']) ?>" 
                                         alt="트레이너" class="profile-image me-3">
                                <?php else: ?>
                                    <div class="profile-placeholder me-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($relationship['trainer_name']) ?></h5>
                                    <span class="badge bg-primary">트레이너</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-user me-2"></i>회원</h6>
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($relationship['member_image']): ?>
                                    <img src="<?= htmlspecialchars($relationship['member_image']) ?>" 
                                         alt="회원" class="profile-image me-3">
                                <?php else: ?>
                                    <div class="profile-placeholder me-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($relationship['member_name']) ?></h5>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($relationship['member_email']) ?></p>
                                    <span class="badge bg-success">회원</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>상태:</strong>
                            <span class="status-<?= $relationship['status'] ?>">
                                <i class="fas fa-circle me-1"></i>
                                <?php
                                switch($relationship['status']) {
                                    case 'pending': echo '대기중'; break;
                                    case 'approved': echo '승인됨'; break;
                                    case 'rejected': echo '거절됨'; break;
                                    case 'terminated': echo '종료됨'; break;
                                }
                                ?>
                            </span>
                        </div>
                        <div class="col-md-3">
                            <strong>요청일:</strong><br>
                            <?= date('Y-m-d', strtotime($relationship['request_date'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>승인일:</strong><br>
                            <?= $relationship['approval_date'] ? date('Y-m-d', strtotime($relationship['approval_date'])) : '-' ?>
                        </div>
                        <div class="col-md-3">
                            <strong>종료일:</strong><br>
                            <?= $relationship['termination_date'] ? date('Y-m-d', strtotime($relationship['termination_date'])) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($relationship['status'] === 'approved'): ?>
                <!-- 탭 네비게이션 -->
                <ul class="nav nav-tabs mb-4" id="trainerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="workouts-tab" data-bs-toggle="tab" data-bs-target="#workouts" type="button" role="tab">
                            <i class="fas fa-dumbbell me-2"></i>운동 기록
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#schedules" type="button" role="tab">
                            <i class="fas fa-calendar me-2"></i>스케줄 관리
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments" type="button" role="tab">
                            <i class="fas fa-comment me-2"></i>코멘트 관리
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab">
                            <i class="fas fa-star me-2"></i>평가 관리
                        </button>
                    </li>
                </ul>

                <!-- 탭 콘텐츠 -->
                <div class="tab-content" id="trainerTabsContent">
                    <!-- 운동 기록 탭 -->
                    <div class="tab-pane fade show active" id="workouts" role="tabpanel">
                        <div class="trainer-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-dumbbell me-2"></i>회원 운동 기록
                                </h5>
                                <span class="badge bg-info"><?= count($workouts) ?>개</span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($workouts)): ?>
                                    <p class="text-muted text-center">아직 운동 기록이 없습니다.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>날짜</th>
                                                    <th>운동 수</th>
                                                    <th>총 볼륨</th>
                                                    <th>상세보기</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($workouts as $workout): ?>
                                                    <tr>
                                                        <td><?= date('Y-m-d', strtotime($workout['session_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-primary"><?= $workout['exercise_count'] ?>개</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success"><?= number_format($workout['total_volume']) ?></span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewWorkoutDetail(<?= $workout['session_id'] ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
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

                    <!-- 스케줄 관리 탭 -->
                    <div class="tab-pane fade" id="schedules" role="tabpanel">
                        <div class="trainer-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar me-2"></i>스케줄 관리
                                </h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addScheduleForm">
                                    <i class="fas fa-plus me-2"></i>새 스케줄
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- 새 스케줄 폼 -->
                                <div class="collapse mb-4" id="addScheduleForm">
                                    <div class="card">
                                        <div class="card-body">
                                            <form method="post" class="row g-3">
                                                <input type="hidden" name="action" value="add_schedule">
                                                
                                                <div class="col-md-3">
                                                    <label for="schedule_date" class="form-label">날짜 *</label>
                                                    <input type="date" class="form-control" id="schedule_date" name="schedule_date" required>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <label for="start_time" class="form-label">시작 시간 *</label>
                                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <label for="end_time" class="form-label">종료 시간 *</label>
                                                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                                                </div>
                                                
                                                <div class="col-md-2">
                                                    <label for="activity_type" class="form-label">활동 유형 *</label>
                                                    <select class="form-select" id="activity_type" name="activity_type" required>
                                                        <option value="workout">운동</option>
                                                        <option value="consultation">상담</option>
                                                        <option value="assessment">평가</option>
                                                        <option value="other">기타</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-3">
                                                    <label for="title" class="form-label">제목 *</label>
                                                    <input type="text" class="form-control" id="title" name="title" required>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <label for="description" class="form-label">설명</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-plus me-2"></i>스케줄 추가
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 스케줄 목록 -->
                                <?php if (empty($schedules)): ?>
                                    <p class="text-muted text-center">아직 스케줄이 없습니다.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>날짜</th>
                                                    <th>시간</th>
                                                    <th>활동</th>
                                                    <th>제목</th>
                                                    <th>상태</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($schedules as $schedule): ?>
                                                    <tr>
                                                        <td><?= date('Y-m-d', strtotime($schedule['schedule_date'])) ?></td>
                                                        <td>
                                                            <?= date('H:i', strtotime($schedule['start_time'])) ?> - 
                                                            <?= date('H:i', strtotime($schedule['end_time'])) ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php
                                                                switch($schedule['activity_type']) {
                                                                    case 'workout': echo '운동'; break;
                                                                    case 'consultation': echo '상담'; break;
                                                                    case 'assessment': echo '평가'; break;
                                                                    case 'other': echo '기타'; break;
                                                                }
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($schedule['title']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $schedule['status'] === 'scheduled' ? 'primary' : ($schedule['status'] === 'completed' ? 'success' : 'secondary') ?>">
                                                                <?php
                                                                switch($schedule['status']) {
                                                                    case 'scheduled': echo '예정'; break;
                                                                    case 'completed': echo '완료'; break;
                                                                    case 'cancelled': echo '취소'; break;
                                                                }
                                                                ?>
                                                            </span>
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

                    <!-- 코멘트 관리 탭 -->
                    <div class="tab-pane fade" id="comments" role="tabpanel">
                        <div class="trainer-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-comment me-2"></i>코멘트 관리
                                </h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addCommentForm">
                                    <i class="fas fa-plus me-2"></i>새 코멘트
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- 새 코멘트 폼 -->
                                <div class="collapse mb-4" id="addCommentForm">
                                    <div class="card">
                                        <div class="card-body">
                                            <form method="post" class="row g-3">
                                                <input type="hidden" name="action" value="add_comment">
                                                
                                                <div class="col-md-4">
                                                    <label for="comment_type" class="form-label">코멘트 유형 *</label>
                                                    <select class="form-select" id="comment_type" name="comment_type" required>
                                                        <option value="general">일반</option>
                                                        <option value="workout_feedback">운동 피드백</option>
                                                        <option value="progress_note">진행 노트</option>
                                                        <option value="goal_setting">목표 설정</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <label for="workout_session_id" class="form-label">운동 세션 (선택)</label>
                                                    <select class="form-select" id="workout_session_id" name="workout_session_id">
                                                        <option value="">선택 안함</option>
                                                        <?php foreach ($workouts as $workout): ?>
                                                            <option value="<?= $workout['session_id'] ?>">
                                                                <?= date('Y-m-d', strtotime($workout['session_date'])) ?> 
                                                                (<?= $workout['exercise_count'] ?>개 운동)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="form-check mt-4">
                                                        <input class="form-check-input" type="checkbox" id="is_private" name="is_private">
                                                        <label class="form-check-label" for="is_private">
                                                            비공개 코멘트
                                                        </label>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <label for="title" class="form-label">제목 *</label>
                                                    <input type="text" class="form-control" id="title" name="title" required>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <label for="content" class="form-label">내용 *</label>
                                                    <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-plus me-2"></i>코멘트 추가
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 코멘트 목록 -->
                                <?php if (empty($comments)): ?>
                                    <p class="text-muted text-center">아직 코멘트가 없습니다.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-header d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-<?= $comment['is_private'] ? 'warning' : 'info' ?>">
                                                            <?php
                                                            switch($comment['comment_type']) {
                                                                case 'general': echo '일반'; break;
                                                                case 'workout_feedback': echo '운동 피드백'; break;
                                                                case 'progress_note': echo '진행 노트'; break;
                                                                case 'goal_setting': echo '목표 설정'; break;
                                                            }
                                                            ?>
                                                        </span>
                                                        <?php if ($comment['is_private']): ?>
                                                            <i class="fas fa-lock text-warning" title="비공개"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?= htmlspecialchars($comment['title']) ?></h6>
                                                        <p class="card-text"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                                        <small class="text-muted">
                                                            <?= date('Y-m-d H:i', strtotime($comment['created_at'])) ?>
                                                            <?php if ($comment['session_date']): ?>
                                                                <br>운동일: <?= date('Y-m-d', strtotime($comment['session_date'])) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 평가 관리 탭 -->
                    <div class="tab-pane fade" id="assessments" role="tabpanel">
                        <div class="trainer-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>평가 관리
                                </h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#addAssessmentForm">
                                    <i class="fas fa-plus me-2"></i>새 평가
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- 새 평가 폼 -->
                                <div class="collapse mb-4" id="addAssessmentForm">
                                    <div class="card">
                                        <div class="card-body">
                                            <form method="post" class="row g-3">
                                                <input type="hidden" name="action" value="add_assessment">
                                                
                                                <div class="col-md-3">
                                                    <label for="assessment_date" class="form-label">평가 날짜 *</label>
                                                    <input type="date" class="form-control" id="assessment_date" name="assessment_date" required>
                                                </div>
                                                
                                                <div class="col-md-3">
                                                    <label for="category" class="form-label">평가 카테고리 *</label>
                                                    <select class="form-select" id="category" name="category" required>
                                                        <option value="overall">전체</option>
                                                        <option value="strength">근력</option>
                                                        <option value="endurance">지구력</option>
                                                        <option value="flexibility">유연성</option>
                                                        <option value="body_composition">체지방률</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="col-md-3">
                                                    <label for="score" class="form-label">점수 (1-10) *</label>
                                                    <input type="number" class="form-control" id="score" name="score" min="1" max="10" step="0.1" required>
                                                </div>
                                                
                                                <div class="col-md-3">
                                                    <label for="score" class="form-label">&nbsp;</label>
                                                    <div class="d-flex align-items-end">
                                                        <div class="form-control-plaintext">
                                                            <div class="d-flex">
                                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                                    <i class="fas fa-star text-warning me-1" style="cursor: pointer;" onclick="setScore(<?= $i ?>)"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <label for="notes" class="form-label">평가 노트</label>
                                                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="현재 상태와 개선점을 기록하세요"></textarea>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <label for="next_goal" class="form-label">다음 목표</label>
                                                    <textarea class="form-control" id="next_goal" name="next_goal" rows="2" placeholder="향후 목표를 설정하세요"></textarea>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-plus me-2"></i>평가 추가
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 평가 목록 -->
                                <?php if (empty($assessments)): ?>
                                    <p class="text-muted text-center">아직 평가가 없습니다.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($assessments as $assessment): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-header d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-primary">
                                                            <?php
                                                            switch($assessment['category']) {
                                                                case 'overall': echo '전체'; break;
                                                                case 'strength': echo '근력'; break;
                                                                case 'endurance': echo '지구력'; break;
                                                                case 'flexibility': echo '유연성'; break;
                                                                case 'body_composition': echo '체지방률'; break;
                                                            }
                                                            ?>
                                                        </span>
                                                        <small class="text-muted"><?= date('Y-m-d', strtotime($assessment['assessment_date'])) ?></small>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <h6 class="mb-0 me-3">점수: <?= $assessment['score'] ?>/10</h6>
                                                            <div class="d-flex">
                                                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                                                    <i class="fas fa-star text-<?= $i <= $assessment['score'] ? 'warning' : 'muted' ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($assessment['notes']): ?>
                                                            <p class="card-text"><strong>노트:</strong> <?= nl2br(htmlspecialchars($assessment['notes'])) ?></p>
                                                        <?php endif; ?>
                                                        <?php if ($assessment['next_goal']): ?>
                                                            <p class="card-text"><strong>다음 목표:</strong> <?= nl2br(htmlspecialchars($assessment['next_goal'])) ?></p>
                                                        <?php endif; ?>
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
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    승인되지 않은 관계입니다. 트레이너 기능을 사용하려면 먼저 관계를 승인해야 합니다.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 점수 설정
        function setScore(score) {
            document.getElementById('score').value = score;
            
            // 별점 표시 업데이트
            const stars = document.querySelectorAll('#addAssessmentForm .fa-star');
            stars.forEach((star, index) => {
                if (index < score) {
                    star.classList.remove('text-muted');
                    star.classList.add('text-warning');
                } else {
                    star.classList.remove('text-warning');
                    star.classList.add('text-muted');
                }
            });
        }

        // 운동 상세보기
        function viewWorkoutDetail(sessionId) {
            // 여기에 운동 상세보기 모달 또는 페이지 이동 로직 추가
            alert('운동 상세보기 기능은 추후 구현 예정입니다. 세션 ID: ' + sessionId);
        }

        // 페이지 로드 시 오늘 날짜를 기본값으로 설정
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            if (document.getElementById('schedule_date')) {
                document.getElementById('schedule_date').value = today;
            }
            if (document.getElementById('assessment_date')) {
                document.getElementById('assessment_date').value = today;
            }
        });
    </script>
</body>
</html>

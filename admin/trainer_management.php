<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 선택된 사용자 ID (트레이너로 가정)
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// 사용자 목록 가져오기
$users = [];
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.profile_image,
            u.email,
            u.created_at,
            COUNT(DISTINCT tr.id) as relationship_count,
            COUNT(DISTINCT CASE WHEN tr.status = 'approved' THEN tr.id END) as active_relationships
        FROM users u
        LEFT JOIN trainer_relationships tr ON u.id = tr.trainer_id
        WHERE u.is_active = 1
        GROUP BY u.id
        ORDER BY u.username
    ");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "사용자 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 선택된 사용자가 관리하는 회원 목록
$managed_members = [];
if ($selected_user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                tr.id as relationship_id,
                tr.status,
                tr.request_date,
                tr.approval_date,
                tr.termination_date,
                m.id as member_id,
                m.username as member_name,
                m.profile_image as member_image,
                m.email as member_email,
                COUNT(DISTINCT ts.id) as schedule_count,
                COUNT(DISTINCT tc.id) as comment_count,
                COUNT(DISTINCT ta.id) as assessment_count
            FROM trainer_relationships tr
            JOIN users m ON tr.member_id = m.id
            LEFT JOIN trainer_schedules ts ON tr.trainer_id = ts.trainer_id AND tr.member_id = ts.member_id
            LEFT JOIN trainer_comments tc ON tr.trainer_id = tc.trainer_id AND tr.member_id = tc.member_id
            LEFT JOIN trainer_assessments ta ON tr.trainer_id = ta.trainer_id AND tr.member_id = ta.member_id
            WHERE tr.trainer_id = ?
            GROUP BY tr.id
            ORDER BY tr.created_at DESC
        ");
        $stmt->execute([$selected_user_id]);
        $managed_members = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "관리 회원 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
    }
}

// 트레이너 관계 처리
if ($_POST) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'approve_request') {
                // 트레이너 요청 승인
                $relationship_id = $_POST['relationship_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE trainer_relationships 
                    SET status = 'approved', approval_date = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$relationship_id]);
                
                $message = "트레이너 요청이 승인되었습니다.";
                
            } elseif ($_POST['action'] === 'reject_request') {
                // 트레이너 요청 거절
                $relationship_id = $_POST['relationship_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE trainer_relationships 
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$relationship_id]);
                
                $message = "트레이너 요청이 거절되었습니다.";
                
            } elseif ($_POST['action'] === 'terminate_relationship') {
                // 트레이너 관계 종료
                $relationship_id = $_POST['relationship_id'];
                
                $stmt = $pdo->prepare("
                    UPDATE trainer_relationships 
                    SET status = 'terminated', termination_date = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$relationship_id]);
                
                $message = "트레이너 관계가 종료되었습니다.";
            }
        }
        
        $pdo->commit();
        
        // 페이지 새로고침으로 목록 업데이트
        $redirect_url = $selected_user_id ? 
            "trainer_management.php?user_id=" . $selected_user_id . "&message=" . urlencode($message) :
            "trainer_management.php?message=" . urlencode($message);
        header('Location: ' . $redirect_url);
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
    <title>트레이너 관리 - 관리자</title>
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
        .status-pending {
            color: #ffc107;
        }
        .status-approved {
            color: #28a745;
        }
        .status-rejected {
            color: #dc3545;
        }
        .status-terminated {
            color: #6c757d;
        }
        .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        .user-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .user-card.selected {
            border: 2px solid #007bff;
            background-color: #f8f9fa;
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
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-users me-3"></i>트레이너 관리
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

        <div class="row">
            <!-- 사용자 목록 -->
            <div class="col-md-4">
                <div class="trainer-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>사용자 목록
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <p class="text-muted text-center">사용자가 없습니다.</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($users as $user): ?>
                                    <div class="col-12 mb-3">
                                        <div class="card user-card <?= $selected_user_id == $user['id'] ? 'selected' : '' ?>" 
                                             onclick="selectUser(<?= $user['id'] ?>)">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-center">
                                                    <?php if ($user['profile_image']): ?>
                                                        <img src="<?= htmlspecialchars($user['profile_image']) ?>" 
                                                             alt="프로필" class="profile-image me-3">
                                                    <?php else: ?>
                                                        <div class="profile-placeholder me-3">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($user['username']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                        <div class="mt-1">
                                                            <span class="badge bg-info me-1">
                                                                총 관계: <?= $user['relationship_count'] ?>
                                                            </span>
                                                            <span class="badge bg-success">
                                                                활성: <?= $user['active_relationships'] ?>
                                                            </span>
                                                        </div>
                                                    </div>
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

            <!-- 선택된 사용자의 관리 회원 목록 -->
            <div class="col-md-8">
                <?php if ($selected_user_id): ?>
                    <?php 
                    $selected_user = array_filter($users, function($u) use ($selected_user_id) { 
                        return $u['id'] == $selected_user_id; 
                    });
                    $selected_user = reset($selected_user);
                    ?>
                    <div class="trainer-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-user-tie me-2"></i>
                                <?= htmlspecialchars($selected_user['username']) ?>님이 관리하는 회원
                            </h5>
                            <a href="trainer_management.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-2"></i>선택 해제
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($managed_members)): ?>
                                <p class="text-muted text-center">아직 관리하는 회원이 없습니다.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>회원</th>
                                                <th>상태</th>
                                                <th>요청일</th>
                                                <th>승인일</th>
                                                <th>활동</th>
                                                <th>관리</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($managed_members as $member): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if ($member['member_image']): ?>
                                                                <img src="<?= htmlspecialchars($member['member_image']) ?>" 
                                                                     alt="회원" class="profile-image me-2">
                                                            <?php else: ?>
                                                                <div class="profile-placeholder me-2">
                                                                    <i class="fas fa-user"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?= htmlspecialchars($member['member_name']) ?></div>
                                                                <small class="text-muted"><?= htmlspecialchars($member['member_email']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="status-<?= $member['status'] ?>">
                                                            <i class="fas fa-circle me-1"></i>
                                                            <?php
                                                            switch($member['status']) {
                                                                case 'pending': echo '대기중'; break;
                                                                case 'approved': echo '승인됨'; break;
                                                                case 'rejected': echo '거절됨'; break;
                                                                case 'terminated': echo '종료됨'; break;
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('Y-m-d', strtotime($member['request_date'])) ?></td>
                                                    <td>
                                                        <?= $member['approval_date'] ? date('Y-m-d', strtotime($member['approval_date'])) : '-' ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <small class="text-muted">
                                                                스케줄: <?= $member['schedule_count'] ?>개
                                                            </small>
                                                            <small class="text-muted">
                                                                코멘트: <?= $member['comment_count'] ?>개
                                                            </small>
                                                            <small class="text-muted">
                                                                평가: <?= $member['assessment_count'] ?>개
                                                            </small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <?php if ($member['status'] === 'pending'): ?>
                                                                <form method="post" class="d-inline" style="display: inline;">
                                                                    <input type="hidden" name="action" value="approve_request">
                                                                    <input type="hidden" name="relationship_id" value="<?= $member['relationship_id'] ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                                            onclick="return confirm('이 요청을 승인하시겠습니까?')">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                                <form method="post" class="d-inline" style="display: inline;">
                                                                    <input type="hidden" name="action" value="reject_request">
                                                                    <input type="hidden" name="relationship_id" value="<?= $member['relationship_id'] ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                                            onclick="return confirm('이 요청을 거절하시겠습니까?')">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($member['status'] === 'approved'): ?>
                                                                <a href="trainer_detail.php?relationship_id=<?= $member['relationship_id'] ?>" 
                                                                   class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="post" class="d-inline" style="display: inline;">
                                                                    <input type="hidden" name="action" value="terminate_relationship">
                                                                    <input type="hidden" name="relationship_id" value="<?= $member['relationship_id'] ?>">
                                                                    <button type="submit" class="btn btn-warning btn-sm" 
                                                                            onclick="return confirm('이 관계를 종료하시겠습니까?')">
                                                                        <i class="fas fa-stop"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="trainer-card">
                        <div class="card-body text-center">
                            <i class="fas fa-hand-point-left fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">사용자를 선택하세요</h5>
                            <p class="text-muted">왼쪽에서 사용자를 선택하면 해당 사용자가 관리하는 회원 목록을 볼 수 있습니다.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectUser(userId) {
            window.location.href = 'trainer_management.php?user_id=' + userId;
        }
    </script>
</body>
</html>

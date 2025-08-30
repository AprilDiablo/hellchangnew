<?php
// 세션 시작
session_start();

// 인증 확인
require_once 'auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 사용자 인증 확인
$user = requireUserAuth();
if (!$user) {
    header('Location: login.php');
    exit;
}

$user_id = $user['id'];

// 사용자 정보 가져오기
$user = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $error = "사용자 정보를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 현재 트레이너 관계 상태 가져오기
$current_relationships = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            tr.*,
            t.username as trainer_name,
            t.profile_image as trainer_image,
            m.username as member_name,
            m.profile_image as member_image
        FROM trainer_relationships tr
        JOIN users t ON tr.trainer_id = t.id
        JOIN users m ON tr.member_id = m.id
        WHERE tr.trainer_id = ? OR tr.member_id = ?
        ORDER BY tr.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $current_relationships = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "트레이너 관계를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 다른 사용자 목록 가져오기 (트레이너 요청용)
$other_users = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, username, profile_image, email
        FROM users 
        WHERE id != ? AND is_active = 1
        ORDER BY username
    ");
    $stmt->execute([$user_id]);
    $other_users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "사용자 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 트레이너 요청 처리
if ($_POST) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'request_trainer') {
                // 트레이너 요청 생성
                $trainer_id = $_POST['trainer_id'];
                $member_id = $user_id;
                
                // 중복 요청 확인
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_relationships WHERE trainer_id = ? AND member_id = ?");
                $stmt->execute([$trainer_id, $member_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("이미 존재하는 트레이너 관계입니다.");
                }
                
                // 자기 자신을 트레이너로 요청하는지 확인
                if ($trainer_id == $member_id) {
                    throw new Exception("자기 자신을 트레이너로 요청할 수 없습니다.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_relationships (trainer_id, member_id, status)
                    VALUES (?, ?, 'pending')
                ");
                $stmt->execute([$trainer_id, $member_id]);
                
                // 기본 권한 설정
                $stmt = $pdo->prepare("
                    INSERT INTO trainer_permissions (trainer_id, member_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$trainer_id, $member_id]);
                
                $message = "트레이너 요청이 성공적으로 전송되었습니다.";
                
            } elseif ($_POST['action'] === 'approve_request') {
                // 트레이너 요청 승인 (트레이너가 요청을 승인)
                $relationship_id = $_POST['relationship_id'];
                
                // 현재 사용자가 트레이너인지 확인
                $stmt = $pdo->prepare("SELECT trainer_id FROM trainer_relationships WHERE id = ? AND trainer_id = ?");
                $stmt->execute([$relationship_id, $user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("승인 권한이 없습니다.");
                }
                
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
                
                // 현재 사용자가 트레이너인지 확인
                $stmt = $pdo->prepare("SELECT trainer_id FROM trainer_relationships WHERE id = ? AND trainer_id = ?");
                $stmt->execute([$relationship_id, $user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("거절 권한이 없습니다.");
                }
                
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
                
                // 현재 사용자가 관계에 포함되어 있는지 확인
                $stmt = $pdo->prepare("SELECT * FROM trainer_relationships WHERE id = ? AND (trainer_id = ? OR member_id = ?)");
                $stmt->execute([$relationship_id, $user_id, $user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("관계 종료 권한이 없습니다.");
                }
                
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
        header('Location: trainer_request.php?message=' . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<?php
$pageTitle = "트레이너 요청";
require_once 'header.php';
?>

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
    }
    .relationship-card {
        border-left: 4px solid #007bff;
    }
    .relationship-card.approved {
        border-left-color: #28a745;
    }
    .relationship-card.pending {
        border-left-color: #ffc107;
    }
    .relationship-card.rejected {
        border-left-color: #dc3545;
    }
    .relationship-card.terminated {
        border-left-color: #6c757d;
    }
</style>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="my_workouts.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>내 운동으로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-dumbbell me-3"></i>트레이너 요청
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

        <!-- 새 트레이너 요청 버튼 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#requestTrainerForm">
                <i class="fas fa-plus me-2"></i>새 트레이너 요청
            </button>
        </div>

        <!-- 새 트레이너 요청 폼 (숨김) -->
        <div class="collapse" id="requestTrainerForm">
            <div class="trainer-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-plus me-2"></i>트레이너 요청하기
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="request_trainer">
                        
                        <div class="col-12">
                            <label for="trainer_id" class="form-label">트레이너로 요청할 사용자 *</label>
                            <select class="form-select" id="trainer_id" name="trainer_id" required>
                                <option value="">사용자 선택</option>
                                <?php foreach ($other_users as $other_user): ?>
                                    <option value="<?= $other_user['id'] ?>">
                                        <?= htmlspecialchars($other_user['username']) ?> (<?= htmlspecialchars($other_user['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>트레이너 요청이란?</strong><br>
                                선택한 사용자가 당신의 트레이너가 되어 운동 기록을 확인하고, 
                                스케줄을 관리하며, 코멘트와 평가를 제공할 수 있습니다.
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>트레이너 요청 보내기
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 현재 트레이너 관계 목록 -->
        <div class="trainer-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>현재 트레이너 관계
                    <span class="badge bg-primary ms-2"><?= count($current_relationships) ?>개</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($current_relationships)): ?>
                    <p class="text-muted text-center">아직 트레이너 관계가 없습니다.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($current_relationships as $rel): ?>
                            <?php 
                            $is_trainer = ($rel['trainer_id'] == $user_id);
                            $other_user_id = $is_trainer ? $rel['member_id'] : $rel['trainer_id'];
                            $other_user_name = $is_trainer ? $rel['member_name'] : $rel['trainer_name'];
                            $other_user_image = $is_trainer ? $rel['member_image'] : $rel['trainer_image'];
                            $relationship_type = $is_trainer ? 'trainer' : 'member';
                            ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card relationship-card <?= $rel['status'] ?>">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?= $relationship_type === 'trainer' ? 'success' : 'primary' ?>">
                                            <?= $relationship_type === 'trainer' ? '트레이너' : '회원' ?>
                                        </span>
                                        <span class="status-<?= $rel['status'] ?>">
                                            <i class="fas fa-circle me-1"></i>
                                            <?php
                                            switch($rel['status']) {
                                                case 'pending': echo '대기중'; break;
                                                case 'approved': echo '승인됨'; break;
                                                case 'rejected': echo '거절됨'; break;
                                                case 'terminated': echo '종료됨'; break;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if ($other_user_image): ?>
                                                <img src="<?= htmlspecialchars($other_user_image) ?>" 
                                                     alt="사용자" class="profile-image me-3">
                                            <?php else: ?>
                                                <div class="profile-placeholder me-3">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($other_user_name) ?></h6>
                                                <small class="text-muted">
                                                    <?= $relationship_type === 'trainer' ? '당신의 회원' : '당신의 트레이너' ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                요청일: <?= date('Y-m-d', strtotime($rel['request_date'])) ?><br>
                                                <?php if ($rel['approval_date']): ?>
                                                    승인일: <?= date('Y-m-d', strtotime($rel['approval_date'])) ?><br>
                                                <?php endif; ?>
                                                <?php if ($rel['termination_date']): ?>
                                                    종료일: <?= date('Y-m-d', strtotime($rel['termination_date'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <?php if ($rel['status'] === 'pending' && $relationship_type === 'trainer'): ?>
                                                <button class="btn btn-sm btn-success" onclick="approveRequest(<?= $rel['id'] ?>)">
                                                    <i class="fas fa-check me-1"></i>승인
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?= $rel['id'] ?>)">
                                                    <i class="fas fa-times me-1"></i>거절
                                                </button>
                                            <?php elseif ($rel['status'] === 'approved'): ?>
                                                <button class="btn btn-sm btn-secondary" onclick="terminateRelationship(<?= $rel['id'] ?>)">
                                                    <i class="fas fa-stop me-1"></i>관계 종료
                                                </button>
                                                <a href="trainer_dashboard.php?relationship_id=<?= $rel['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>관리하기
                                                </a>
                                            <?php endif; ?>
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

    <!-- 승인 확인 모달 -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">트레이너 요청 승인</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>이 트레이너 요청을 승인하시겠습니까?</p>
                    <p class="text-success"><small>승인 후 회원의 운동 자료를 확인하고 관리할 수 있습니다.</small></p>
                </div>
                <div class="modal-footer">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="approve_request">
                        <input type="hidden" name="relationship_id" id="approve_relationship_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-success">승인</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 거절 확인 모달 -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">트레이너 요청 거절</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>이 트레이너 요청을 거절하시겠습니까?</p>
                    <p class="text-danger"><small>거절된 요청은 되돌릴 수 없습니다.</small></p>
                </div>
                <div class="modal-footer">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="relationship_id" id="reject_relationship_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-danger">거절</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 관계 종료 확인 모달 -->
    <div class="modal fade" id="terminateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">트레이너 관계 종료</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>이 트레이너 관계를 종료하시겠습니까?</p>
                    <p class="text-warning"><small>종료 후 상대방의 자료에 접근할 수 없습니다.</small></p>
                </div>
                <div class="modal-footer">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="terminate_relationship">
                        <input type="hidden" name="relationship_id" id="terminate_relationship_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-warning">종료</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 트레이너 요청 승인
        function approveRequest(relationshipId) {
            document.getElementById('approve_relationship_id').value = relationshipId;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        // 트레이너 요청 거절
        function rejectRequest(relationshipId) {
            document.getElementById('reject_relationship_id').value = relationshipId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        // 트레이너 관계 종료
        function terminateRelationship(relationshipId) {
            document.getElementById('terminate_relationship_id').value = relationshipId;
            new bootstrap.Modal(document.getElementById('terminateModal')).show();
        }
    </script>

<?php require_once 'footer.php'; ?>

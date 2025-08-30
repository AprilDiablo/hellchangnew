<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 사용자 목록 가져오기
$users = [];
try {
    $stmt = $pdo->query("
        SELECT 
            u.id as user_id,
            u.username,
            u.email,
            u.username as full_name,
            u.kakao_id,
            u.profile_image,
            'user' as role,
            CASE WHEN u.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
            u.created_at,
            u.updated_at as last_login,
            COUNT(DISTINCT ws.session_id) as workout_count
        FROM users u
        LEFT JOIN m_workout_session ws ON u.id = ws.user_id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "사용자 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 사용자 추가/수정 처리
if ($_POST) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // 새 사용자 추가
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // 중복 확인
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("이미 존재하는 사용자명 또는 이메일입니다.");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, is_active, created_at)
                    VALUES (?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$username, $email, $password]);
                
                $message = "사용자가 성공적으로 추가되었습니다.";
                
            } elseif ($_POST['action'] === 'edit') {
                // 사용자 수정
                $user_id = $_POST['user_id'];
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?, username = ?, is_active = ?
                    WHERE id = ?
                ");
                $is_active = ($status === 'active') ? 1 : 0;
                $stmt->execute([$email, $full_name, $is_active, $user_id]);
                
                // 비밀번호 변경이 있는 경우
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$password, $user_id]);
                }
                
                $message = "사용자 정보가 성공적으로 수정되었습니다.";
                
            } elseif ($_POST['action'] === 'delete') {
                // 사용자 삭제
                $user_id = $_POST['user_id'];
                
                // 사용자 관련 데이터 확인
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_workout_session WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("운동 기록이 있는 사용자는 삭제할 수 없습니다. 먼저 운동 기록을 삭제해주세요.");
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $message = "사용자가 성공적으로 삭제되었습니다.";
            }
        }
        
        $pdo->commit();
        
        // 페이지 새로고침으로 목록 업데이트
        header('Location: user_management.php?message=' . urlencode($message));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// 사용자 정보 가져오기 (AJAX용)
if (isset($_GET['action']) && $_GET['action'] === 'get_user') {
    try {
        $user_id = $_GET['user_id'];
        $stmt = $pdo->prepare("SELECT id as user_id, username, email, username as full_name, 'user' as role, CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END as status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($user);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>사용자 관리 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .user-card .card-body {
            padding: 1.5rem;
        }
        .user-card .card-header {
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
        .status-active {
            color: #28a745;
        }
        .status-inactive {
            color: #dc3545;
        }
        .role-admin {
            background: #dc3545;
        }
        .role-user {
            background: #28a745;
        }
        .role-moderator {
            background: #ffc107;
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
                <i class="fas fa-users me-3"></i>사용자 관리
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

        <!-- 새 사용자 추가 버튼 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm">
                <i class="fas fa-user-plus me-2"></i>새 사용자 추가
            </button>
        </div>

        <!-- 새 사용자 추가 폼 (숨김) -->
        <div class="collapse" id="addUserForm">
            <div class="user-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>새 사용자 추가
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="col-md-3">
                            <label for="username" class="form-label">사용자명 *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="email" class="form-label">이메일 *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="full_name" class="form-label">이름 *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="role" class="form-label">권한 *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="user">일반 사용자</option>
                                <option value="moderator">모더레이터</option>
                                <option value="admin">관리자</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="password" class="form-label">비밀번호 *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>사용자 추가
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 사용자 목록 -->
        <div class="user-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>사용자 목록
                    <span class="badge bg-primary ms-2"><?= count($users) ?>명</span>
                </h5>
            </div>
            <div class="card-body">
                <!-- 검색 및 필터 -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="searchInput" placeholder="이름, 이메일, 카카오 ID로 검색...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">모든 상태</option>
                            <option value="active">활성</option>
                            <option value="inactive">비활성</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="roleFilter">
                            <option value="">모든 권한</option>
                            <option value="user">일반 사용자</option>
                            <option value="moderator">모더레이터</option>
                            <option value="admin">관리자</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary" type="button" onclick="resetFilters()">
                            <i class="fas fa-undo me-1"></i>초기화
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <div class="mb-2">
                        <small class="text-muted">검색 결과: <span id="resultCount"><?= count($users) ?></span>명</small>
                    </div>
                    <table class="table table-hover" id="userTable">
                        <thead>
                            <tr>
                                <th></th>
                                <th>카카오별명</th>
                                <th>이메일</th>
                                <th>카카오 ID</th>
                                <th>권한</th>
                                <th>상태</th>
                                <th>운동 기록</th>
                                <th>가입일</th>
                                <th>마지막 로그인</th>
                                <th>관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr data-username="<?= htmlspecialchars($user['username']) ?>" 
                                    data-email="<?= htmlspecialchars($user['email']) ?>" 
                                    data-full-name="<?= htmlspecialchars($user['full_name']) ?>" 
                                    data-kakao-id="<?= $user['kakao_id'] ?: '' ?>" 
                                    data-role="<?= $user['role'] ?>" 
                                    data-status="<?= $user['status'] ?>">
                                    <td>
                                        <?php if ($user['profile_image']): ?>
                                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" 
                                                 alt="프로필" 
                                                 class="rounded-circle" 
                                                 width="40" 
                                                 height="40"
                                                 style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <?php if ($user['kakao_id']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-comment me-1"></i><?= $user['kakao_id'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge role-<?= $user['role'] ?>">
                                            <?= $user['role'] === 'admin' ? '관리자' : 
                                                ($user['role'] === 'moderator' ? '모더레이터' : '일반사용자') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?= $user['status'] ?>">
                                            <i class="fas fa-circle me-1"></i>
                                            <?= $user['status'] === 'active' ? '활성' : '비활성' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $user['workout_count'] ?>개</span>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : '없음' ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['user_id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 사용자 수정 모달 -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">사용자 정보 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">사용자명</label>
                            <input type="text" class="form-control" id="edit_username" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">이메일 *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">이름 *</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">권한 *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="user">일반 사용자</option>
                                <option value="moderator">모더레이터</option>
                                <option value="admin">관리자</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">상태 *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">활성</option>
                                <option value="inactive">비활성</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">새 비밀번호</label>
                            <input type="password" class="form-control" id="edit_password" name="password" placeholder="변경하지 않으려면 비워두세요">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">수정</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 사용자 삭제 확인 모달 -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">사용자 삭제 확인</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>정말로 <strong id="delete_username"></strong> 사용자를 삭제하시겠습니까?</p>
                    <p class="text-danger"><small>이 작업은 되돌릴 수 없습니다.</small></p>
                </div>
                <div class="modal-footer">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-danger">삭제</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 사용자 수정
        function editUser(userId) {
            fetch(`?action=get_user&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('오류: ' + data.error);
                        return;
                    }
                    
                    document.getElementById('edit_user_id').value = data.user_id;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_full_name').value = data.full_name;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('edit_password').value = '';
                    
                    new bootstrap.Modal(document.getElementById('editUserModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('사용자 정보를 가져오는 중 오류가 발생했습니다.');
                });
        }

        // 사용자 삭제
        function deleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_username').textContent = username;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }

        // 검색 및 필터링
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const roleFilter = document.getElementById('roleFilter').value;
            
            const rows = document.querySelectorAll('#userTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const email = row.getAttribute('data-email').toLowerCase();
                const fullName = row.getAttribute('data-full-name').toLowerCase();
                const kakaoId = row.getAttribute('data-kakao-id').toLowerCase();
                const role = row.getAttribute('data-role');
                const status = row.getAttribute('data-status');
                
                let showRow = true;
                
                // 검색어 필터
                if (searchTerm && !email.includes(searchTerm) && !fullName.includes(searchTerm) && !kakaoId.includes(searchTerm)) {
                    showRow = false;
                }
                
                // 상태 필터
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }
                
                // 권한 필터
                if (roleFilter && role !== roleFilter) {
                    showRow = false;
                }
                
                if (showRow) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('resultCount').textContent = visibleCount;
        }

        // 필터 초기화
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('roleFilter').value = '';
            filterUsers();
        }

        // 이벤트 리스너 등록
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('input', filterUsers);
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('roleFilter').addEventListener('change', filterUsers);
        });
    </script>
</body>
</html>

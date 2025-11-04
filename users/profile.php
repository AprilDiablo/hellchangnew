<?php
// AJAX 요청 처리 (header.php 로드 전에 처리)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once 'auth_check.php';
    
    $user = getCurrentUser();
    header('Content-Type: application/json');
    
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'save_routine_settings') {
            // 프리/엔드루틴 설정 저장
            $pre_routine = $_POST['pre_routine'] ?? '';
            $post_routine = $_POST['post_routine'] ?? '';
            $pre_routine_enabled = isset($_POST['pre_routine_enabled']) ? 1 : 0;
            $post_routine_enabled = isset($_POST['post_routine_enabled']) ? 1 : 0;
            
            // 기존 설정 확인
            $stmt = $pdo->prepare("SELECT id FROM m_routine_settings WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 기존 설정 업데이트
                $stmt = $pdo->prepare("UPDATE m_routine_settings SET pre_routine = ?, post_routine = ?, pre_routine_enabled = ?, post_routine_enabled = ? WHERE user_id = ?");
                $stmt->execute([$pre_routine, $post_routine, $pre_routine_enabled, $post_routine_enabled, $user['id']]);
            } else {
                // 새 설정 생성
                $stmt = $pdo->prepare("INSERT INTO m_routine_settings (user_id, pre_routine, post_routine, pre_routine_enabled, post_routine_enabled) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $pre_routine, $post_routine, $pre_routine_enabled, $post_routine_enabled]);
            }
            
            $response = ['success' => true, 'message' => '루틴 설정이 저장되었습니다.'];
            
        } elseif ($_POST['action'] === 'update_profile') {
            // 프로필 정보 업데이트
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            
            if (empty($username)) {
                throw new Exception('사용자명을 입력해주세요.');
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('올바른 이메일을 입력해주세요.');
            }
            
            // 이메일 중복 확인 (자신 제외)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                throw new Exception('이미 사용 중인 이메일입니다.');
            }
            
            // 프로필 업데이트
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$username, $email, $user['id']]);
            
            // 세션 정보 업데이트
            $_SESSION['username'] = $username;
            
            $response = ['success' => true, 'message' => '프로필이 업데이트되었습니다.'];
        }
        
        $pdo->commit();
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} else {
    // 일반 페이지 로딩
    $pageTitle = "설정";
    require_once 'header.php';
    
    $user = getCurrentUser();
    
    // 현재 루틴 설정 가져오기
$pdo = getDB();
$stmt = $pdo->prepare("SELECT pre_routine, post_routine, pre_routine_enabled, post_routine_enabled FROM m_routine_settings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$routineSettings = $stmt->fetch(PDO::FETCH_ASSOC);

$preRoutine = $routineSettings['pre_routine'] ?? '';
$postRoutine = $routineSettings['post_routine'] ?? '';
$preRoutineEnabled = $routineSettings['pre_routine_enabled'] ?? 1;
$postRoutineEnabled = $routineSettings['post_routine_enabled'] ?? 1;
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4"><i class="fas fa-user-cog"></i> 설정</h2>
        </div>
    </div>
    
    <div class="row">
        <!-- 프로필 정보 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user"></i> 프로필 정보</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">사용자명</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">이메일</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">가입일</label>
                            <input type="text" class="form-control" value="<?= date('Y-m-d H:i', strtotime($user['created_at'])) ?>" readonly>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 프로필 저장
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 루틴 설정 -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-dumbbell"></i> 루틴 설정</h5>
                </div>
                <div class="card-body">
                    <form id="routineForm">
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="pre_routine_enabled" name="pre_routine_enabled" 
                                       <?= $preRoutineEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="pre_routine_enabled">
                                    프리루틴 사용
                                </label>
                            </div>
                            <label for="pre_routine" class="form-label">프리루틴 (운동 전)</label>
                            <textarea class="form-control" id="pre_routine" name="pre_routine" rows="4" 
                                      placeholder="운동 전 루틴을 입력하세요...&#10;예: 맨몸스쿼트 100개, 트레드밀 10분"><?= htmlspecialchars($preRoutine) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="post_routine_enabled" name="post_routine_enabled" 
                                       <?= $postRoutineEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="post_routine_enabled">
                                    엔드루틴 사용
                                </label>
                            </div>
                            <label for="post_routine" class="form-label">엔드루틴 (운동 후)</label>
                            <textarea class="form-control" id="post_routine" name="post_routine" rows="4" 
                                      placeholder="운동 후 루틴을 입력하세요...&#10;예: 스트레칭 10분, 샤워"><?= htmlspecialchars($postRoutine) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> 루틴 저장
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 기타 설정 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> 기타 설정</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>데이터 관리</h6>
                            <p class="text-muted">운동 기록 데이터를 관리합니다.</p>
                            <button class="btn btn-outline-info btn-sm" onclick="exportData()">
                                <i class="fas fa-download"></i> 데이터 내보내기
                            </button>
                        </div>
                        <div class="col-md-6">
                            <h6>계정 관리</h6>
                            <p class="text-muted">계정 관련 설정을 관리합니다.</p>
                            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-sign-out-alt"></i> 로그아웃
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 메시지 표시 영역 -->
<div id="messageContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script>
// 프로필 저장
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_profile');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('오류가 발생했습니다.', 'error');
    });
});

// 루틴 저장
document.getElementById('routineForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'save_routine_settings');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('오류가 발생했습니다.', 'error');
    });
});

// 데이터 내보내기
function exportData() {
    showMessage('데이터 내보내기 기능은 준비 중입니다.', 'info');
}

// 메시지 표시 함수
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(alertDiv);
    
    // 3초 후 자동 제거
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}
</script>

<style>
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.form-control:focus {
    border-color: #4e73df;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

#messageContainer .alert {
    min-width: 300px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
</style>

<?php require_once 'footer.php'; ?>

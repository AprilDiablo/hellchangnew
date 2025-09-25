<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 사용자 목록 가져오기
$stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE is_active = 1 ORDER BY username ASC');
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 선택된 사용자
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// 선택된 사용자 정보
$selectedUser = null;
if ($selected_user_id) {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$selected_user_id]);
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 사용자가 선택되었으면 개별 달력 페이지로 리다이렉트
    if ($selectedUser) {
        header("Location: user_calendar.php?user_id={$selected_user_id}");
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
    <title>개인 운동 이력 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exercise-card {
            border-left: 4px solid #007bff;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* 달력 스타일 */
        .week-row {
            margin-bottom: 8px;
        }
        
        .week-days {
            display: flex;
            gap: 4px;
            justify-content: center;
            padding: 10px 0;
        }
        
        .day-cell {
            width: 80px;
            height: 100px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
            position: relative;
        }
        
        .day-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .day-cell.has-workout {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-color: #28a745;
            color: white;
        }
        
        .day-cell.current-month {
            opacity: 1;
        }
        
        .day-cell.other-month {
            opacity: 0.4;
        }
        
        .day-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .day-number {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .workout-indicator {
            position: absolute;
            bottom: 4px;
            font-size: 12px;
        }
        
        /* 토요일 스타일 (하늘색) */
        .day-cell.saturday {
            color: #17a2b8 !important;
            border-color: #17a2b8 !important;
        }
        
        .day-cell.saturday.has-workout {
            background: linear-gradient(135deg, #17a2b8, #20c997) !important;
            border-color: #17a2b8 !important;
            color: white !important;
        }
        
        /* 일요일 스타일 (빨간색) */
        .day-cell.sunday {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        
        .day-cell.sunday.has-workout {
            background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .user-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .user-card.border-primary {
            border-color: #007bff !important;
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
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
                        <h2><i class="fas fa-user-clock me-2"></i>개인 운동 이력</h2>
                        <p class="text-muted">사용자별 상세한 운동 이력을 달력으로 확인합니다</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
                    </a>
                </div>
            </div>
        </div>

        <!-- 사용자 선택 -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>사용자 선택</h5>
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
                                                    onclick="event.stopPropagation(); selectUser(<?= $user['id'] ?>)">
                                                <i class="fas fa-calendar-alt me-1"></i>보기
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
    </script>
</body>
</html>

<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// AJAX 요청 처리 (유사어 목록 가져오기)
if (isset($_GET['action']) && $_GET['action'] === 'get_aliases') {
    $exId = (int)($_GET['ex_id'] ?? 0);
    
    if ($exId > 0) {
        $stmt = $pdo->prepare("SELECT alias FROM m_exercise_alias WHERE ex_id = ? ORDER BY alias");
        $stmt->execute([$exId]);
        $aliases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'aliases' => $aliases]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '운동 ID가 필요합니다.']);
        exit;
    }
}

// 상태별 필터링
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$validStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $validStatuses)) {
    $status = 'pending';
}

// 페이지네이션
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 검색어
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 임시 운동 목록 조회
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = "te.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $whereConditions[] = "(te.exercise_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// 전체 개수 조회
$countSql = "
    SELECT COUNT(*) as total
    FROM m_temp_exercise te
    LEFT JOIN users u ON te.user_id = u.id
    $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCount / $limit);

// 임시 운동 목록 조회
$sql = "
    SELECT 
        te.temp_ex_id,
        te.exercise_name,
        te.status,
        te.created_at,
        te.updated_at,
        te.approved_ex_id,
        u.username,
        u.email,
        e.name_kr as approved_exercise_name,
        COUNT(we.wx_id) as usage_count
    FROM m_temp_exercise te
    LEFT JOIN users u ON te.user_id = u.id
    LEFT JOIN m_exercise e ON te.approved_ex_id = e.ex_id
    LEFT JOIN m_workout_exercise we ON te.temp_ex_id = we.temp_ex_id
    $whereClause
    GROUP BY te.temp_ex_id, te.exercise_name, te.status, te.created_at, te.updated_at, te.approved_ex_id, u.username, u.email, e.name_kr
    ORDER BY te.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tempExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 상태별 통계
$statsSql = "
    SELECT 
        status,
        COUNT(*) as count
    FROM m_temp_exercise
    GROUP BY status
";
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute();
$stats = [];
while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $stats[$row['status']] = $row['count'];
}

// 최근 7일간 임시 운동 등록 통계
$recentStatsSql = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM m_temp_exercise
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
";
$recentStatsStmt = $pdo->prepare($recentStatsSql);
$recentStatsStmt->execute();
$recentStats = $recentStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// 정식 운동 목록 (승인 시 연결용) - 별칭도 함께 조회
$exerciseSql = "
    SELECT DISTINCT e.ex_id, e.name_kr, e.name_en
    FROM m_exercise e
    LEFT JOIN m_exercise_alias a ON e.ex_id = a.ex_id
    ORDER BY e.name_kr
";
$exerciseStmt = $pdo->prepare($exerciseSql);
$exerciseStmt->execute();
$exercises = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);

// 별칭 목록도 함께 조회 (검색용)
$aliasSql = "SELECT alias, ex_id FROM m_exercise_alias";
$aliasStmt = $pdo->prepare($aliasSql);
$aliasStmt->execute();
$aliases = $aliasStmt->fetchAll(PDO::FETCH_ASSOC);

// 유사어 관리 액션 처리 (AJAX)
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_alias') {
        // 유사어 추가 (AJAX 요청)
        header('Content-Type: application/json');
        $exId = (int)($_POST['ex_id'] ?? 0);
        $alias = trim($_POST['alias'] ?? '');
        
        if ($exId > 0 && !empty($alias)) {
            try {
                $pdo->beginTransaction();
                // 중복 확인
                $checkStmt = $pdo->prepare("SELECT alias FROM m_exercise_alias WHERE alias = ?");
                $checkStmt->execute([$alias]);
                if ($checkStmt->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => '이미 등록된 유사어입니다.']);
                    exit;
                }
                
                $insertStmt = $pdo->prepare("INSERT INTO m_exercise_alias (alias, ex_id) VALUES (?, ?)");
                $insertStmt->execute([$alias, $exId]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => '유사어가 추가되었습니다.']);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => '운동과 유사어를 입력해주세요.']);
            exit;
        }
    } elseif ($action === 'update_alias') {
        // 유사어 수정 (AJAX 요청)
        header('Content-Type: application/json');
        $oldAlias = trim($_POST['old_alias'] ?? '');
        $newAlias = trim($_POST['new_alias'] ?? '');
        $exId = (int)($_POST['ex_id'] ?? 0);
        
        if (!empty($oldAlias) && !empty($newAlias) && $exId > 0) {
            try {
                $pdo->beginTransaction();
                // 중복 확인 (자기 자신 제외)
                $checkStmt = $pdo->prepare("SELECT alias FROM m_exercise_alias WHERE alias = ? AND alias != ?");
                $checkStmt->execute([$newAlias, $oldAlias]);
                if ($checkStmt->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => '이미 등록된 유사어입니다.']);
                    exit;
                }
                
                // 기존 유사어 삭제 후 새로 추가
                $deleteStmt = $pdo->prepare("DELETE FROM m_exercise_alias WHERE alias = ?");
                $deleteStmt->execute([$oldAlias]);
                
                $insertStmt = $pdo->prepare("INSERT INTO m_exercise_alias (alias, ex_id) VALUES (?, ?)");
                $insertStmt->execute([$newAlias, $exId]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => '유사어가 수정되었습니다.']);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => '유사어를 입력해주세요.']);
            exit;
        }
    } elseif ($action === 'delete_alias') {
        // 유사어 삭제 (AJAX 요청)
        header('Content-Type: application/json');
        $alias = trim($_POST['alias'] ?? '');
        
        if (!empty($alias)) {
            try {
                $pdo->beginTransaction();
                $deleteStmt = $pdo->prepare("DELETE FROM m_exercise_alias WHERE alias = ?");
                $deleteStmt->execute([$alias]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => '유사어가 삭제되었습니다.']);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => '유사어를 선택해주세요.']);
            exit;
        }
    }
}

// 처리 액션 (승인/거부)
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    // 유사어 관리 액션은 이미 처리됨
    if ($action === 'add_alias' || $action === 'update_alias' || $action === 'delete_alias') {
        // 이미 처리됨, 여기서는 건너뜀
    } else {
        $tempExId = (int)($_POST['temp_ex_id'] ?? 0);
        
        if ($tempExId > 0) {
            try {
                $pdo->beginTransaction();
            
            if ($action === 'approve') {
                $approvedExId = (int)($_POST['approved_ex_id'] ?? 0);
                if ($approvedExId > 0) {
                    // 선택한 운동의 올바른 이름 가져오기
                    $exerciseStmt = $pdo->prepare("SELECT name_kr FROM m_exercise WHERE ex_id = ?");
                    $exerciseStmt->execute([$approvedExId]);
                    $exercise = $exerciseStmt->fetch(PDO::FETCH_ASSOC);
                    $correctExerciseName = $exercise['name_kr'] ?? '';
                    
                    // 기존 운동과 연결 (오타 수정: 올바른 운동명으로 업데이트)
                    $updateSql = "UPDATE m_temp_exercise SET status = 'approved', approved_ex_id = ?, exercise_name = ? WHERE temp_ex_id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$approvedExId, $correctExerciseName, $tempExId]);
                    
                    // 임시 운동을 사용하는 모든 운동 기록을 정식 운동으로 변경
                    // original_exercise_name도 올바른 이름으로 업데이트 (오타 수정)
                    $updateWorkoutSql = "UPDATE m_workout_exercise SET ex_id = ?, temp_ex_id = NULL, is_temp = 0, original_exercise_name = ? WHERE temp_ex_id = ?";
                    $updateWorkoutStmt = $pdo->prepare($updateWorkoutSql);
                    $updateWorkoutStmt->execute([$approvedExId, $correctExerciseName, $tempExId]);
                    
                    $message = "임시 운동이 기존 운동과 연결되었습니다. (오타가 수정되었습니다)";
                } else {
                    // 새 운동으로 등록
                    $exerciseName = trim($_POST['exercise_name'] ?? '');
                    $equipment = trim($_POST['equipment'] ?? '');
                    $equipmentKr = trim($_POST['equipment_kr'] ?? '');
                    $angle = trim($_POST['angle'] ?? '');
                    $angleKr = trim($_POST['angle_kr'] ?? '');
                    $movement = trim($_POST['movement'] ?? '');
                    $movementKr = trim($_POST['movement_kr'] ?? '');
                    $note = trim($_POST['note'] ?? '');
                    
                    if (!empty($exerciseName)) {
                        // 새 운동 등록
                        $insertSql = "INSERT INTO m_exercise (name_kr, name_en, equipment, equipment_kr, angle, angle_kr, movement, movement_kr, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute([$exerciseName, $exerciseName, $equipment, $equipmentKr, $angle, $angleKr, $movement, $movementKr, $note]);
                        $newExId = $pdo->lastInsertId();
                        
                        // 임시 운동 상태 업데이트
                        $updateSql = "UPDATE m_temp_exercise SET status = 'approved', approved_ex_id = ? WHERE temp_ex_id = ?";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute([$newExId, $tempExId]);
                        
                        // 임시 운동을 사용하는 모든 운동 기록을 정식 운동으로 변경
                        $updateWorkoutSql = "UPDATE m_workout_exercise SET ex_id = ?, temp_ex_id = NULL, is_temp = 0 WHERE temp_ex_id = ?";
                        $updateWorkoutStmt = $pdo->prepare($updateWorkoutSql);
                        $updateWorkoutStmt->execute([$newExId, $tempExId]);
                        
                        $message = "새로운 운동으로 등록되었습니다.";
                    } else {
                        throw new Exception("운동명을 입력해주세요.");
                    }
                }
            } elseif ($action === 'reject') {
                $rejectReason = trim($_POST['reject_reason'] ?? '');
                $updateSql = "UPDATE m_temp_exercise SET status = 'rejected' WHERE temp_ex_id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$tempExId]);
                
                $message = "임시 운동이 거부되었습니다.";
            }
            
            $pdo->commit();
            header("Location: temp_exercise_management.php?status=$status&page=$page&message=" . urlencode($message));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
        }
    }
}

$message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>임시 운동 관리 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .menu-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #667eea;
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
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        /* 검색 리스트 스타일 */
        .exercise-list-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .exercise-list-item:hover {
            background-color: #f8f9fa;
        }
        .exercise-list-item.selected {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
        </a>
        
        <h1 class="text-center mb-5">
            <i class="fas fa-clock me-3"></i>임시 운동 관리
        </h1>

        <!-- 메시지 표시 -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 통계 카드 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-2"><?= $stats['pending'] ?? 0 ?></h3>
                    <p class="mb-0">승인 대기</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-2"><?= $stats['approved'] ?? 0 ?></h3>
                    <p class="mb-0">승인됨</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-2"><?= $stats['rejected'] ?? 0 ?></h3>
                    <p class="mb-0">거부됨</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-center">
                    <h3 class="mb-2"><?= $totalCount ?></h3>
                    <p class="mb-0">전체</p>
                </div>
            </div>
        </div>

        <!-- 최근 7일 통계 -->
        <?php if (!empty($recentStats)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="menu-card">
                    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>최근 7일간 임시 운동 등록 현황</h5>
                    <div class="row">
                        <?php foreach ($recentStats as $stat): ?>
                        <div class="col-md-2 col-sm-4 col-6 mb-2">
                            <div class="text-center">
                                <div class="badge bg-info fs-6"><?= $stat['count'] ?>개</div>
                                <div class="small text-muted"><?= date('m/d', strtotime($stat['date'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 필터 및 검색 -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>필터 및 검색</h5>
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">상태</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>전체</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>승인 대기</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>승인됨</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>거부됨</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">검색</label>
                    <input type="text" name="search" class="form-control" placeholder="운동명 또는 사용자명으로 검색" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>검색
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 임시 운동 목록 -->
        <div class="table-card">
            <div class="p-4 border-bottom">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>임시 운동 목록 (<?= $totalCount ?>개)</h5>
            </div>
            <div class="p-0">
                <?php if (empty($tempExercises)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">임시 운동이 없습니다</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>운동명</th>
                                    <th>사용자</th>
                                    <th>상태</th>
                                    <th>사용 횟수</th>
                                    <th>등록일</th>
                                    <th>승인된 운동</th>
                                    <th>액션</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tempExercises as $exercise): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($exercise['exercise_name']) ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?= htmlspecialchars($exercise['username']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($exercise['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($exercise['status']) {
                                            case 'pending':
                                                $statusClass = 'warning';
                                                $statusText = '승인 대기';
                                                break;
                                            case 'approved':
                                                $statusClass = 'success';
                                                $statusText = '승인됨';
                                                break;
                                            case 'rejected':
                                                $statusClass = 'danger';
                                                $statusText = '거부됨';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $exercise['usage_count'] ?>회</span>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($exercise['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($exercise['approved_exercise_name']): ?>
                                            <span class="text-success"><?= htmlspecialchars($exercise['approved_exercise_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exercise['status'] === 'pending'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-success" onclick="approveExercise(<?= $exercise['temp_ex_id'] ?>, '<?= htmlspecialchars($exercise['exercise_name']) ?>')">
                                                    <i class="fas fa-check"></i> 승인
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="rejectExercise(<?= $exercise['temp_ex_id'] ?>, '<?= htmlspecialchars($exercise['exercise_name']) ?>')">
                                                    <i class="fas fa-times"></i> 거부
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">처리됨</span>
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

        <!-- 페이지네이션 -->
        <?php if ($totalPages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 승인 모달 -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">임시 운동 승인</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="approveForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="temp_ex_id" id="approveTempExId">
                        
                        <div class="mb-3">
                            <label class="form-label">운동명</label>
                            <input type="text" class="form-control" id="approveExerciseName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">승인 방법</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="approve_method" id="connectExisting" value="connect" checked>
                                <label class="form-check-label" for="connectExisting">
                                    기존 운동과 연결
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="approve_method" id="createNew" value="create">
                                <label class="form-check-label" for="createNew">
                                    새 운동으로 등록
                                </label>
                            </div>
                        </div>
                        
                        <!-- 기존 운동 연결 -->
                        <div id="connectSection">
                            <div class="mb-3">
                                <label class="form-label">연결할 운동 선택</label>
                                <input type="hidden" name="approved_ex_id" id="selectedExerciseId" value="">
                                <input type="text" class="form-control mb-2" id="exerciseSearch" placeholder="운동명 또는 별칭을 입력하여 검색하세요..." autocomplete="off">
                                <div id="exerciseListContainer" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; display: none;">
                                    <div id="exerciseList" class="list-group list-group-flush">
                                        <!-- 검색 결과가 여기에 표시됩니다 -->
                                    </div>
                                </div>
                                <div id="selectedExercise" class="mt-2" style="display: none;">
                                    <div class="alert alert-info mb-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-check-circle me-2"></i>
                                            <strong>선택된 운동:</strong> <span id="selectedExerciseName"></span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                                            <i class="fas fa-times"></i> 선택 해제
                                        </button>
                                    </div>
                                </div>
                                <div class="alert alert-warning mb-0 mt-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>
                                        <strong>오타 처리:</strong> 운동을 선택하면 오타가 자동으로 올바른 운동명으로 수정됩니다. 
                                        별칭으로도 검색할 수 있습니다.
                                    </small>
                                </div>
                                
                                <!-- 유사어 관리 섹션 -->
                                <div id="aliasManagementSection" class="mt-3" style="display: none;">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i class="fas fa-tags me-2"></i>유사어 관리</h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- 유사어 목록 -->
                                            <div id="aliasList" class="mb-3">
                                                <div class="text-muted small">유사어 목록이 여기에 표시됩니다.</div>
                                            </div>
                                            
                                            <!-- 유사어 추가 폼 -->
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" id="newAliasInput" placeholder="새 유사어를 입력하세요...">
                                                <button type="button" class="btn btn-success" onclick="addAlias()">
                                                    <i class="fas fa-plus"></i> 추가
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 새 운동 등록 -->
                        <div id="createSection" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">운동명 (한글) *</label>
                                        <input type="text" name="exercise_name" class="form-control" id="newExerciseName">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">운동명 (영문)</label>
                                        <input type="text" name="exercise_name_en" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">장비</label>
                                        <input type="text" name="equipment" class="form-control" placeholder="예: Barbell, Dumbbell, Machine">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">장비 (한글)</label>
                                        <input type="text" name="equipment_kr" class="form-control" placeholder="예: 바벨, 덤벨, 머신">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">각도</label>
                                        <input type="text" name="angle" class="form-control" placeholder="예: Flat, Incline, Decline">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">각도 (한글)</label>
                                        <input type="text" name="angle_kr" class="form-control" placeholder="예: 평평, 인클라인, 디클라인">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">동작</label>
                                        <input type="text" name="movement" class="form-control" placeholder="예: Press, Pull, Extension">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">동작 (한글)</label>
                                        <input type="text" name="movement_kr" class="form-control" placeholder="예: 프레스, 풀, 익스텐션">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">메모</label>
                                <textarea name="note" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-success">승인</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 거부 모달 -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">임시 운동 거부</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="temp_ex_id" id="rejectTempExId">
                        
                        <div class="mb-3">
                            <label class="form-label">운동명</label>
                            <input type="text" class="form-control" id="rejectExerciseName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">거부 사유</label>
                            <textarea name="reject_reason" class="form-control" rows="3" placeholder="거부 사유를 입력하세요 (선택사항)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-danger">거부</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 운동 목록을 JavaScript 변수로 전달
        const exercisesList = <?= json_encode($exercises) ?>;
        // 별칭 목록도 JavaScript 변수로 전달 (검색용)
        const aliasesList = <?= json_encode($aliases) ?>;
        
        // 별칭을 ex_id로 매핑
        const aliasToExId = {};
        aliasesList.forEach(alias => {
            if (!aliasToExId[alias.ex_id]) {
                aliasToExId[alias.ex_id] = [];
            }
            aliasToExId[alias.ex_id].push(alias.alias.toLowerCase());
        });
        function approveExercise(tempExId, exerciseName) {
            document.getElementById('approveTempExId').value = tempExId;
            document.getElementById('approveExerciseName').value = exerciseName;
            document.getElementById('newExerciseName').value = exerciseName;
            
            // 검색 필드 및 선택 초기화
            document.getElementById('exerciseSearch').value = '';
            document.getElementById('selectedExerciseId').value = '';
            document.getElementById('selectedExercise').style.display = 'none';
            document.getElementById('exerciseListContainer').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();
        }
        
        function rejectExercise(tempExId, exerciseName) {
            document.getElementById('rejectTempExId').value = tempExId;
            document.getElementById('rejectExerciseName').value = exerciseName;
            
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }
        
        // 운동 선택 함수
        function selectExercise(exId, nameKr, nameEn) {
            document.getElementById('selectedExerciseId').value = exId;
            document.getElementById('selectedExerciseName').textContent = nameKr + (nameEn ? ' (' + nameEn + ')' : '');
            document.getElementById('selectedExercise').style.display = 'block';
            document.getElementById('exerciseListContainer').style.display = 'none';
            document.getElementById('exerciseSearch').value = '';
            
            // 유사어 관리 섹션 표시 및 유사어 목록 로드
            loadAliases(exId);
        }
        
        // 유사어 목록 로드
        function loadAliases(exId) {
            const aliasManagementSection = document.getElementById('aliasManagementSection');
            const aliasList = document.getElementById('aliasList');
            
            if (!exId) {
                aliasManagementSection.style.display = 'none';
                return;
            }
            
            // AJAX로 유사어 목록 가져오기
            fetch('?action=get_aliases&ex_id=' + exId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAliases(data.aliases, exId);
                        aliasManagementSection.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // 유사어 목록 표시
        function displayAliases(aliases, exId) {
            const aliasList = document.getElementById('aliasList');
            
            if (aliases.length === 0) {
                aliasList.innerHTML = '<div class="text-muted small">등록된 유사어가 없습니다.</div>';
                return;
            }
            
            let html = '<div class="list-group list-group-flush">';
            aliases.forEach(alias => {
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-info me-2">${escapeHtml(alias)}</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editAlias('${escapeHtml(alias)}', ${exId})">
                                <i class="fas fa-edit"></i> 수정
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteAlias('${escapeHtml(alias)}')">
                                <i class="fas fa-trash"></i> 삭제
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            aliasList.innerHTML = html;
        }
        
        // 유사어 추가
        function addAlias() {
            const exId = document.getElementById('selectedExerciseId').value;
            const aliasInput = document.getElementById('newAliasInput');
            const alias = aliasInput.value.trim();
            
            if (!exId) {
                alert('먼저 운동을 선택해주세요.');
                return;
            }
            
            if (!alias) {
                alert('유사어를 입력해주세요.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_alias');
            formData.append('ex_id', exId);
            formData.append('alias', alias);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    aliasInput.value = '';
                    loadAliases(exId);
                    alert('유사어가 추가되었습니다.');
                } else {
                    alert(data.message || '유사어 추가에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        // 유사어 수정
        function editAlias(oldAlias, exId) {
            const newAlias = prompt('유사어를 수정하세요:', oldAlias);
            
            if (!newAlias || newAlias.trim() === '') {
                return;
            }
            
            if (newAlias === oldAlias) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_alias');
            formData.append('ex_id', exId);
            formData.append('old_alias', oldAlias);
            formData.append('new_alias', newAlias.trim());
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadAliases(exId);
                    alert('유사어가 수정되었습니다.');
                } else {
                    alert(data.message || '유사어 수정에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        // 유사어 삭제
        function deleteAlias(alias) {
            if (!confirm('이 유사어를 삭제하시겠습니까?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_alias');
            formData.append('alias', alias);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const exId = document.getElementById('selectedExerciseId').value;
                    loadAliases(exId);
                    alert('유사어가 삭제되었습니다.');
                } else {
                    alert(data.message || '유사어 삭제에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        // 선택 해제 함수
        function clearSelection() {
            document.getElementById('aliasManagementSection').style.display = 'none';
            document.getElementById('selectedExerciseId').value = '';
            document.getElementById('selectedExercise').style.display = 'none';
            document.getElementById('exerciseSearch').value = '';
            document.getElementById('exerciseListContainer').style.display = 'none';
        }
        
        // 검색 기능 구현 (운동명 + 별칭 검색)
        function searchExercises(query) {
            const container = document.getElementById('exerciseListContainer');
            const list = document.getElementById('exerciseList');
            
            if (!query || query.trim() === '') {
                container.style.display = 'none';
                return;
            }
            
            const searchTerm = query.toLowerCase().trim();
            const filtered = exercisesList.filter(ex => {
                const nameKr = (ex.name_kr || '').toLowerCase();
                const nameEn = (ex.name_en || '').toLowerCase();
                
                // 운동명 검색
                if (nameKr.includes(searchTerm) || nameEn.includes(searchTerm)) {
                    return true;
                }
                
                // 별칭 검색
                const aliases = aliasToExId[ex.ex_id] || [];
                for (let i = 0; i < aliases.length; i++) {
                    if (aliases[i].includes(searchTerm)) {
                        return true;
                    }
                }
                
                return false;
            });
            
            if (filtered.length === 0) {
                list.innerHTML = '<div class="list-group-item text-center text-muted">검색 결과가 없습니다.</div>';
                container.style.display = 'block';
                return;
            }
            
            // 최대 50개만 표시
            const displayList = filtered.slice(0, 50);
            list.innerHTML = '';
            
            displayList.forEach(ex => {
                const item = document.createElement('div');
                item.className = 'list-group-item exercise-list-item';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${escapeHtml(ex.name_kr || '')}</strong>
                            ${ex.name_en ? '<small class="text-muted ms-2">(' + escapeHtml(ex.name_en) + ')</small>' : ''}
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </div>
                `;
                item.addEventListener('click', function() {
                    selectExercise(ex.ex_id, ex.name_kr, ex.name_en);
                });
                list.appendChild(item);
            });
            
            if (filtered.length > 50) {
                const moreItem = document.createElement('div');
                moreItem.className = 'list-group-item text-center text-muted';
                moreItem.textContent = `외 ${filtered.length - 50}개 더 있습니다. 검색어를 더 구체적으로 입력하세요.`;
                list.appendChild(moreItem);
            }
            
            container.style.display = 'block';
        }
        
        // HTML 이스케이프 함수
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // DOMContentLoaded 이벤트 리스너
        document.addEventListener('DOMContentLoaded', function() {
            const exerciseSearch = document.getElementById('exerciseSearch');
            
            // 검색 입력 필드 이벤트
            exerciseSearch.addEventListener('input', function(e) {
                const query = e.target.value;
                searchExercises(query);
            });
            
            // 검색 필드 포커스 시 전체 목록 표시 (선택되지 않은 경우)
            exerciseSearch.addEventListener('focus', function() {
                if (!document.getElementById('selectedExerciseId').value) {
                    if (exerciseSearch.value.trim() === '') {
                        // 전체 목록 표시 (최대 50개)
                        const displayList = exercisesList.slice(0, 50);
                        const list = document.getElementById('exerciseList');
                        const container = document.getElementById('exerciseListContainer');
                        
                        list.innerHTML = '';
                        displayList.forEach(ex => {
                            const item = document.createElement('div');
                            item.className = 'list-group-item exercise-list-item';
                            item.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${escapeHtml(ex.name_kr || '')}</strong>
                                        ${ex.name_en ? '<small class="text-muted ms-2">(' + escapeHtml(ex.name_en) + ')</small>' : ''}
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            `;
                            item.addEventListener('click', function() {
                                selectExercise(ex.ex_id, ex.name_kr, ex.name_en);
                            });
                            list.appendChild(item);
                        });
                        
                        if (exercisesList.length > 50) {
                            const moreItem = document.createElement('div');
                            moreItem.className = 'list-group-item text-center text-muted';
                            moreItem.textContent = `외 ${exercisesList.length - 50}개 더 있습니다. 검색어를 입력하여 찾으세요.`;
                            list.appendChild(moreItem);
                        }
                        
                        container.style.display = 'block';
                    }
                }
            });
            
            // 외부 클릭 시 리스트 닫기
            document.addEventListener('click', function(e) {
                const container = document.getElementById('exerciseListContainer');
                const searchInput = document.getElementById('exerciseSearch');
                
                if (!container.contains(e.target) && e.target !== searchInput) {
                    container.style.display = 'none';
                }
            });
            
            // 승인 방법 변경 시 섹션 표시/숨김
            const connectRadio = document.getElementById('connectExisting');
            const createRadio = document.getElementById('createNew');
            const connectSection = document.getElementById('connectSection');
            const createSection = document.getElementById('createSection');
            
            function toggleSections() {
                if (connectRadio.checked) {
                    connectSection.style.display = 'block';
                    createSection.style.display = 'none';
                } else {
                    connectSection.style.display = 'none';
                    createSection.style.display = 'block';
                }
            }
            
            connectRadio.addEventListener('change', toggleSections);
            createRadio.addEventListener('change', toggleSections);
        });
    </script>
    
    <!-- 하단 여백 -->
    <div class="mb-5"></div>
</body>
</html>

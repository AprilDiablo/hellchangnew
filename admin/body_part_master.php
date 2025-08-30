<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 데이터 추가/수정/삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_body_part':
                    $stmt = $pdo->prepare("INSERT INTO m_body_part (part_code, part_name_kr, part_name_en) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $_POST['part_code'],
                        $_POST['part_name_kr'],
                        $_POST['part_name_en'] ?: null
                    ]);
                    header("Location: body_part_master.php?message=body_part_added");
                    exit();
                    break;
                    
                case 'edit_body_part':
                    $stmt = $pdo->prepare("UPDATE m_body_part SET part_name_kr=?, part_name_en=? WHERE part_code=?");
                    $stmt->execute([
                        $_POST['part_name_kr'],
                        $_POST['part_name_en'] ?: null,
                        $_POST['part_code']
                    ]);
                    header("Location: body_part_master.php?message=body_part_updated");
                    exit();
                    break;
                    
                case 'delete_body_part':
                    // 관련 데이터 체크
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_muscle WHERE part_code = ?");
                    $stmt->execute([$_POST['part_code']]);
                    $muscle_count = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_part_zone WHERE part_code = ?");
                    $stmt->execute([$_POST['part_code']]);
                    $zone_count = $stmt->fetchColumn();
                    
                    if ($muscle_count > 0 || $zone_count > 0) {
                        throw new Exception("이 신체 부위는 근육이나 세부존에 사용되고 있어 삭제할 수 없습니다.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM m_body_part WHERE part_code = ?");
                    $stmt->execute([$_POST['part_code']]);
                    
                    header("Location: body_part_master.php?message=body_part_deleted");
                    exit();
                    break;
                    
                case 'add_muscle':
                    $stmt = $pdo->prepare("INSERT INTO m_muscle (muscle_code, part_code, name_kr, name_en) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['muscle_code'],
                        $_POST['part_code'],
                        $_POST['name_kr'],
                        $_POST['name_en'] ?: null
                    ]);
                    header("Location: body_part_master.php?message=muscle_added");
                    exit();
                    break;
                    
                case 'edit_muscle':
                    $stmt = $pdo->prepare("UPDATE m_muscle SET part_code=?, name_kr=?, name_en=? WHERE muscle_code=?");
                    $stmt->execute([
                        $_POST['part_code'],
                        $_POST['name_kr'],
                        $_POST['name_en'] ?: null,
                        $_POST['muscle_code']
                    ]);
                    header("Location: body_part_master.php?message=muscle_updated");
                    exit();
                    break;
                    
                case 'delete_muscle':
                    // 관련 데이터 체크
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_exercise_muscle_target WHERE muscle_code = ?");
                    $stmt->execute([$_POST['muscle_code']]);
                    $usage_count = $stmt->fetchColumn();
                    
                    if ($usage_count > 0) {
                        throw new Exception("이 근육은 운동에 사용되고 있어 삭제할 수 없습니다.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM m_muscle WHERE muscle_code = ?");
                    $stmt->execute([$_POST['muscle_code']]);
                    
                    header("Location: body_part_master.php?message=muscle_deleted");
                    exit();
                    break;
                    
                case 'add_zone':
                    $stmt = $pdo->prepare("INSERT INTO m_part_zone (zone_code, part_code, zone_name_kr, zone_name_en) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['zone_code'],
                        $_POST['part_code'],
                        $_POST['zone_name_kr'],
                        $_POST['zone_name_en'] ?: null
                    ]);
                    header("Location: body_part_master.php?message=zone_added");
                    exit();
                    break;
                    
                case 'edit_zone':
                    $stmt = $pdo->prepare("UPDATE m_part_zone SET part_code=?, zone_name_kr=?, zone_name_en=? WHERE zone_code=?");
                    $stmt->execute([
                        $_POST['part_code'],
                        $_POST['zone_name_kr'],
                        $_POST['zone_name_en'] ?: null,
                        $_POST['zone_code']
                    ]);
                    header("Location: body_part_master.php?message=zone_updated");
                    exit();
                    break;
                    
                case 'delete_zone':
                    // 관련 데이터 체크
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_exercise_zone_target WHERE zone_code = ?");
                    $stmt->execute([$_POST['zone_code']]);
                    $usage_count = $stmt->fetchColumn();
                    
                    if ($usage_count > 0) {
                        throw new Exception("이 세부존은 운동에 사용되고 있어 삭제할 수 없습니다.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM m_part_zone WHERE zone_code = ?");
                    $stmt->execute([$_POST['zone_code']]);
                    
                    header("Location: body_part_master.php?message=zone_deleted");
                    exit();
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "오류가 발생했습니다: " . $e->getMessage();
    }
}

// 성공 메시지 처리
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'body_part_added':
            $message = "신체 부위가 성공적으로 추가되었습니다.";
            break;
        case 'body_part_updated':
            $message = "신체 부위가 성공적으로 수정되었습니다.";
            break;
        case 'body_part_deleted':
            $message = "신체 부위가 성공적으로 삭제되었습니다.";
            break;
        case 'muscle_added':
            $message = "근육이 성공적으로 추가되었습니다.";
            break;
        case 'muscle_updated':
            $message = "근육이 성공적으로 수정되었습니다.";
            break;
        case 'muscle_deleted':
            $message = "근육이 성공적으로 삭제되었습니다.";
            break;
        case 'zone_added':
            $message = "세부존이 성공적으로 추가되었습니다.";
            break;
        case 'zone_updated':
            $message = "세부존이 성공적으로 수정되었습니다.";
            break;
        case 'zone_deleted':
            $message = "세부존이 성공적으로 삭제되었습니다.";
            break;
    }
}

// 데이터 가져오기
try {
    // 신체 부위 목록
    $stmt = $pdo->query("SELECT * FROM m_body_part ORDER BY part_code");
    $body_parts = $stmt->fetchAll();
    
    // 근육 목록
    $stmt = $pdo->query("
        SELECT m.*, bp.part_name_kr as body_part_name
        FROM m_muscle m
        JOIN m_body_part bp ON m.part_code = bp.part_code
        ORDER BY bp.part_code, m.muscle_code
    ");
    $muscles = $stmt->fetchAll();
    
    // 세부존 목록
    $stmt = $pdo->query("
        SELECT z.*, bp.part_name_kr as body_part_name
        FROM m_part_zone z
        JOIN m_body_part bp ON z.part_code = bp.part_code
        ORDER BY bp.part_code, z.zone_code
    ");
    $zones = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "데이터를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>세부부위 관리 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .back-btn {
            background: #6c757d;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        .master-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
        }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="exercise_master.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>운동 마스터로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-cog me-3"></i>세부부위 관리
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
        
        <!-- 탭 네비게이션 -->
        <ul class="nav nav-tabs" id="masterTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="body-part-tab" data-bs-toggle="tab" data-bs-target="#body-part" type="button" role="tab">
                    <i class="fas fa-user me-2"></i>신체 부위
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="muscle-tab" data-bs-toggle="tab" data-bs-target="#muscle" type="button" role="tab">
                    <i class="fas fa-muscle me-2"></i>근육
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="zone-tab" data-bs-toggle="tab" data-bs-target="#zone" type="button" role="tab">
                    <i class="fas fa-map-marker-alt me-2"></i>세부존
                </button>
            </li>
        </ul>
        
        <!-- 탭 콘텐츠 -->
        <div class="tab-content" id="masterTabsContent">
            <!-- 신체 부위 관리 -->
            <div class="tab-pane fade show active" id="body-part" role="tabpanel">
                <div class="card master-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-user me-2"></i>신체 부위 관리</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBodyPartModal">
                            <i class="fas fa-plus me-1"></i>신체 부위 추가
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>부위 코드</th>
                                        <th>부위명 (한글)</th>
                                        <th>부위명 (영문)</th>
                                        <th>관리</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($body_parts as $part): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($part['part_code']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($part['part_name_kr']) ?></strong></td>
                                        <td><?= htmlspecialchars($part['part_name_en'] ?: '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editBodyPart('<?= $part['part_code'] ?>', '<?= htmlspecialchars($part['part_name_kr']) ?>', '<?= htmlspecialchars($part['part_name_en'] ?: '') ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBodyPart('<?= $part['part_code'] ?>', '<?= htmlspecialchars($part['part_name_kr']) ?>')">
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
            
            <!-- 근육 관리 -->
            <div class="tab-pane fade" id="muscle" role="tabpanel">
                <div class="card master-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-muscle me-2"></i>근육 관리</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMuscleModal">
                            <i class="fas fa-plus me-1"></i>근육 추가
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>근육 코드</th>
                                        <th>신체 부위</th>
                                        <th>근육명 (한글)</th>
                                        <th>근육명 (영문)</th>
                                        <th>관리</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($muscles as $muscle): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($muscle['muscle_code']) ?></code></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($muscle['body_part_name']) ?></span></td>
                                        <td><strong><?= htmlspecialchars($muscle['name_kr']) ?></strong></td>
                                        <td><?= htmlspecialchars($muscle['name_en'] ?: '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editMuscle('<?= $muscle['muscle_code'] ?>', '<?= $muscle['part_code'] ?>', '<?= htmlspecialchars($muscle['name_kr']) ?>', '<?= htmlspecialchars($muscle['name_en'] ?: '') ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMuscle('<?= $muscle['muscle_code'] ?>', '<?= htmlspecialchars($muscle['name_kr']) ?>')">
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
            
            <!-- 세부존 관리 -->
            <div class="tab-pane fade" id="zone" role="tabpanel">
                <div class="card master-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>세부존 관리</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addZoneModal">
                            <i class="fas fa-plus me-1"></i>세부존 추가
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>세부존 코드</th>
                                        <th>신체 부위</th>
                                        <th>세부존명 (한글)</th>
                                        <th>세부존명 (영문)</th>
                                        <th>관리</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($zones as $zone): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($zone['zone_code']) ?></code></td>
                                        <td><span class="badge bg-info"><?= htmlspecialchars($zone['body_part_name']) ?></span></td>
                                        <td><strong><?= htmlspecialchars($zone['zone_name_kr']) ?></strong></td>
                                        <td><?= htmlspecialchars($zone['zone_name_en'] ?: '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editZone('<?= $zone['zone_code'] ?>', '<?= $zone['part_code'] ?>', '<?= htmlspecialchars($zone['zone_name_kr']) ?>', '<?= htmlspecialchars($zone['zone_name_en'] ?: '') ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteZone('<?= $zone['zone_code'] ?>', '<?= htmlspecialchars($zone['zone_name_kr']) ?>')">
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
        </div>
    </div>
    
    <!-- 신체 부위 추가 모달 -->
    <div class="modal fade" id="addBodyPartModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">신체 부위 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_body_part">
                        <div class="mb-3">
                            <label for="part_code" class="form-label">부위 코드 *</label>
                            <input type="text" class="form-control" id="part_code" name="part_code" required placeholder="예: CHEST">
                        </div>
                        <div class="mb-3">
                            <label for="part_name_kr" class="form-label">부위명 (한글) *</label>
                            <input type="text" class="form-control" id="part_name_kr" name="part_name_kr" required placeholder="예: 가슴">
                        </div>
                        <div class="mb-3">
                            <label for="part_name_en" class="form-label">부위명 (영문)</label>
                            <input type="text" class="form-control" id="part_name_en" name="part_name_en" placeholder="예: Chest">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">추가</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 신체 부위 수정 모달 -->
    <div class="modal fade" id="editBodyPartModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">신체 부위 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_body_part">
                        <div class="mb-3">
                            <label for="edit_part_code" class="form-label">부위 코드</label>
                            <input type="text" class="form-control" id="edit_part_code" name="part_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_part_name_kr" class="form-label">부위명 (한글) *</label>
                            <input type="text" class="form-control" id="edit_part_name_kr" name="part_name_kr" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_part_name_en" class="form-label">부위명 (영문)</label>
                            <input type="text" class="form-control" id="edit_part_name_en" name="part_name_en">
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
    
    <!-- 근육 추가 모달 -->
    <div class="modal fade" id="addMuscleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">근육 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_muscle">
                        <div class="mb-3">
                            <label for="muscle_code" class="form-label">근육 코드 *</label>
                            <input type="text" class="form-control" id="muscle_code" name="muscle_code" required placeholder="예: CHEST_PEC_MAJOR">
                        </div>
                        <div class="mb-3">
                            <label for="muscle_part_code" class="form-label">신체 부위 *</label>
                            <select class="form-select" id="muscle_part_code" name="part_code" required>
                                <option value="">선택하세요</option>
                                <?php foreach ($body_parts as $part): ?>
                                    <option value="<?= $part['part_code'] ?>"><?= htmlspecialchars($part['part_name_kr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="muscle_name_kr" class="form-label">근육명 (한글) *</label>
                            <input type="text" class="form-control" id="muscle_name_kr" name="name_kr" required placeholder="예: 대흉근">
                        </div>
                        <div class="mb-3">
                            <label for="muscle_name_en" class="form-label">근육명 (영문)</label>
                            <input type="text" class="form-control" id="muscle_name_en" name="name_en" placeholder="예: Pectoralis Major">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">추가</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 근육 수정 모달 -->
    <div class="modal fade" id="editMuscleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">근육 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_muscle">
                        <div class="mb-3">
                            <label for="edit_muscle_code" class="form-label">근육 코드</label>
                            <input type="text" class="form-control" id="edit_muscle_code" name="muscle_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_muscle_part_code" class="form-label">신체 부위 *</label>
                            <select class="form-select" id="edit_muscle_part_code" name="part_code" required>
                                <?php foreach ($body_parts as $part): ?>
                                    <option value="<?= $part['part_code'] ?>"><?= htmlspecialchars($part['part_name_kr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_muscle_name_kr" class="form-label">근육명 (한글) *</label>
                            <input type="text" class="form-control" id="edit_muscle_name_kr" name="name_kr" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_muscle_name_en" class="form-label">근육명 (영문)</label>
                            <input type="text" class="form-control" id="edit_muscle_name_en" name="name_en">
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
    
    <!-- 세부존 추가 모달 -->
    <div class="modal fade" id="addZoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">세부존 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_zone">
                        <div class="mb-3">
                            <label for="zone_code" class="form-label">세부존 코드 *</label>
                            <input type="text" class="form-control" id="zone_code" name="zone_code" required placeholder="예: CHEST_UPPER">
                        </div>
                        <div class="mb-3">
                            <label for="zone_part_code" class="form-label">신체 부위 *</label>
                            <select class="form-select" id="zone_part_code" name="part_code" required>
                                <option value="">선택하세요</option>
                                <?php foreach ($body_parts as $part): ?>
                                    <option value="<?= $part['part_code'] ?>"><?= htmlspecialchars($part['part_name_kr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="zone_name_kr" class="form-label">세부존명 (한글) *</label>
                            <input type="text" class="form-control" id="zone_name_kr" name="zone_name_kr" required placeholder="예: 상부 가슴">
                        </div>
                        <div class="mb-3">
                            <label for="zone_name_en" class="form-label">세부존명 (영문)</label>
                            <input type="text" class="form-control" id="zone_name_en" name="zone_name_en" placeholder="예: Upper Chest">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">추가</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 세부존 수정 모달 -->
    <div class="modal fade" id="editZoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">세부존 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_zone">
                        <div class="mb-3">
                            <label for="edit_zone_code" class="form-label">세부존 코드</label>
                            <input type="text" class="form-control" id="edit_zone_code" name="zone_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_zone_part_code" class="form-label">신체 부위 *</label>
                            <select class="form-select" id="edit_zone_part_code" name="part_code" required>
                                <?php foreach ($body_parts as $part): ?>
                                    <option value="<?= $part['part_code'] ?>"><?= htmlspecialchars($part['part_name_kr']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_zone_name_kr" class="form-label">세부존명 (한글) *</label>
                            <input type="text" class="form-control" id="edit_zone_name_kr" name="zone_name_kr" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_zone_name_en" class="form-label">세부존명 (영문)</label>
                            <input type="text" class="form-control" id="edit_zone_name_en" name="zone_name_en">
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 신체 부위 수정
        function editBodyPart(partCode, partNameKr, partNameEn) {
            document.getElementById('edit_part_code').value = partCode;
            document.getElementById('edit_part_name_kr').value = partNameKr;
            document.getElementById('edit_part_name_en').value = partNameEn;
            new bootstrap.Modal(document.getElementById('editBodyPartModal')).show();
        }
        
        // 신체 부위 삭제
        function deleteBodyPart(partCode, partName) {
            if (confirm(`"${partName}" 신체 부위를 삭제하시겠습니까?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_body_part">
                    <input type="hidden" name="part_code" value="${partCode}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 근육 수정
        function editMuscle(muscleCode, partCode, nameKr, nameEn) {
            document.getElementById('edit_muscle_code').value = muscleCode;
            document.getElementById('edit_muscle_part_code').value = partCode;
            document.getElementById('edit_muscle_name_kr').value = nameKr;
            document.getElementById('edit_muscle_name_en').value = nameEn;
            new bootstrap.Modal(document.getElementById('editMuscleModal')).show();
        }
        
        // 근육 삭제
        function deleteMuscle(muscleCode, name) {
            if (confirm(`"${name}" 근육을 삭제하시겠습니까?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_muscle">
                    <input type="hidden" name="muscle_code" value="${muscleCode}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 세부존 수정
        function editZone(zoneCode, partCode, nameKr, nameEn) {
            document.getElementById('edit_zone_code').value = zoneCode;
            document.getElementById('edit_zone_part_code').value = partCode;
            document.getElementById('edit_zone_name_kr').value = nameKr;
            document.getElementById('edit_zone_name_en').value = nameEn;
            new bootstrap.Modal(document.getElementById('editZoneModal')).show();
        }
        
        // 세부존 삭제
        function deleteZone(zoneCode, name) {
            if (confirm(`"${name}" 세부존을 삭제하시겠습니까?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_zone">
                    <input type="hidden" name="zone_code" value="${zoneCode}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

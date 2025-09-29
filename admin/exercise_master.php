<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$message = '';
$error = '';

// 운동 추가/수정/삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $pdo->beginTransaction();
                    
                    // 운동 추가
                    $stmt = $pdo->prepare("INSERT INTO m_exercise (name_kr, name_en, equipment, angle, movement, note) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['name_kr'],
                        $_POST['name_en'] ?: null,
                        $_POST['equipment'] ?: null,
                        $_POST['angle'] ?: null,
                        $_POST['movement'] ?: null,
                        $_POST['note'] ?: null
                    ]);
                    
                    $ex_id = $pdo->lastInsertId();
                    
                    // 근육 타겟 추가
                    if (!empty($_POST['muscle_targets'])) {
                        $stmt = $pdo->prepare("INSERT INTO m_exercise_muscle_target (ex_id, muscle_code, priority, weight) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['muscle_targets'] as $muscle) {
                            if (!empty($muscle['muscle_code'])) {
                                $stmt->execute([
                                    $ex_id,
                                    $muscle['muscle_code'],
                                    $muscle['priority'] ?: 1,
                                    $muscle['weight'] ?: 1.00
                                ]);
                            }
                        }
                    }
                    
                    // 세부존 타겟 추가
                    if (!empty($_POST['zone_targets'])) {
                        $stmt = $pdo->prepare("INSERT INTO m_exercise_zone_target (ex_id, zone_code, priority, weight) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['zone_targets'] as $zone) {
                            if (!empty($zone['zone_code'])) {
                                $stmt->execute([
                                    $ex_id,
                                    $zone['zone_code'],
                                    $zone['priority'] ?: 1,
                                    $zone['weight'] ?: 1.00
                                ]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    // 리다이렉션으로 새로고침 방지
                    header("Location: exercise_master.php?message=added");
                    exit();
                    break;
                    
                case 'edit':
                    $pdo->beginTransaction();
                    
                    // 운동 기본 정보 수정
                    $stmt = $pdo->prepare("UPDATE m_exercise SET name_kr=?, name_en=?, equipment=?, angle=?, movement=?, note=? WHERE ex_id=?");
                    $stmt->execute([
                        $_POST['name_kr'],
                        $_POST['name_en'] ?: null,
                        $_POST['equipment'] ?: null,
                        $_POST['angle'] ?: null,
                        $_POST['movement'] ?: null,
                        $_POST['note'] ?: null,
                        $_POST['ex_id']
                    ]);
                    
                    // 기존 근육 타겟 삭제 후 재생성
                    $stmt = $pdo->prepare("DELETE FROM m_exercise_muscle_target WHERE ex_id = ?");
                    $stmt->execute([$_POST['ex_id']]);
                    
                    // 새로운 근육 타겟 추가
                    if (!empty($_POST['muscle_targets'])) {
                        $stmt = $pdo->prepare("INSERT INTO m_exercise_muscle_target (ex_id, muscle_code, priority, weight) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['muscle_targets'] as $muscle) {
                            if (!empty($muscle['muscle_code'])) {
                                $stmt->execute([
                                    $_POST['ex_id'],
                                    $muscle['muscle_code'],
                                    $muscle['priority'] ?: 1,
                                    $muscle['weight'] ?: 1.00
                                ]);
                            }
                        }
                    }
                    
                    // 기존 세부존 타겟 삭제 후 재생성
                    $stmt = $pdo->prepare("DELETE FROM m_exercise_zone_target WHERE ex_id = ?");
                    $stmt->execute([$_POST['ex_id']]);
                    
                    // 새로운 세부존 타겟 추가
                    if (!empty($_POST['zone_targets'])) {
                        $stmt = $pdo->prepare("INSERT INTO m_exercise_zone_target (ex_id, zone_code, priority, weight) VALUES (?, ?, ?, ?)");
                        foreach ($_POST['zone_targets'] as $zone) {
                            if (!empty($zone['zone_code'])) {
                                $stmt->execute([
                                    $_POST['ex_id'],
                                    $zone['zone_code'],
                                    $zone['priority'] ?: 1,
                                    $zone['weight'] ?: 1.00
                                ]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    // 리다이렉션으로 새로고침 방지
                    header("Location: exercise_master.php?message=updated");
                    exit();
                    break;
                    
                case 'delete':
                    try {
                        $pdo->beginTransaction();
                        
                        // 외래키 제약조건 체크
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_exercise_muscle_target WHERE ex_id = ?");
                        $stmt->execute([$_POST['ex_id']]);
                        $muscle_count = $stmt->fetchColumn();
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_exercise_zone_target WHERE ex_id = ?");
                        $stmt->execute([$_POST['ex_id']]);
                        $zone_count = $stmt->fetchColumn();
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM m_exercise_alias WHERE ex_id = ?");
                        $stmt->execute([$_POST['ex_id']]);
                        $alias_count = $stmt->fetchColumn();
                        
                        // 사용 중인 운동인지 체크 (사용자 운동 기록 등)
                        // user_workout_detail 테이블이 존재하지 않으므로 주석 처리
                        /*
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_workout_detail WHERE ex_id = ?");
                        $stmt->execute([$_POST['ex_id']]);
                        $usage_count = $stmt->fetchColumn();
                        
                        if ($usage_count > 0) {
                            throw new Exception("이 운동은 사용자의 운동 기록에 사용되고 있어 삭제할 수 없습니다.");
                        }
                        */
                        $usage_count = 0; // 임시로 0으로 설정
                        
                        // 관련 데이터 먼저 삭제
                        if ($muscle_count > 0) {
                            $stmt = $pdo->prepare("DELETE FROM m_exercise_muscle_target WHERE ex_id = ?");
                            $stmt->execute([$_POST['ex_id']]);
                        }
                        
                        if ($zone_count > 0) {
                            $stmt = $pdo->prepare("DELETE FROM m_exercise_zone_target WHERE ex_id = ?");
                            $stmt->execute([$_POST['ex_id']]);
                        }
                        
                        if ($alias_count > 0) {
                            $stmt = $pdo->prepare("DELETE FROM m_exercise_alias WHERE ex_id = ?");
                            $stmt->execute([$_POST['ex_id']]);
                        }
                        
                        // 메인 운동 삭제
                        $stmt = $pdo->prepare("DELETE FROM m_exercise WHERE ex_id = ?");
                        $stmt->execute([$_POST['ex_id']]);
                        
                        $pdo->commit();
                        
                        // 리다이렉션으로 새로고침 방지
                        header("Location: exercise_master.php?message=deleted");
                        exit();
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    break;
                    
                case 'add_alias':
                    $stmt = $pdo->prepare("INSERT INTO m_exercise_alias (alias, ex_id) VALUES (?, ?)");
                    $stmt->execute([$_POST['alias'], $_POST['ex_id']]);
                    
                    // 리다이렉션으로 새로고침 방지
                    header("Location: exercise_master.php?message=alias_added");
                    exit();
                    break;
                    
                case 'delete_alias':
                    $stmt = $pdo->prepare("DELETE FROM m_exercise_alias WHERE alias=?");
                    $stmt->execute([$_POST['alias']]);
                    
                    // 리다이렉션으로 새로고침 방지
                    header("Location: exercise_master.php?message=alias_deleted");
                    exit();
                    break;
            }
        }
    } catch (Exception $e) {
        // 트랜잭션이 활성화된 경우에만 롤백
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "오류가 발생했습니다: " . $e->getMessage();
    }
}

// 성공 메시지 처리
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
            $message = "운동이 성공적으로 추가되었습니다.";
            break;
        case 'updated':
            $message = "운동이 성공적으로 수정되었습니다.";
            break;
        case 'deleted':
            $message = "운동이 성공적으로 삭제되었습니다.";
            break;
        case 'alias_added':
            $message = "동의어가 성공적으로 추가되었습니다.";
            break;
        case 'alias_deleted':
            $message = "동의어가 성공적으로 삭제되었습니다.";
            break;
    }
}

// 운동 목록 가져오기
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               COUNT(DISTINCT ez.zone_code) as zone_count,
               COUNT(DISTINCT em.muscle_code) as muscle_count,
               COUNT(DISTINCT ea.alias) as alias_count
        FROM m_exercise e
        LEFT JOIN m_exercise_zone_target ez ON e.ex_id = ez.ex_id
        LEFT JOIN m_exercise_muscle_target em ON e.ex_id = em.ex_id
        LEFT JOIN m_exercise_alias ea ON e.ex_id = ea.ex_id
        GROUP BY e.ex_id
        ORDER BY e.name_kr
    ");
    $exercises = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "운동 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 동의어 목록 가져오기
try {
    $stmt = $pdo->query("
        SELECT ea.*, e.name_kr as exercise_name
        FROM m_exercise_alias ea
        JOIN m_exercise e ON ea.ex_id = e.ex_id
        ORDER BY e.name_kr, ea.alias
    ");
    $aliases = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "동의어 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 근육 목록 가져오기
try {
    $stmt = $pdo->query("
        SELECT m.*, bp.part_name_kr as body_part
        FROM m_muscle m
        JOIN m_body_part bp ON m.part_code = bp.part_code
        ORDER BY bp.part_name_kr, m.name_kr
    ");
    $muscles = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "근육 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 세부존 목록 가져오기
try {
    $stmt = $pdo->query("
        SELECT z.*, bp.part_name_kr as body_part
        FROM m_part_zone z
        JOIN m_body_part bp ON z.part_code = bp.part_code
        ORDER BY bp.part_name_kr, z.zone_name_kr
    ");
    $zones = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "세부존 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// AJAX 요청 처리 - 운동 데이터 가져오기
if (isset($_GET['action']) && $_GET['action'] === 'get_exercise') {
    try {
        $ex_id = $_GET['ex_id'];
        
        // 운동 기본 정보
        $stmt = $pdo->prepare("SELECT * FROM m_exercise WHERE ex_id = ?");
        $stmt->execute([$ex_id]);
        $exercise = $stmt->fetch();
        
        if (!$exercise) {
            http_response_code(404);
            echo json_encode(['error' => '운동을 찾을 수 없습니다.']);
            exit;
        }
        
        // 근육 타겟 정보
        $stmt = $pdo->prepare("SELECT * FROM m_exercise_muscle_target WHERE ex_id = ? ORDER BY priority");
        $stmt->execute([$ex_id]);
        $muscle_targets = $stmt->fetchAll();
        
        // 세부존 타겟 정보
        $stmt = $pdo->prepare("SELECT * FROM m_exercise_zone_target WHERE ex_id = ? ORDER BY priority");
        $stmt->execute([$ex_id]);
        $zone_targets = $stmt->fetchAll();
        
        $response = [
            'exercise' => $exercise,
            'muscle_targets' => $muscle_targets,
            'zone_targets' => $zone_targets
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => '데이터를 가져오는 중 오류가 발생했습니다: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>운동 마스터 관리 - 관리자</title>
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
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        .exercise-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
        }
        .gap-1 {
            gap: 0.25rem !important;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
                 <div class="d-flex justify-content-between align-items-center mb-4">
             <a href="exercise_management.php" class="back-btn">
                 <i class="fas fa-arrow-left me-2"></i>운동 관리로 돌아가기
             </a>
             <a href="body_part_master.php" class="btn btn-info">
                 <i class="fas fa-cog me-2"></i>세부부위 관리
             </a>
         </div>
        
        <h1 class="text-center mb-5">
            <i class="fas fa-dumbbell me-3"></i>운동 마스터 관리
        </h1>
        
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
        
                 <!-- 새 운동 추가 버튼 -->
         <div class="text-center mb-4">
             <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="collapse" data-bs-target="#addExerciseForm" aria-expanded="false" aria-controls="addExerciseForm">
                 <i class="fas fa-plus me-2"></i>새 운동 추가
             </button>
         </div>
         
         <!-- 새 운동 추가 폼 (접을 수 있음) -->
         <div class="collapse" id="addExerciseForm">
             <div class="card exercise-card mb-4">
                 <div class="card-header">
                     <h5><i class="fas fa-plus me-2"></i>새 운동 추가</h5>
                 </div>
                 <div class="card-body">
                     <form method="post">
                         <input type="hidden" name="action" value="add">
                         <div class="row">
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="name_kr" class="form-label">운동명 (한글) *</label>
                                     <input type="text" class="form-control" id="name_kr" name="name_kr" required>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="name_en" class="form-label">운동명 (영문)</label>
                                     <input type="text" class="form-control" id="name_en" name="name_en">
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="equipment" class="form-label">장비</label>
                                     <select class="form-select" id="equipment" name="equipment">
                                         <option value="">선택하세요</option>
                                         <option value="Barbell">Barbell</option>
                                         <option value="Dumbbell">Dumbbell</option>
                                         <option value="Machine">Machine</option>
                                         <option value="Cable">Cable</option>
                                         <option value="Bodyweight">Bodyweight</option>
                                         <option value="Kettlebell">Kettlebell</option>
                                     </select>
                                 </div>
                             </div>
                         </div>
                         <div class="row">
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="angle" class="form-label">각도</label>
                                     <select class="form-select" id="angle" name="angle">
                                         <option value="">선택하세요</option>
                                         <option value="Flat">Flat</option>
                                         <option value="Incline">Incline</option>
                                         <option value="Decline">Decline</option>
                                         <option value="Seated">Seated</option>
                                         <option value="Standing">Standing</option>
                                         <option value="Lying">Lying</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="movement" class="form-label">동작 유형</label>
                                     <select class="form-select" id="movement" name="movement">
                                         <option value="">선택하세요</option>
                                         <option value="Press">Press</option>
                                         <option value="Pull">Pull</option>
                                         <option value="Push">Push</option>
                                         <option value="Extension">Extension</option>
                                         <option value="Curl">Curl</option>
                                         <option value="Squat">Squat</option>
                                         <option value="Lunge">Lunge</option>
                                         <option value="Row">Row</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="note" class="form-label">비고</label>
                                     <input type="text" class="form-control" id="note" name="note" placeholder="추가 설명">
                                 </div>
                             </div>
                         </div>
                         
                         <!-- 근육 타겟 설정 -->
                         <div class="row mb-3">
                             <div class="col-12">
                                 <label class="form-label">근육 타겟 설정</label>
                                 <div id="muscle-targets">
                                     <div class="muscle-target-row row mb-2">
                                         <div class="col-md-4">
                                             <select class="form-select" name="muscle_targets[0][muscle_code]">
                                                 <option value="">근육을 선택하세요</option>
                                                 <?php foreach ($muscles as $muscle): ?>
                                                     <option value="<?= $muscle['muscle_code'] ?>">
                                                         <?= htmlspecialchars($muscle['body_part']) ?> - <?= htmlspecialchars($muscle['name_kr']) ?>
                                                     </option>
                                                 <?php endforeach; ?>
                                             </select>
                                         </div>
                                         <div class="col-md-3">
                                             <select class="form-select" name="muscle_targets[0][priority]">
                                                 <option value="1">주요 타겟</option>
                                                 <option value="2">보조 타겟</option>
                                                 <option value="3">보조 타겟</option>
                                             </select>
                                         </div>
                                         <div class="col-md-3">
                                             <input type="number" class="form-control" name="muscle_targets[0][weight]" 
                                                    placeholder="가중치" step="0.01" min="0" max="2" value="1.00">
                                         </div>
                                         <div class="col-md-2">
                                             <button type="button" class="btn btn-outline-danger btn-sm remove-muscle" style="display: none;">
                                                 <i class="fas fa-trash"></i>
                                             </button>
                                         </div>
                                     </div>
                                 </div>
                                 <button type="button" class="btn btn-outline-primary btn-sm" id="add-muscle-target">
                                     <i class="fas fa-plus me-1"></i>근육 타겟 추가
                                 </button>
                             </div>
                         </div>
                         
                         <!-- 세부존 타겟 설정 -->
                         <div class="row mb-3">
                             <div class="col-12">
                                 <label class="form-label">세부존 타겟 설정</label>
                                 <div id="zone-targets">
                                     <div class="zone-target-row row mb-2">
                                         <div class="col-md-4">
                                             <select class="form-select" name="zone_targets[0][zone_code]">
                                                 <option value="">세부존을 선택하세요</option>
                                                 <?php foreach ($zones as $zone): ?>
                                                     <option value="<?= $zone['zone_code'] ?>">
                                                         <?= htmlspecialchars($zone['body_part']) ?> - <?= htmlspecialchars($zone['zone_name_kr']) ?>
                                                     </option>
                                                 <?php endforeach; ?>
                                             </select>
                                         </div>
                                         <div class="col-md-3">
                                             <select class="form-select" name="zone_targets[0][priority]">
                                                 <option value="1">주요 타겟</option>
                                                 <option value="2">보조 타겟</option>
                                                 <option value="3">보조 타겟</option>
                                             </select>
                                         </div>
                                         <div class="col-md-3">
                                             <input type="number" class="form-control" name="zone_targets[0][weight]" 
                                                    placeholder="가중치" step="0.01" min="0" max="2" value="1.00">
                                         </div>
                                         <div class="col-md-2">
                                             <button type="button" class="btn btn-outline-danger btn-sm remove-zone" style="display: none;">
                                                 <i class="fas fa-trash"></i>
                                             </button>
                                         </div>
                                     </div>
                                 </div>
                                 <button type="button" class="btn btn-outline-primary btn-sm" id="add-zone-target">
                                     <i class="fas fa-plus me-1"></i>세부존 타겟 추가
                                 </button>
                             </div>
                         </div>
                         
                         <div class="text-center">
                             <button type="submit" class="btn btn-primary">
                                 <i class="fas fa-plus me-2"></i>운동 추가
                             </button>
                         </div>
                     </form>
                 </div>
             </div>
         </div>
        
                 <!-- 운동 목록 -->
         <div class="card exercise-card">
             <div class="card-header">
                 <h5><i class="fas fa-list me-2"></i>운동 목록 (<?= count($exercises) ?>개)</h5>
             </div>
             <div class="card-body">
                 <!-- 검색 및 필터 -->
                 <div class="row mb-3">
                     <div class="col-md-3">
                         <div class="input-group">
                             <span class="input-group-text"><i class="fas fa-search"></i></span>
                             <input type="text" class="form-control" id="searchInput" placeholder="운동명으로 검색...">
                         </div>
                     </div>
                     <div class="col-md-2">
                         <select class="form-select" id="equipmentFilter">
                             <option value="">모든 장비</option>
                             <option value="Barbell">Barbell</option>
                             <option value="Dumbbell">Dumbbell</option>
                             <option value="Machine">Machine</option>
                             <option value="Cable">Cable</option>
                             <option value="Bodyweight">Bodyweight</option>
                             <option value="Kettlebell">Kettlebell</option>
                         </select>
                     </div>
                     <div class="col-md-2">
                         <select class="form-select" id="movementFilter">
                             <option value="">모든 동작</option>
                             <option value="Press">Press</option>
                             <option value="Pull">Pull</option>
                             <option value="Push">Push</option>
                             <option value="Extension">Extension</option>
                             <option value="Curl">Curl</option>
                             <option value="Squat">Squat</option>
                             <option value="Lunge">Lunge</option>
                             <option value="Row">Row</option>
                         </select>
                     </div>
                     <div class="col-md-3">
                         <select class="form-select" id="mappingFilter">
                             <option value="">모든 운동</option>
                             <option value="no_mapping">매핑 없음</option>
                             <option value="no_muscle">근육 매핑 없음</option>
                             <option value="no_zone">세부존 매핑 없음</option>
                             <option value="no_weight">가중치 없음</option>
                             <option value="complete">완전한 매핑</option>
                         </select>
                     </div>
                     <div class="col-md-2">
                         <button type="button" class="btn btn-outline-secondary w-100" id="clearFilters">
                             <i class="fas fa-times me-1"></i>초기화
                         </button>
                     </div>
                 </div>
                 
                 <div class="table-responsive">
                     <table class="table table-hover" id="exerciseTable">
                         <thead class="table-dark">
                             <tr>
                                 <th>운동명</th>
                                 <th>영문명</th>
                                 <th>장비</th>
                                 <th>각도</th>
                                 <th>동작</th>
                                 <th>매핑 현황</th>
                                 <th>동의어</th>
                                 <th>관리</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($exercises as $exercise): ?>
                             <tr class="exercise-row" 
                                 data-name-kr="<?= htmlspecialchars($exercise['name_kr']) ?>"
                                 data-name-en="<?= htmlspecialchars($exercise['name_en'] ?: '') ?>"
                                 data-equipment="<?= htmlspecialchars($exercise['equipment'] ?: '') ?>"
                                 data-movement="<?= htmlspecialchars($exercise['movement'] ?: '') ?>">
                                 <td>
                                     <strong><?= htmlspecialchars($exercise['name_kr']) ?></strong>
                                     <?php if ($exercise['note']): ?>
                                         <br><small class="text-muted"><?= htmlspecialchars($exercise['note']) ?></small>
                                     <?php endif; ?>
                                 </td>
                                 <td><?= htmlspecialchars($exercise['name_en'] ?: '-') ?></td>
                                 <td>
                                     <?php if ($exercise['equipment']): ?>
                                         <span class="badge bg-info"><?= htmlspecialchars($exercise['equipment']) ?></span>
                                     <?php else: ?>
                                         <span class="text-muted">-</span>
                                     <?php endif; ?>
                                 </td>
                                 <td>
                                     <?php if ($exercise['angle']): ?>
                                         <span class="badge bg-secondary"><?= htmlspecialchars($exercise['angle']) ?></span>
                                     <?php else: ?>
                                         <span class="text-muted">-</span>
                                     <?php endif; ?>
                                 </td>
                                 <td>
                                     <?php if ($exercise['movement']): ?>
                                         <span class="badge bg-warning text-dark"><?= htmlspecialchars($exercise['movement']) ?></span>
                                     <?php else: ?>
                                         <span class="text-muted">-</span>
                                     <?php endif; ?>
                                 </td>
                                 <td>
                                     <div class="d-flex flex-column gap-1">
                                         <div>
                                             <span class="stats-badge me-1" title="세부존 매핑">
                                                 <i class="fas fa-map-marker-alt me-1"></i><?= $exercise['zone_count'] ?>
                                             </span>
                                             <span class="stats-badge" title="근육 매핑">
                                                 <i class="fas fa-muscle me-1"></i><?= $exercise['muscle_count'] ?>
                                             </span>
                                         </div>
                                         <div class="small">
                                             <?php if ($exercise['zone_count'] == 0 && $exercise['muscle_count'] == 0): ?>
                                                 <span class="badge bg-danger">매핑 없음</span>
                                             <?php elseif ($exercise['zone_count'] == 0): ?>
                                                 <span class="badge bg-warning">세부존 없음</span>
                                             <?php elseif ($exercise['muscle_count'] == 0): ?>
                                                 <span class="badge bg-warning">근육 없음</span>
                                             <?php else: ?>
                                                 <span class="badge bg-success">완전</span>
                                             <?php endif; ?>
                                         </div>
                                     </div>
                                 </td>
                                 <td>
                                     <span class="badge bg-success"><?= $exercise['alias_count'] ?></span>
                                 </td>
                                 <td>
                                     <button class="btn btn-sm btn-outline-primary" onclick="editExercise(<?= $exercise['ex_id'] ?>)">
                                         <i class="fas fa-edit"></i>
                                     </button>
                                     <button class="btn btn-sm btn-outline-danger" onclick="deleteExercise(<?= $exercise['ex_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>')">
                                         <i class="fas fa-trash"></i>
                                     </button>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
                 
                 <!-- 검색 결과 없음 메시지 -->
                 <div id="noResults" class="text-center py-4" style="display: none;">
                     <i class="fas fa-search fa-3x text-muted mb-3"></i>
                     <h5 class="text-muted">검색 결과가 없습니다</h5>
                     <p class="text-muted">다른 검색어나 필터를 시도해보세요.</p>
                 </div>
             </div>
         </div>
        
                 <!-- 동의어 관리 -->
         <div class="card exercise-card mt-4">
             <div class="card-header">
                 <h5><i class="fas fa-tags me-2"></i>동의어 관리 (<?= count($aliases) ?>개)</h5>
             </div>
             <div class="card-body">
                 <!-- 새 동의어 추가 -->
                 <form method="post" class="mb-4">
                     <input type="hidden" name="action" value="add_alias">
                     <div class="row">
                         <div class="col-md-4">
                             <div class="mb-3">
                                 <label for="ex_id" class="form-label">운동 선택</label>
                                 <select class="form-select" id="ex_id" name="ex_id" required>
                                     <option value="">운동을 선택하세요</option>
                                     <?php foreach ($exercises as $exercise): ?>
                                         <option value="<?= $exercise['ex_id'] ?>"><?= htmlspecialchars($exercise['name_kr']) ?></option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                         </div>
                         <div class="col-md-6">
                             <div class="mb-3">
                                 <label for="alias" class="form-label">동의어</label>
                                 <input type="text" class="form-control" id="alias" name="alias" placeholder="예: 인클라인 DB 프레스" required>
                             </div>
                         </div>
                         <div class="col-md-2">
                             <div class="mb-3">
                                 <label class="form-label">&nbsp;</label>
                                 <button type="submit" class="btn btn-success w-100">
                                     <i class="fas fa-plus me-2"></i>추가
                                 </button>
                             </div>
                         </div>
                     </div>
                 </form>
                 
                 <!-- 동의어 검색 -->
                 <div class="row mb-3">
                     <div class="col-md-6">
                         <div class="input-group">
                             <span class="input-group-text"><i class="fas fa-search"></i></span>
                             <input type="text" class="form-control" id="aliasSearchInput" placeholder="운동명 또는 동의어로 검색...">
                         </div>
                     </div>
                     <div class="col-md-3">
                         <button type="button" class="btn btn-outline-secondary w-100" id="clearAliasFilters">
                             <i class="fas fa-times me-1"></i>초기화
                         </button>
                     </div>
                 </div>
                 
                 <!-- 동의어 목록 -->
                 <div class="table-responsive">
                     <table class="table table-sm" id="aliasTable">
                         <thead class="table-light">
                             <tr>
                                 <th>운동명</th>
                                 <th>동의어</th>
                                 <th>관리</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($aliases as $alias): ?>
                             <tr class="alias-row" 
                                 data-exercise-name="<?= htmlspecialchars($alias['exercise_name']) ?>"
                                 data-alias="<?= htmlspecialchars($alias['alias']) ?>">
                                 <td><?= htmlspecialchars($alias['exercise_name']) ?></td>
                                 <td><span class="badge bg-info"><?= htmlspecialchars($alias['alias']) ?></span></td>
                                 <td>
                                     <form method="post" style="display: inline;">
                                         <input type="hidden" name="action" value="delete_alias">
                                         <input type="hidden" name="alias" value="<?= htmlspecialchars($alias['alias']) ?>">
                                         <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('이 동의어를 삭제하시겠습니까?')">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </form>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
                 
                 <!-- 동의어 검색 결과 없음 메시지 -->
                 <div id="noAliasResults" class="text-center py-4" style="display: none;">
                     <i class="fas fa-search fa-3x text-muted mb-3"></i>
                     <h5 class="text-muted">검색 결과가 없습니다</h5>
                     <p class="text-muted">다른 검색어를 시도해보세요.</p>
                 </div>
             </div>
         </div>
    </div>

    <!-- 운동 수정 모달 -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">운동 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                                 <form method="post" id="editForm">
                     <div class="modal-body">
                         <input type="hidden" name="action" value="edit">
                         <input type="hidden" name="ex_id" id="edit_ex_id">
                         <div class="row">
                             <div class="col-md-6">
                                 <div class="mb-3">
                                     <label for="edit_name_kr" class="form-label">운동명 (한글) *</label>
                                     <input type="text" class="form-control" id="edit_name_kr" name="name_kr" required>
                                 </div>
                             </div>
                             <div class="col-md-6">
                                 <div class="mb-3">
                                     <label for="edit_name_en" class="form-label">운동명 (영문)</label>
                                     <input type="text" class="form-control" id="edit_name_en" name="name_en">
                                 </div>
                             </div>
                         </div>
                         <div class="row">
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="edit_equipment" class="form-label">장비</label>
                                     <select class="form-select" id="edit_equipment" name="equipment">
                                         <option value="">선택하세요</option>
                                         <option value="Barbell">Barbell</option>
                                         <option value="Dumbbell">Dumbbell</option>
                                         <option value="Machine">Machine</option>
                                         <option value="Cable">Cable</option>
                                         <option value="Bodyweight">Bodyweight</option>
                                         <option value="Kettlebell">Kettlebell</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="edit_angle" class="form-label">각도</label>
                                     <select class="form-select" id="edit_angle" name="angle">
                                         <option value="">선택하세요</option>
                                         <option value="Flat">Flat</option>
                                         <option value="Incline">Incline</option>
                                         <option value="Decline">Decline</option>
                                         <option value="Seated">Seated</option>
                                         <option value="Standing">Standing</option>
                                         <option value="Lying">Lying</option>
                                     </select>
                                 </div>
                             </div>
                             <div class="col-md-4">
                                 <div class="mb-3">
                                     <label for="edit_movement" class="form-label">동작 유형</label>
                                     <select class="form-select" id="edit_movement" name="movement">
                                         <option value="">선택하세요</option>
                                         <option value="Press">Press</option>
                                         <option value="Pull">Pull</option>
                                         <option value="Push">Push</option>
                                         <option value="Extension">Extension</option>
                                         <option value="Curl">Curl</option>
                                         <option value="Squat">Squat</option>
                                         <option value="Lunge">Lunge</option>
                                         <option value="Row">Row</option>
                                     </select>
                                 </div>
                             </div>
                         </div>
                         <div class="mb-3">
                             <label for="edit_note" class="form-label">비고</label>
                             <input type="text" class="form-control" id="edit_note" name="note">
                         </div>
                         
                         <!-- 근육 타겟 설정 -->
                         <div class="row mb-3">
                             <div class="col-12">
                                 <label class="form-label">근육 타겟 설정</label>
                                 <div id="edit-muscle-targets">
                                     <!-- 동적으로 생성됨 -->
                                 </div>
                                 <button type="button" class="btn btn-outline-primary btn-sm" id="edit-add-muscle-target">
                                     <i class="fas fa-plus me-1"></i>근육 타겟 추가
                                 </button>
                             </div>
                         </div>
                         
                         <!-- 세부존 타겟 설정 -->
                         <div class="row mb-3">
                             <div class="col-12">
                                 <label class="form-label">세부존 타겟 설정</label>
                                 <div id="edit-zone-targets">
                                     <!-- 동적으로 생성됨 -->
                                 </div>
                                 <button type="button" class="btn btn-outline-primary btn-sm" id="edit-add-zone-target">
                                     <i class="fas fa-plus me-1"></i>세부존 타겟 추가
                                 </button>
                             </div>
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
         // 전역 변수 선언
         let muscleTargetCount = 1;
         let zoneTargetCount = 1;
         
                   // 운동 수정 함수
          function editExercise(exId) {
              // AJAX로 운동 데이터 가져오기
              fetch(`exercise_master.php?action=get_exercise&ex_id=${exId}`)
                  .then(response => response.json())
                  .then(data => {
                      if (data.error) {
                          alert('오류: ' + data.error);
                          return;
                      }
                      
                      // 기본 정보 채우기
                      document.getElementById('edit_ex_id').value = data.exercise.ex_id;
                      document.getElementById('edit_name_kr').value = data.exercise.name_kr;
                      document.getElementById('edit_name_en').value = data.exercise.name_en || '';
                      document.getElementById('edit_equipment').value = data.exercise.equipment || '';
                      document.getElementById('edit_angle').value = data.exercise.angle || '';
                      document.getElementById('edit_movement').value = data.exercise.movement || '';
                      document.getElementById('edit_note').value = data.exercise.note || '';
                      
                      // 근육 타겟 설정
                      const muscleContainer = document.getElementById('edit-muscle-targets');
                      muscleContainer.innerHTML = '';
                      
                      if (data.muscle_targets.length > 0) {
                          data.muscle_targets.forEach((muscle, index) => {
                              addEditMuscleTarget(muscle.muscle_code, muscle.priority, muscle.weight);
                          });
                      } else {
                          addEditMuscleTarget('', 1, 1.00);
                      }
                      
                      // 세부존 타겟 설정
                      const zoneContainer = document.getElementById('edit-zone-targets');
                      zoneContainer.innerHTML = '';
                      
                      if (data.zone_targets.length > 0) {
                          data.zone_targets.forEach((zone, index) => {
                              addEditZoneTarget(zone.zone_code, zone.priority, zone.weight);
                          });
                      } else {
                          addEditZoneTarget('', 1, 1.00);
                      }
                      
                      // 모달 열기
                      new bootstrap.Modal(document.getElementById('editModal')).show();
                  })
                  .catch(error => {
                      console.error('Error:', error);
                      alert('데이터를 가져오는 중 오류가 발생했습니다.');
                  });
          }
         
         // 운동 삭제 함수
         function deleteExercise(exId, name) {
             if (confirm(`"${name}" 운동을 삭제하시겠습니까?\n\n주의: 관련된 모든 매핑 데이터도 함께 삭제됩니다.`)) {
                 const form = document.createElement('form');
                 form.method = 'POST';
                 form.innerHTML = `
                     <input type="hidden" name="action" value="delete">
                     <input type="hidden" name="ex_id" value="${exId}">
                 `;
                 document.body.appendChild(form);
                 form.submit();
             }
         }
         
         // 근육 타겟 동적 추가/제거
         document.getElementById('add-muscle-target').addEventListener('click', function() {
             const container = document.getElementById('muscle-targets');
             const newRow = document.createElement('div');
             newRow.className = 'muscle-target-row row mb-2';
             newRow.innerHTML = `
                 <div class="col-md-4">
                     <select class="form-select" name="muscle_targets[${muscleTargetCount}][muscle_code]">
                         <option value="">근육을 선택하세요</option>
                         <?php foreach ($muscles as $muscle): ?>
                             <option value="<?= $muscle['muscle_code'] ?>">
                                 <?= htmlspecialchars($muscle['body_part']) ?> - <?= htmlspecialchars($muscle['name_kr']) ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="col-md-3">
                     <select class="form-select" name="muscle_targets[${muscleTargetCount}][priority]">
                         <option value="1">주요 타겟</option>
                         <option value="2">보조 타겟</option>
                         <option value="3">보조 타겟</option>
                     </select>
                 </div>
                 <div class="col-md-3">
                     <input type="number" class="form-control" name="muscle_targets[${muscleTargetCount}][weight]" 
                            placeholder="가중치" step="0.01" min="0" max="2" value="1.00">
                 </div>
                 <div class="col-md-2">
                     <button type="button" class="btn btn-outline-danger btn-sm remove-muscle">
                         <i class="fas fa-trash"></i>
                     </button>
                 </div>
             `;
             container.appendChild(newRow);
             muscleTargetCount++;
             
             // 첫 번째 행이 아닌 경우 삭제 버튼 표시
             if (muscleTargetCount > 1) {
                 document.querySelectorAll('.remove-muscle').forEach(btn => btn.style.display = 'inline-block');
             }
         });
         
         // 세부존 타겟 동적 추가/제거
         document.getElementById('add-zone-target').addEventListener('click', function() {
             const container = document.getElementById('zone-targets');
             const newRow = document.createElement('div');
             newRow.className = 'zone-target-row row mb-2';
             newRow.innerHTML = `
                 <div class="col-md-4">
                     <select class="form-select" name="zone_targets[${zoneTargetCount}][zone_code]">
                         <option value="">세부존을 선택하세요</option>
                         <?php foreach ($zones as $zone): ?>
                             <option value="<?= $zone['zone_code'] ?>">
                                 <?= htmlspecialchars($zone['body_part']) ?> - <?= htmlspecialchars($zone['zone_name_kr']) ?>
                             </option>
                         <?php endforeach; ?>
                     </select>
                 </div>
                 <div class="col-md-3">
                     <select class="form-select" name="zone_targets[${zoneTargetCount}][priority]">
                         <option value="1">주요 타겟</option>
                         <option value="2">보조 타겟</option>
                         <option value="3">보조 타겟</option>
                     </select>
                 </div>
                 <div class="col-md-3">
                     <input type="number" class="form-control" name="zone_targets[${zoneTargetCount}][weight]" 
                            placeholder="가중치" step="0.01" min="0" max="2" value="1.00">
                 </div>
                 <div class="col-md-2">
                     <button type="button" class="btn btn-outline-danger btn-sm remove-zone">
                         <i class="fas fa-trash"></i>
                     </button>
                 </div>
             `;
             container.appendChild(newRow);
             zoneTargetCount++;
             
             // 첫 번째 행이 아닌 경우 삭제 버튼 표시
             if (zoneTargetCount > 1) {
                 document.querySelectorAll('.remove-zone').forEach(btn => btn.style.display = 'inline-block');
             }
         });
         
                   // 수정 모달용 근육 타겟 추가 함수
          function addEditMuscleTarget(muscleCode = '', priority = 1, weight = 1.00) {
              const container = document.getElementById('edit-muscle-targets');
              const newRow = document.createElement('div');
              newRow.className = 'muscle-target-row row mb-2';
              newRow.innerHTML = `
                  <div class="col-md-4">
                      <select class="form-select" name="muscle_targets[${muscleTargetCount}][muscle_code]">
                          <option value="">근육을 선택하세요</option>
                          <?php foreach ($muscles as $muscle): ?>
                              <option value="<?= $muscle['muscle_code'] ?>" ${muscleCode === '<?= $muscle['muscle_code'] ?>' ? 'selected' : ''}>
                                  <?= htmlspecialchars($muscle['body_part']) ?> - <?= htmlspecialchars($muscle['name_kr']) ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-3">
                      <select class="form-select" name="muscle_targets[${muscleTargetCount}][priority]">
                          <option value="1" ${priority == 1 ? 'selected' : ''}>주요 타겟</option>
                          <option value="2" ${priority == 2 ? 'selected' : ''}>보조 타겟</option>
                          <option value="3" ${priority == 3 ? 'selected' : ''}>보조 타겟</option>
                      </select>
                  </div>
                  <div class="col-md-3">
                      <input type="number" class="form-control" name="muscle_targets[${muscleTargetCount}][weight]" 
                             placeholder="가중치" step="0.01" min="0" max="2" value="${weight}">
                  </div>
                  <div class="col-md-2">
                      <button type="button" class="btn btn-outline-danger btn-sm remove-edit-muscle">
                          <i class="fas fa-trash"></i>
                      </button>
                  </div>
              `;
              container.appendChild(newRow);
              muscleTargetCount++;
              
              // 첫 번째 행이 아닌 경우 삭제 버튼 표시
              if (muscleTargetCount > 1) {
                  document.querySelectorAll('.remove-edit-muscle').forEach(btn => btn.style.display = 'inline-block');
              }
          }
          
          // 수정 모달용 세부존 타겟 추가 함수
          function addEditZoneTarget(zoneCode = '', priority = 1, weight = 1.00) {
              const container = document.getElementById('edit-zone-targets');
              const newRow = document.createElement('div');
              newRow.className = 'zone-target-row row mb-2';
              newRow.innerHTML = `
                  <div class="col-md-4">
                      <select class="form-select" name="zone_targets[${zoneTargetCount}][zone_code]">
                          <option value="">세부존을 선택하세요</option>
                          <?php foreach ($zones as $zone): ?>
                              <option value="<?= $zone['zone_code'] ?>" ${zoneCode === '<?= $zone['zone_code'] ?>' ? 'selected' : ''}>
                                  <?= htmlspecialchars($zone['body_part']) ?> - <?= htmlspecialchars($zone['zone_name_kr']) ?>
                              </option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-3">
                      <select class="form-select" name="zone_targets[${zoneTargetCount}][priority]">
                          <option value="1" ${priority == 1 ? 'selected' : ''}>주요 타겟</option>
                          <option value="2" ${priority == 2 ? 'selected' : ''}>보조 타겟</option>
                          <option value="3" ${priority == 3 ? 'selected' : ''}>보조 타겟</option>
                      </select>
                  </div>
                  <div class="col-md-3">
                      <input type="number" class="form-control" name="zone_targets[${zoneTargetCount}][weight]" 
                             placeholder="가중치" step="0.01" min="0" max="2" value="${weight}">
                  </div>
                  <div class="col-md-2">
                      <button type="button" class="btn btn-outline-danger btn-sm remove-edit-zone">
                          <i class="fas fa-trash"></i>
                      </button>
                  </div>
              `;
              container.appendChild(newRow);
              zoneTargetCount++;
              
              // 첫 번째 행이 아닌 경우 삭제 버튼 표시
              if (zoneTargetCount > 1) {
                  document.querySelectorAll('.remove-edit-zone').forEach(btn => btn.style.display = 'inline-block');
              }
          }
          
          // 수정 모달용 근육 타겟 추가 버튼 이벤트
          document.getElementById('edit-add-muscle-target').addEventListener('click', function() {
              addEditMuscleTarget();
          });
          
          // 수정 모달용 세부존 타겟 추가 버튼 이벤트
          document.getElementById('edit-add-zone-target').addEventListener('click', function() {
              addEditZoneTarget();
          });
          
                     // 삭제 버튼 이벤트 위임
           document.addEventListener('click', function(e) {
               if (e.target.closest('.remove-muscle')) {
                   e.target.closest('.muscle-target-row').remove();
               }
               if (e.target.closest('.remove-zone')) {
                   e.target.closest('.zone-target-row').remove();
               }
               if (e.target.closest('.remove-edit-muscle')) {
                   e.target.closest('.muscle-target-row').remove();
               }
               if (e.target.closest('.remove-edit-zone')) {
                   e.target.closest('.zone-target-row').remove();
               }
           });
           
           // 검색 및 필터링 기능
           function filterExercises() {
               const searchTerm = document.getElementById('searchInput').value.toLowerCase();
               const equipmentFilter = document.getElementById('equipmentFilter').value;
               const movementFilter = document.getElementById('movementFilter').value;
               const mappingFilter = document.getElementById('mappingFilter').value;
               
               const rows = document.querySelectorAll('.exercise-row');
               let visibleCount = 0;
               
               rows.forEach(row => {
                   const nameKr = row.getAttribute('data-name-kr').toLowerCase();
                   const nameEn = row.getAttribute('data-name-en').toLowerCase();
                   const equipment = row.getAttribute('data-equipment');
                   const movement = row.getAttribute('data-movement');
                   
                   // 검색어 필터링
                   const matchesSearch = nameKr.includes(searchTerm) || nameEn.includes(searchTerm);
                   
                   // 장비 필터링
                   const matchesEquipment = !equipmentFilter || equipment === equipmentFilter;
                   
                   // 동작 필터링
                   const matchesMovement = !movementFilter || movement === movementFilter;
                   
                   // 매핑 상태 필터링
                   let matchesMapping = true;
                   if (mappingFilter) {
                       const zoneCount = parseInt(row.querySelector('.stats-badge:first-child').textContent.trim());
                       const muscleCount = parseInt(row.querySelector('.stats-badge:last-child').textContent.trim());
                       
                       switch (mappingFilter) {
                           case 'no_mapping':
                               matchesMapping = zoneCount === 0 && muscleCount === 0;
                               break;
                           case 'no_muscle':
                               matchesMapping = muscleCount === 0;
                               break;
                           case 'no_zone':
                               matchesMapping = zoneCount === 0;
                               break;
                           case 'no_weight':
                               // 가중치 필터는 현재 구현되지 않음 (추후 확장 가능)
                               matchesMapping = true;
                               break;
                           case 'complete':
                               matchesMapping = zoneCount > 0 && muscleCount > 0;
                               break;
                       }
                   }
                   
                   // 모든 조건을 만족하는 경우에만 표시
                   if (matchesSearch && matchesEquipment && matchesMovement && matchesMapping) {
                       row.style.display = '';
                       visibleCount++;
                   } else {
                       row.style.display = 'none';
                   }
               });
               
               // 검색 결과 없음 메시지 표시/숨김
               const noResults = document.getElementById('noResults');
               if (visibleCount === 0) {
                   noResults.style.display = 'block';
               } else {
                   noResults.style.display = 'none';
               }
               
               // 운동 목록 헤더의 개수 업데이트
               const header = document.querySelector('.card-header h5');
               header.innerHTML = `<i class="fas fa-list me-2"></i>운동 목록 (${visibleCount}개)`;
           }
           
           // 검색 입력 이벤트
           document.getElementById('searchInput').addEventListener('input', filterExercises);
           
           // 장비 필터 이벤트
           document.getElementById('equipmentFilter').addEventListener('change', filterExercises);
           
           // 동작 필터 이벤트
           document.getElementById('movementFilter').addEventListener('change', filterExercises);
           
           // 매핑 필터 이벤트
           document.getElementById('mappingFilter').addEventListener('change', filterExercises);
           
           // 필터 초기화
           document.getElementById('clearFilters').addEventListener('click', function() {
               document.getElementById('searchInput').value = '';
               document.getElementById('equipmentFilter').value = '';
               document.getElementById('movementFilter').value = '';
               document.getElementById('mappingFilter').value = '';
               filterExercises();
           });
           
                       // 페이지 로드 시 초기 필터링
            document.addEventListener('DOMContentLoaded', function() {
                filterExercises();
                filterAliases();
            });
            
            // 동의어 검색 및 필터링 기능
            function filterAliases() {
                const searchTerm = document.getElementById('aliasSearchInput').value.toLowerCase();
                const rows = document.querySelectorAll('.alias-row');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const exerciseName = row.getAttribute('data-exercise-name').toLowerCase();
                    const alias = row.getAttribute('data-alias').toLowerCase();
                    
                    // 운동명 또는 동의어에서 검색어 포함 여부 확인
                    const matchesSearch = exerciseName.includes(searchTerm) || alias.includes(searchTerm);
                    
                    if (matchesSearch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // 검색 결과 없음 메시지 표시/숨김
                const noResults = document.getElementById('noAliasResults');
                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                } else {
                    noResults.style.display = 'none';
                }
                
                // 동의어 관리 헤더의 개수 업데이트
                const header = document.querySelector('.card-header h5');
                if (header && header.textContent.includes('동의어 관리')) {
                    header.innerHTML = `<i class="fas fa-tags me-2"></i>동의어 관리 (${visibleCount}개)`;
                }
            }
            
            // 동의어 검색 입력 이벤트
            document.getElementById('aliasSearchInput').addEventListener('input', filterAliases);
            
            // 동의어 필터 초기화
            document.getElementById('clearAliasFilters').addEventListener('click', function() {
                document.getElementById('aliasSearchInput').value = '';
                filterAliases();
            });
        </script>
</body>
</html>

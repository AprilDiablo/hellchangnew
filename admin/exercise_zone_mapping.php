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
                case 'add_mapping':
                    $pdo->beginTransaction();
                    
                    // 기존 매핑 삭제
                    $stmt = $pdo->prepare("DELETE FROM m_exercise_zone_target WHERE ex_id = ?");
                    $stmt->execute([$_POST['ex_id']]);
                    
                    // 새로운 매핑 추가
                    if (isset($_POST['zone_targets']) && is_array($_POST['zone_targets'])) {
                        $stmt = $pdo->prepare("INSERT INTO m_exercise_zone_target (ex_id, zone_code, weight) VALUES (?, ?, ?)");
                        foreach ($_POST['zone_targets'] as $zone) {
                            if (!empty($zone['zone_code']) && !empty($zone['weight'])) {
                                $stmt->execute([
                                    $_POST['ex_id'],
                                    $zone['zone_code'],
                                    $zone['weight']
                                ]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    header("Location: exercise_zone_mapping.php?message=mapping_added");
                    exit();
                    break;
                    
                case 'delete_mapping':
                    $stmt = $pdo->prepare("DELETE FROM m_exercise_zone_target WHERE ex_id = ?");
                    $stmt->execute([$_POST['ex_id']]);
                    header("Location: exercise_zone_mapping.php?message=mapping_deleted");
                    exit();
                    break;
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "오류가 발생했습니다: " . $e->getMessage();
    }
}

// 운동 목록 가져오기
$exercises = [];
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               COUNT(ez.zone_code) as zone_count,
               GROUP_CONCAT(DISTINCT z.zone_name_kr ORDER BY z.zone_name_kr SEPARATOR ', ') as zone_names
        FROM m_exercise e
        LEFT JOIN m_exercise_zone_target ez ON e.ex_id = ez.ex_id
        LEFT JOIN m_part_zone z ON ez.zone_code = z.zone_code
        GROUP BY e.ex_id
        ORDER BY e.name_kr
    ");
    $exercises = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "운동 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// 세부존 목록 가져오기
$zones = [];
try {
    $stmt = $pdo->query("
        SELECT z.*, p.part_name_kr as part_name
        FROM m_part_zone z
        JOIN m_body_part p ON z.part_code = p.part_code
        ORDER BY p.part_name_kr, z.zone_name_kr
    ");
    $zones = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "세부존 목록을 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}

// AJAX 요청 처리
if (isset($_GET['action']) && $_GET['action'] === 'get_mapping') {
    $ex_id = $_GET['ex_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT ez.zone_code, ez.weight, z.zone_name_kr as zone_name
            FROM m_exercise_zone_target ez
            JOIN m_part_zone z ON ez.zone_code = z.zone_code
            WHERE ez.ex_id = ?
            ORDER BY ez.weight DESC
        ");
        $stmt->execute([$ex_id]);
        $mappings = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode($mappings);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>운동-부위 매핑 관리 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .zone-badge {
            background: #e9ecef;
            color: #495057;
            padding: 3px 8px;
            border-radius: 15px;
            margin: 1px;
            display: inline-block;
            font-size: 0.8em;
        }
        .weight-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            margin-left: 5px;
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
        .search-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="exercise_management.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>운동 관리로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-link me-3"></i>운동-부위 매핑 관리
            </h1>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['message']) {
                    case 'mapping_added':
                        echo '운동-부위 매핑이 성공적으로 추가되었습니다.';
                        break;
                    case 'mapping_deleted':
                        echo '운동-부위 매핑이 성공적으로 삭제되었습니다.';
                        break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- 검색 섹션 -->
        <div class="search-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="운동명으로 검색...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="zoneFilter">
                        <option value="">모든 부위</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?= htmlspecialchars($zone['zone_name_kr']) ?>"><?= htmlspecialchars($zone['zone_name_kr']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                        <i class="fas fa-undo me-2"></i>초기화
                    </button>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <small class="text-muted">
                        검색 결과: <span id="resultCount"><?= count($exercises) ?></span>개
                    </small>
                </div>
            </div>
        </div>

        <!-- 운동 목록 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>운동-부위 매핑 목록 (<?= count($exercises) ?>개)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="exerciseTable">
                        <thead class="table-dark">
                            <tr>
                                <th>운동명</th>
                                <th>영문명</th>
                                <th>매핑된 부위</th>
                                <th>부위 개수</th>
                                <th>관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exercises as $exercise): ?>
                                <tr class="exercise-item" 
                                    data-exercise-name="<?= htmlspecialchars($exercise['name_kr']) ?>"
                                    data-zone-names="<?= htmlspecialchars($exercise['zone_names'] ?? '') ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($exercise['name_kr']) ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($exercise['name_en'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($exercise['zone_names']): ?>
                                            <div class="zone-list">
                                                <?php 
                                                $zoneArray = explode(', ', $exercise['zone_names']);
                                                foreach ($zoneArray as $zoneName): 
                                                ?>
                                                    <span class="zone-badge"><?= htmlspecialchars(trim($zoneName)) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">매핑된 부위 없음</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $exercise['zone_count'] ?>개</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="editMapping(<?= $exercise['ex_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>')">
                                                <i class="fas fa-edit me-1"></i>수정
                                            </button>
                                            <?php if ($exercise['zone_count'] > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteMapping(<?= $exercise['ex_id'] ?>, '<?= htmlspecialchars($exercise['name_kr']) ?>')">
                                                    <i class="fas fa-trash me-1"></i>삭제
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 매핑 수정 모달 -->
    <div class="modal fade" id="mappingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">운동-부위 매핑 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_mapping">
                        <input type="hidden" name="ex_id" id="modalExId">
                        
                        <div class="mb-3">
                            <label class="form-label">운동명</label>
                            <input type="text" class="form-control" id="modalExerciseName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">타겟 부위 설정</label>
                            <div id="zoneTargets">
                                <!-- 동적으로 추가될 부위 입력 행들 -->
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addZoneTarget()">
                                <i class="fas fa-plus me-1"></i>부위 추가
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">저장</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let zoneTargetCount = 0;
        
        // 검색 및 필터링
        function filterExercises() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const zoneFilter = document.getElementById('zoneFilter').value.toLowerCase();
            const items = document.querySelectorAll('.exercise-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                const exerciseName = item.getAttribute('data-exercise-name').toLowerCase();
                const zoneNames = item.getAttribute('data-zone-names').toLowerCase();
                
                const matchesSearch = exerciseName.includes(searchTerm);
                const matchesZone = !zoneFilter || zoneNames.includes(zoneFilter);
                
                if (matchesSearch && matchesZone) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // 결과 개수 표시
            const resultCount = document.getElementById('resultCount');
            if (resultCount) {
                resultCount.textContent = visibleCount;
            }
        }
        
        // 필터 초기화
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('zoneFilter').value = '';
            filterExercises();
        }
        
        // 매핑 수정
        function editMapping(exId, exerciseName) {
            document.getElementById('modalExId').value = exId;
            document.getElementById('modalExerciseName').value = exerciseName;
            
            // 기존 부위 목록 가져오기
            fetch(`exercise_zone_mapping.php?action=get_mapping&ex_id=${exId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    // 기존 부위 입력 행들 제거
                    document.getElementById('zoneTargets').innerHTML = '';
                    zoneTargetCount = 0;
                    
                            // 기존 매핑된 부위들 추가
        if (data.length > 0) {
            data.forEach(mapping => {
                addZoneTarget(mapping.zone_code, mapping.weight);
            });
        } else {
            // 기본 빈 행 하나 추가
            addZoneTarget();
        }
                })
                .catch(error => {
                    console.error('Error:', error);
                    addZoneTarget();
                });
            
            new bootstrap.Modal(document.getElementById('mappingModal')).show();
        }
        
        // 부위 타겟 행 추가
        function addZoneTarget(zoneCode = '', weight = '') {
            zoneTargetCount++;
            const row = document.createElement('div');
            row.className = 'row mb-2 zone-target-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <select class="form-select" name="zone_targets[${zoneTargetCount}][zone_code]" required>
                        <option value="">부위 선택</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?= $zone['zone_code'] ?>" ${zoneCode == '<?= $zone['zone_code'] ?>' ? 'selected' : ''}>
                                <?= htmlspecialchars($zone['part_name']) ?> - <?= htmlspecialchars($zone['zone_name_kr']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" class="form-control" name="zone_targets[${zoneTargetCount}][weight]" 
                           placeholder="가중치" min="1" max="10" value="${weight}" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeZoneTarget(this)">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            `;
            document.getElementById('zoneTargets').appendChild(row);
        }
        
        // 부위 타겟 행 제거
        function removeZoneTarget(button) {
            button.closest('.zone-target-row').remove();
        }
        
        // 매핑 삭제
        function deleteMapping(exId, exerciseName) {
            if (confirm(`"${exerciseName}"의 부위 매핑을 삭제하시겠습니까?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_mapping">
                    <input type="hidden" name="ex_id" value="${exId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // 이벤트 리스너
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('input', filterExercises);
            document.getElementById('zoneFilter').addEventListener('change', filterExercises);
        });
    </script>
</body>
</html>

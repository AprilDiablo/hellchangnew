<?php
session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 관리자 권한 확인
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: login.php');
    exit;
}
$pdo = getDB();

// 템플릿 ID 확인
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$template_id) {
    header('Location: workout_template_management.php');
    exit;
}

// 템플릿 정보 가져오기
$stmt = $pdo->prepare('SELECT * FROM m_workout_template WHERE template_id = ?');
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    header('Location: workout_template_management.php');
    exit;
}

// 페이지 제목 설정
$pageTitle = '템플릿 편집: ' . $template['template_name'];
$pageSubtitle = '운동 목록을 추가하고 편집하세요';

// 운동 계획 파싱 및 검색 결과
$parsedWorkouts = [];
$exerciseResults = [];

// 템플릿 내 운동 목록 가져오기
$stmt = $pdo->prepare('
    SELECT 
        te.*,
        e.name_kr as exercise_name_kr,
        e.name_en as exercise_name_en,
        te.exercise_name as custom_name
    FROM m_workout_template_exercise te
    LEFT JOIN m_exercise e ON te.ex_id = e.ex_id
    WHERE te.template_id = ?
    ORDER BY te.order_no ASC
');
$stmt->execute([$template_id]);
$exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 운동 계획 파싱 함수
function parseWorkoutPlan($text) {
    $lines = explode("\n", trim($text));
    $workouts = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // 웜업 여부 확인
        $is_warmup = 0;
        if (preg_match('/^웜업\s+(.+)$/', $line, $matches)) {
            $is_warmup = 1;
            $line = trim($matches[1]); // '웜업' 제거하고 앞뒤 공백 제거
        }

        $parts = preg_split('/\s+/', $line);

        if (count($parts) >= 1) {
            $exerciseName = '';
            $numbers = [];

            for ($i = 0; $i < count($parts); $i++) {
                if (is_numeric($parts[$i])) {
                    $numbers[] = (int)$parts[$i];
                } else {
                    if (empty($exerciseName)) {
                        $exerciseName = $parts[$i];
                    } else {
                        $exerciseName .= ' ' . $parts[$i];
                    }
                }
            }

            $weight = (int)($numbers[0] ?? 0);
            $reps = (int)($numbers[1] ?? 0);
            $sets = (int)($numbers[2] ?? 0);

            $workouts[] = [
                'exercise_name' => $exerciseName,
                'weight' => $weight,
                'reps' => $reps,
                'sets' => $sets,
                'is_warmup' => $is_warmup
            ];
        }
    }
    return $workouts;
}

// 운동 검색 함수
function searchExercise($pdo, $exerciseName) {
    $sql = "
        SELECT ex_id, name_kr, name_en, equipment, angle, movement
        FROM m_exercise
        WHERE name_kr LIKE ? OR name_en LIKE ? OR name_kr LIKE ? OR name_en LIKE ?
        ORDER BY 
            CASE 
                WHEN name_kr = ? THEN 1
                WHEN name_kr LIKE ? THEN 2
                WHEN name_en LIKE ? THEN 3
                ELSE 4
            END,
            name_kr
        LIMIT 10
    ";

    $params = [
        $exerciseName,
        $exerciseName,
        $exerciseName . '%',
        $exerciseName . '%',
        $exerciseName,
        $exerciseName . '%',
        $exerciseName . '%'
    ];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 운동 계획 파싱 및 검색
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exercise_plan']) && !empty($_POST['exercise_plan'])) {
    $workoutPlan = $_POST['exercise_plan'];
    $parsedWorkouts = parseWorkoutPlan($workoutPlan);
    
    // 각 운동에 대해 검색
    foreach ($parsedWorkouts as $workout) {
        $exerciseResults[$workout['exercise_name']] = searchExercise($pdo, $workout['exercise_name']);
    }
}

// 운동 목록 가져오기 (운동 추가용)
$stmt = $pdo->prepare('
    SELECT ex_id, name_kr, name_en, equipment, angle, movement
    FROM m_exercise
    ORDER BY name_kr ASC
');
$stmt->execute();
$exercise_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 운동 계획 파싱
$parsedWorkouts = [];
$exerciseResults = [];

// POST 요청 처리
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'search_exercises') {
            // 운동 계획 파싱 및 검색
            if (!empty($_POST['exercise_plan'])) {
                $parsedWorkouts = parseWorkoutPlan($_POST['exercise_plan']);
                
                // 각 운동에 대해 검색
                foreach ($parsedWorkouts as $workout) {
                    $exerciseResults[$workout['exercise_name']] = searchExercise($pdo, $workout['exercise_name']);
                }
            }
        }
        elseif ($_POST['action'] === 'save_exercises') {
            try {
                // 기존 운동들 삭제
                $stmt = $pdo->prepare('DELETE FROM m_workout_template_exercise WHERE template_id = ?');
                $stmt->execute([$template_id]);
                
                $added_count = 0;
                $current_order = 0;
                
                // POST 데이터에서 운동 정보 추출
                $index = 0;
                while (isset($_POST["exercise_name_{$index}"])) {
                    $exercise_name = $_POST["exercise_name_{$index}"];
                    $selected_exercise_id = $_POST["selected_exercise_{$index}"] ?? '';
                    $weight = (int)($_POST["weight_{$index}"] ?? 0);
                    $reps = (int)($_POST["reps_{$index}"] ?? 0);
                    $sets = (int)($_POST["sets_{$index}"] ?? 0);
                    $is_warmup = isset($_POST["warmup_{$index}"]) ? 1 : 0;
                    
                    if (!empty($exercise_name)) {
                        $current_order++;
                        
                        // 데이터베이스에 저장
                        $stmt = $pdo->prepare('
                            INSERT INTO m_workout_template_exercise 
                            (template_id, ex_id, exercise_name, order_no, weight, reps, sets, note, is_warmup) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $template_id, 
                            !empty($selected_exercise_id) ? $selected_exercise_id : null, 
                            $exercise_name, 
                            $current_order, 
                            $weight, 
                            $reps, 
                            $sets, 
                            '', 
                            $is_warmup
                        ]);
                        
                        $added_count++;
                    }
                    
                    $index++;
                }
                
                if ($added_count > 0) {
                    $_SESSION['success_message'] = "{$added_count}개의 운동이 업데이트되었습니다.";
                } else {
                    $_SESSION['error_message'] = "업데이트할 운동이 없습니다.";
                }
                
                header("Location: workout_template_edit.php?id={$template_id}");
                exit;
            } catch (Exception $e) {
                $error = "운동 업데이트 중 오류가 발생했습니다: " . $e->getMessage();
            }
        }
        
        elseif ($_POST['action'] === 'delete_exercise') {
            $exercise_id = (int)$_POST['exercise_id'];
            
            try {
                $stmt = $pdo->prepare('DELETE FROM m_workout_template_exercise WHERE id = ?');
                $stmt->execute([$exercise_id]);
                $_SESSION['success_message'] = "운동이 삭제되었습니다.";
                header("Location: workout_template_edit.php?id={$template_id}");
                exit;
            } catch (Exception $e) {
                $error = "운동 삭제 중 오류가 발생했습니다: " . $e->getMessage();
            }
        }
        
        elseif ($_POST['action'] === 'update_exercise') {
            $exercise_id = (int)$_POST['exercise_id'];
            $weight = (int)$_POST['weight'];
            $reps = (int)$_POST['reps'];
            $sets = (int)$_POST['sets'];
            $note = trim($_POST['note']);
            $is_warmup = isset($_POST['is_warmup']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare('
                    UPDATE m_workout_template_exercise 
                    SET weight = ?, reps = ?, sets = ?, note = ?, is_warmup = ?
                    WHERE id = ?
                ');
                $stmt->execute([$weight, $reps, $sets, $note, $is_warmup, $exercise_id]);
                
                $_SESSION['success_message'] = "운동 정보가 업데이트되었습니다.";
                header("Location: workout_template_edit.php?id={$template_id}");
                exit;
            } catch (Exception $e) {
                $error = "운동 업데이트 중 오류가 발생했습니다: " . $e->getMessage();
            }
        }
    }
}

// 메시지 처리 (세션에서 읽어서 JavaScript alert로 표시)
$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

// 메시지 표시 후 세션에서 제거
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// HTML 헤더
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>템플릿 편집 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .exercise-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .exercise-card .card-body {
            padding: 1.5rem;
        }
        .exercise-card .card-header {
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
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="workout_template_management.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>목록으로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-edit me-3"></i><?= htmlspecialchars($template['template_name']) ?>
            </h1>
        </div>

        <!-- 메시지 표시 -->
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

        <!-- 운동 추가 -->
        <div class="exercise-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>운동 추가
                </h5>
            </div>
            <div class="card-body">
                <form method="post" id="exerciseForm">
                    <div class="mb-3">
                        <label for="exercise_plan" class="form-label">
                            <strong>운동 계획을 입력하세요</strong>
                        </label>
                        <textarea 
                            class="form-control" 
                            id="exercise_plan" 
                            name="exercise_plan" 
                            rows="8" 
                            placeholder="예시:
사이드레터럴레이즈 5 15 1
비하인드넥프레스 20 12 4
머신숄더프레스 25 10 3
바벨오버헤드프레스 30 8 4
덤벨숄더프레스 12 12 3
바벨프론트레터럴레이즈 15 10 3
사이드레터럴레이즈 8 12 4

또는 운동명만:
사이드레터럴레이즈
비하인드넥프레스
머신숄더프레스

형식: 운동명 [무게(kg)] [반복(회)] [세트(개)]"
                        ><?php 
                            if (isset($_POST['exercise_plan'])) {
                                echo htmlspecialchars($_POST['exercise_plan']);
                            } elseif (!empty($exercises)) {
                                // 기존 운동들을 텍스트로 변환
                                $workoutText = '';
                                foreach ($exercises as $exercise) {
                                    $weight = $exercise['weight'] ?: 0;
                                    $reps = $exercise['reps'] ?: 0;
                                    $sets = $exercise['sets'] ?: 0;
                                    $warmupPrefix = $exercise['is_warmup'] ? '웜업 ' : '';
                                    $workoutText .= $warmupPrefix . $exercise['exercise_name'] . ' ' . number_format($weight, 0) . ' ' . $reps . ' ' . $sets . "\n";
                                }
                                echo htmlspecialchars(trim($workoutText));
                            }
                        ?></textarea>
                        <div class="form-text">
                            <strong>팁:</strong> 
                            • 한 줄에 하나씩 운동을 입력하세요<br>
                            • 운동명만 입력하면 무게, 횟수, 세트는 0으로 설정됩니다<br>
                            • 웜업 운동은 앞에 '웜업'을 붙이세요: <code>웜업 사이드레터럴레이즈</code>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="action" value="search_exercises" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>운동 검색
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($parsedWorkouts)): ?>
        <!-- 운동 계획 미리보기 -->
        <div class="exercise-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-eye me-2"></i>운동 계획 미리보기</h5>
                <small class="text-muted">운동을 선택하고 정보를 수정한 후 저장하세요</small>
            </div>
            <div class="card-body">
                <form method="post" id="saveForm">
                    <input type="hidden" name="action" value="save_exercises">
                    <input type="hidden" name="exercise_plan" value="<?= htmlspecialchars($_POST['exercise_plan'] ?? '') ?>">
                    
                    <div class="row">
                        <?php foreach ($parsedWorkouts as $index => $workout): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($workout['exercise_name']) ?></h6>
                                        <span class="badge bg-primary"><?= $index + 1 ?></span>
                                    </div>
                                    
                                    <!-- 검색 결과 표시 -->
                                    <div class="mb-2">
                                        <?php if (isset($exerciseResults[$workout['exercise_name']]) && !empty($exerciseResults[$workout['exercise_name']])): ?>
                                            <?php if (count($exerciseResults[$workout['exercise_name']]) == 1): ?>
                                                <span class="text-success">
                                                    ✓ <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_kr']) ?>
                                                </span>
                                                <input type="hidden" name="selected_exercise_<?= $index ?>" 
                                                       value="<?= $exerciseResults[$workout['exercise_name']][0]['ex_id'] ?>">
                                            <?php else: ?>
                                                <!-- 첫 번째 결과만 기본 표시 -->
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" 
                                                           name="selected_exercise_<?= $index ?>" 
                                                           id="ex_<?= $index ?>_0" 
                                                           value="<?= $exerciseResults[$workout['exercise_name']][0]['ex_id'] ?>" 
                                                           checked>
                                                    <label class="form-check-label" for="ex_<?= $index ?>_0">
                                                        <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_kr']) ?>
                                                        <?php if ($exerciseResults[$workout['exercise_name']][0]['name_en']): ?>
                                                            <small class="text-muted">(<?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_en']) ?>)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                                
                                                <!-- 나머지 결과들 (숨김) -->
                                                <div class="more-results">
                                                    <?php for ($i = 1; $i < count($exerciseResults[$workout['exercise_name']]); $i++): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="selected_exercise_<?= $index ?>" 
                                                               id="ex_<?= $index ?>_<?= $i ?>" 
                                                               value="<?= $exerciseResults[$workout['exercise_name']][$i]['ex_id'] ?>">
                                                        <label class="form-check-label" for="ex_<?= $index ?>_<?= $i ?>">
                                                            <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][$i]['name_kr']) ?>
                                                            <?php if ($exerciseResults[$workout['exercise_name']][$i]['name_en']): ?>
                                                                <small class="text-muted">(<?= htmlspecialchars($exerciseResults[$workout['exercise_name']][$i]['name_en']) ?>)</small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger">❌ 운동을 찾을 수 없습니다</span>
                                            <input type="hidden" name="selected_exercise_<?= $index ?>" value="">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- 운동 정보 입력 폼 -->
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="무게(kg)" 
                                                   min="0" 
                                                   step="1"
                                                   name="weight_<?= $index ?>"
                                                   value="<?= $workout['weight'] ? (int)$workout['weight'] : '' ?>">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="횟수" 
                                                   min="0"
                                                   name="reps_<?= $index ?>"
                                                   value="<?= $workout['reps'] ?? '' ?>">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="세트" 
                                                   min="0"
                                                   name="sets_<?= $index ?>"
                                                   value="<?= $workout['sets'] ?? '' ?>">
                                        </div>
                                    </div>
                                    
                                    <!-- 웜업 체크박스 -->
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" 
                                               id="warmup_<?= $index ?>" 
                                               name="warmup_<?= $index ?>"
                                               <?= $workout['is_warmup'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="warmup_<?= $index ?>">
                                            웜업 운동
                                        </label>
                                    </div>
                                    
                                    <input type="hidden" name="exercise_name_<?= $index ?>" value="<?= htmlspecialchars($workout['exercise_name']) ?>">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>운동 업데이트
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- 운동 목록 요약 -->
        <?php if (!empty($exercises)): ?>
        <div class="exercise-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>현재 운동 목록 
                    <span class="badge bg-primary"><?= count($exercises) ?>개</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($exercises as $index => $exercise): ?>
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <div>
                                <?php if ($exercise['is_warmup']): ?>
                                <span class="badge bg-warning text-dark me-1">웜업</span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($exercise['exercise_name']) ?></strong>
                                <small class="text-muted">
                                    <?= $exercise['weight'] ? (int)$exercise['weight'] . 'kg' : '0kg' ?> × 
                                    <?= $exercise['reps'] ? $exercise['reps'] . '회' : '0회' ?> × 
                                    <?= $exercise['sets'] ? $exercise['sets'] . '세트' : '0세트' ?>
                                </small>
                            </div>
                            <span class="badge bg-secondary"><?= $index + 1 ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        위 텍스트 영역에서 운동을 수정하고 "운동 업데이트" 버튼을 클릭하세요.
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
    
    function deleteExercise(exerciseId, exerciseName) {
        if (confirm('정말로 "' + exerciseName + '" 운동을 삭제하시겠습니까?')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = '<input type="hidden" name="action" value="delete_exercise">' +
                           '<input type="hidden" name="exercise_id" value="' + exerciseId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>

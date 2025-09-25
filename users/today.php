<?php
session_start();
require_once 'auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 로그인 확인
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// 날짜 파라미터 (기본값: 오늘)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 페이지 제목과 부제목 설정
$pageTitle = '운동 계획 입력';
$pageSubtitle = '오늘의 운동 계획을 세워보세요';

// 데이터베이스 연결
$pdo = getDB();

// 할당된 운동 템플릿 확인
$assignedTemplate = null;
$stmt = $pdo->prepare('
    SELECT ta.*, t.template_name, t.description, a.name as admin_name
    FROM m_template_assignment ta
    LEFT JOIN m_workout_template t ON ta.template_id = t.template_id
    LEFT JOIN admins a ON ta.assigned_by = a.id
    WHERE ta.user_id = ? AND ta.workout_date = ? AND ta.status = "assigned"
    ORDER BY ta.created_at DESC
    LIMIT 1
');
$stmt->execute([$user['id'], $date]);
$assignedTemplate = $stmt->fetch(PDO::FETCH_ASSOC);

// 할당된 템플릿의 운동들 가져오기
$assignedExercises = [];
if ($assignedTemplate) {
    $stmt = $pdo->prepare('
        SELECT * FROM m_workout_template_exercise 
        WHERE template_id = ? 
        ORDER BY order_no ASC
    ');
    $stmt->execute([$assignedTemplate['template_id']]);
    $assignedExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 기존 운동 세션 데이터 가져오기 (최근 30일)
$stmt = $pdo->prepare('
    SELECT 
        ws.session_id,
        ws.workout_date,
        ws.note,
        COUNT(we.wx_id) as exercise_count,
        GROUP_CONCAT(
            e.name_kr 
            ORDER BY we.order_no SEPARATOR ", "
        ) as exercise_summary
    FROM m_workout_session ws
    LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
    LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
    WHERE ws.user_id = ? 
    AND ws.workout_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY ws.session_id, ws.workout_date, ws.note
    ORDER BY ws.workout_date DESC, ws.session_id DESC
');
$stmt->execute([$user['id']]);
$workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 각 세션의 상세 운동 데이터 가져오기
$sessionDetails = [];
foreach ($workoutSessions as $session) {
    $stmt = $pdo->prepare('
        SELECT 
            we.wx_id,
            we.ex_id,
            we.weight,
            we.reps,
            we.sets,
            we.order_no,
            e.name_kr,
            e.name_en,
            e.equipment
        FROM m_workout_exercise we
        JOIN m_exercise e ON we.ex_id = e.ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$session['session_id']]);
    $sessionDetails[$session['session_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 수정 모드 확인
$editMode = false;
$editSessionId = null;
$editExerciseId = null;
$existingWorkouts = [];

if (isset($_GET['edit_session'])) {
    $editMode = true;
    $editSessionId = $_GET['edit_session'];
    $pageTitle = '운동 세션 수정';
    $pageSubtitle = '운동 세션을 수정하세요';
    
    // 기존 운동 세션 데이터 가져오기 (임시 운동 포함)
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT we.*, 
               COALESCE(e.name_kr, te.exercise_name) as name_kr,
               e.name_en, 
               e.equipment,
               we.is_temp,
               te.exercise_name as temp_exercise_name
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE we.session_id = ?
        ORDER BY we.order_no ASC
    ');
    $stmt->execute([$editSessionId]);
    $existingWorkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif (isset($_GET['edit_exercise'])) {
    $editMode = true;
    $editExerciseId = $_GET['edit_exercise'];
    $pageTitle = '운동 수정';
    $pageSubtitle = '운동을 수정하세요';
    
    // 기존 운동 데이터 가져오기 (임시 운동 포함)
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT we.*, 
               COALESCE(e.name_kr, te.exercise_name) as name_kr,
               e.name_en, 
               e.equipment,
               we.is_temp,
               te.exercise_name as temp_exercise_name
        FROM m_workout_exercise we
        LEFT JOIN m_exercise e ON we.ex_id = e.ex_id
        LEFT JOIN m_temp_exercise te ON we.temp_ex_id = te.temp_ex_id
        WHERE we.wx_id = ?
    ');
    $stmt->execute([$editExerciseId]);
    $existingWorkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 운동 계획 파싱
$parsedWorkouts = [];
$exerciseResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['workout_plan'])) {
    $workoutPlan = $_POST['workout_plan'];
    $parsedWorkouts = parseWorkoutPlan($workoutPlan);
    
    // 각 운동에 대해 검색
    $pdo = getDB();
    foreach ($parsedWorkouts as $workout) {
        $exerciseResults[$workout['exercise_name']] = searchExercise($pdo, $workout['exercise_name']);
    }
}

// 운동 계획 파싱 함수
function parseWorkoutPlan($text) {
    $lines = explode("\n", trim($text));
    $workouts = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

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

            $weight = $numbers[0] ?? 0;
            $reps = $numbers[1] ?? 0;
            $sets = $numbers[2] ?? 0;

            $workouts[] = [
                'exercise_name' => $exerciseName,
                'weight' => $weight,
                'reps' => $reps,
                'sets' => $sets
            ];
        }
    }
    return $workouts;
}

// 운동 검색 함수
function searchExercise($pdo, $exerciseName) {
    $searchWords = preg_split('/\s+/', trim($exerciseName));
    $conditions = [];
    $params = [];

    // 1. 공백 제거한 전체 검색어로 정확한 매칭 (최우선)
    $noSpaceTerm = str_replace(' ', '', $exerciseName);
    $conditions[] = "(REPLACE(e.name_kr, ' ', '') LIKE ? OR REPLACE(e.name_en, ' ', '') LIKE ? OR REPLACE(ea.alias, ' ', '') LIKE ?)";
    $params[] = '%' . $noSpaceTerm . '%';
    $params[] = '%' . $noSpaceTerm . '%';
    $params[] = '%' . $noSpaceTerm . '%';

    // 2. 전체 검색어로 정확한 매칭
    $conditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
    $searchTerm = '%' . $exerciseName . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    // 3. 단어별 검색 (모든 단어가 포함되어야 함)
    if (count($searchWords) > 1) {
        $wordConditions = [];
        foreach ($searchWords as $word) {
            if (strlen($word) > 1) {
                $wordConditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
                $wordTerm = '%' . $word . '%';
                $params[] = $wordTerm;
                $params[] = $wordTerm;
                $params[] = $wordTerm;
            }
        }
        if (!empty($wordConditions)) {
            $conditions[] = "(" . implode(' AND ', $wordConditions) . ")";
        }
    }

    $whereClause = implode(' OR ', $conditions);
    $stmt = $pdo->prepare('
        SELECT DISTINCT e.*,
               GROUP_CONCAT(DISTINCT ea.alias) as aliases
        FROM m_exercise e
        LEFT JOIN m_exercise_alias ea ON e.ex_id = ea.ex_id
        WHERE ' . $whereClause . '
        GROUP BY e.ex_id
        ORDER BY e.name_kr ASC
        LIMIT 5
    ');

    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as &$result) {
        $result['similarity_score'] = calculateSimilarity($exerciseName, $result['name_kr']);
    }

    usort($results, function($a, $b) {
        return $b['similarity_score'] <=> $a['similarity_score'];
    });

    return $results;
}

// 유사도 계산 함수
function calculateSimilarity($searchTerm, $exerciseName) {
    $searchTerm = strtolower(trim($searchTerm));
    $exerciseName = strtolower(trim($exerciseName));

    if ($searchTerm === $exerciseName) {
        return 100;
    }

    if (strpos($exerciseName, $searchTerm) !== false) {
        return 95;
    }

    if (strpos($searchTerm, $exerciseName) !== false) {
        return 90;
    }

    $fuzzyScore = calculateFuzzyScore($searchTerm, $exerciseName);
    $wordMatchScore = calculateWordMatchScore($searchTerm, $exerciseName);
    $phoneticScore = calculatePhoneticSimilarity($searchTerm, $exerciseName);

    $finalScore = ($fuzzyScore * 0.4) + ($wordMatchScore * 0.4) + ($phoneticScore * 0.2);

    return min(100, max(0, round($finalScore)));
}

// 퍼지 검색 점수 계산
function calculateFuzzyScore($str1, $str2) {
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    if ($len1 === 0 || $len2 === 0) { return 0; }
    $distance = levenshtein($str1, $str2);
    $maxDistance = max($len1, $len2);
    if ($maxDistance > 0) {
        $similarity = (1 - ($distance / $maxDistance)) * 100;
        return max(0, $similarity);
    }
    return 0;
}

// 단어 매칭 점수 계산
function calculateWordMatchScore($searchTerm, $exerciseName) {
    $searchWords = preg_split('/\s+/', $searchTerm);
    $exerciseWords = preg_split('/\s+/', $exerciseName);
    
    $matchedWords = 0;
    foreach ($searchWords as $searchWord) {
        foreach ($exerciseWords as $exerciseWord) {
            if (strpos($exerciseWord, $searchWord) !== false || strpos($searchWord, $exerciseWord) !== false) {
                $matchedWords++;
                break;
            }
        }
    }
    
    if (count($searchWords) > 0) {
        return ($matchedWords / count($searchWords)) * 100;
    }
    return 0;
}

// 음성 유사도 점수 계산
function calculatePhoneticSimilarity($str1, $str2) {
    $soundex1 = soundex($str1);
    $soundex2 = soundex($str2);
    
    if ($soundex1 === $soundex2) {
        return 100;
    }
    
    $similarity = 0;
    for ($i = 0; $i < 4; $i++) {
        if (isset($soundex1[$i]) && isset($soundex2[$i]) && $soundex1[$i] === $soundex2[$i]) {
            $similarity += 25;
        }
    }
    
    return $similarity;
}

// 헤더 포함
include 'header.php';
?>


<?php if ($assignedTemplate): ?>
<!-- 할당된 운동 템플릿 -->
<div class="card border-success mb-4">
    <div class="card-header bg-success text-white">
        <h4 class="mb-0">
            <i class="fas fa-gift me-2"></i>할당된 운동
            <small class="ms-2">by <?= htmlspecialchars($assignedTemplate['admin_name']) ?></small>
        </h4>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h5><?= htmlspecialchars($assignedTemplate['template_name']) ?></h5>
                <?php if ($assignedTemplate['description']): ?>
                <p class="text-muted"><?= htmlspecialchars($assignedTemplate['description']) ?></p>
                <?php endif; ?>
                <?php if ($assignedTemplate['note']): ?>
                <p class="text-info">
                    <i class="fas fa-sticky-note me-1"></i>
                    <?= htmlspecialchars($assignedTemplate['note']) ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-success" onclick="loadAssignedWorkout()">
                    <i class="fas fa-download me-2"></i>할당된 운동 불러오기
                </button>
            </div>
        </div>
        
        <div class="mt-3">
            <h6>운동 목록:</h6>
            <div class="row">
                <?php foreach ($assignedExercises as $index => $exercise): ?>
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                        <div>
                            <?php if ($exercise['is_warmup']): ?>
                            <span class="badge bg-warning text-dark me-1">웜업</span>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($exercise['exercise_name']) ?></strong>
                            <small class="text-muted d-block">
                                <?= $exercise['weight'] ? $exercise['weight'] . 'kg' : '0kg' ?> × 
                                <?= $exercise['reps'] ? $exercise['reps'] . '회' : '0회' ?> × 
                                <?= $exercise['sets'] ? $exercise['sets'] . '세트' : '0세트' ?>
                            </small>
                        </div>
                        <span class="badge bg-secondary"><?= $index + 1 ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 운동 계획 입력 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-dumbbell"></i> 운동 계획</h4>
        <div>
            <?php if (!empty($workoutSessions)): ?>
            <button type="button" class="btn btn-outline-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#loadWorkoutModal" title="기존 운동 불러오기">
                <i class="fas fa-history"></i>
            </button>
            <?php endif; ?>
            <button type="submit" form="workoutForm" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="post" id="workoutForm">
            <div class="mb-3">
                <label for="workout_plan" class="form-label">
                    <strong>운동 계획을 입력하세요</strong>
                </label>
                <textarea 
                    class="form-control" 
                    id="workout_plan" 
                    name="workout_plan" 
                    rows="8" 
                    placeholder="예시:
덤벨 벤치 프레스 10 15 5
바벨 스쿼트 20 10 3
라잉 트라이셉스 익스텐션 5 12 4

형식: 운동명 무게(kg) 반복(회) 세트(개)"
                ><?php 
                    if (isset($_POST['workout_plan'])) {
                        echo htmlspecialchars($_POST['workout_plan']);
                    } elseif ($editMode && isset($editExerciseId) && !empty($existingWorkouts)) {
                        // 개별 운동 수정 모드일 때
                        $workout = $existingWorkouts[0]; // 첫 번째 운동만 사용
                        $weight = $workout['weight'] ?: 0;
                        $reps = $workout['reps'] ?: 0;
                        $sets = $workout['sets'] ?: 0;
                        $workoutText = $workout['name_kr'] . ' ' . number_format($weight, 0) . ' ' . $reps . ' ' . $sets;
                        echo htmlspecialchars($workoutText);
                    } elseif ($editMode && !empty($existingWorkouts)) {
                        // 세션 수정 모드일 때 기존 데이터를 텍스트로 변환
                        $workoutText = '';
                        foreach ($existingWorkouts as $workout) {
                            $weight = $workout['weight'] ?: 0;
                            $reps = $workout['reps'] ?: 0;
                            $sets = $workout['sets'] ?: 0;
                            $workoutText .= $workout['name_kr'] . ' ' . number_format($weight, 0) . ' ' . $reps . ' ' . $sets . "\n";
                        }
                        echo htmlspecialchars(trim($workoutText));
                    }
                ?></textarea>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($parsedWorkouts)): ?>
<!-- 운동 계획 미리보기 -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-eye"></i> 운동 계획 미리보기</h5>
        <small class="text-muted">카드를 드래그하여 순서를 변경할 수 있습니다</small>
    </div>
    <div class="card-body">
        <div id="workout-preview-container" class="row">
            <?php foreach ($parsedWorkouts as $index => $workout): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card workout-card" data-index="<?= $index ?>">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="order-controls">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" 
                                        onclick="moveUp(<?= $index ?>)" 
                                        <?= $index == 0 ? 'disabled' : '' ?>
                                        title="위로 이동">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="moveDown(<?= $index ?>)" 
                                        <?= $index == count($parsedWorkouts) - 1 ? 'disabled' : '' ?>
                                        title="아래로 이동">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            <span class="badge bg-primary"><?= $index + 1 ?></span>
                        </div>
                        
                        <!-- 검색 결과 표시 -->
                        <div class="mb-2">
                            <?php if (isset($exerciseResults[$workout['exercise_name']]) && !empty($exerciseResults[$workout['exercise_name']])): ?>
                                <?php if (count($exerciseResults[$workout['exercise_name']]) == 1): ?>
                                    <span class="text-success" 
                                          data-exercise-name="<?= htmlspecialchars($workout['exercise_name']) ?>"
                                          data-exercise-id="<?= $exerciseResults[$workout['exercise_name']][0]['ex_id'] ?>">
                                        ✓ <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_kr']) ?>
                                    </span>
                                <?php else: ?>
                                    <!-- 첫 번째 결과만 기본 표시 -->
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="selected_exercise_<?= $workout['exercise_name'] ?>" 
                                               id="ex_<?= $workout['exercise_name'] ?>_0" 
                                               value="<?= $exerciseResults[$workout['exercise_name']][0]['ex_id'] ?>" 
                                               checked>
                                        <label class="form-check-label" for="ex_<?= $workout['exercise_name'] ?>_0">
                                            <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_kr']) ?>
                                            <?php if ($exerciseResults[$workout['exercise_name']][0]['name_en']): ?>
                                                <small class="text-muted">(<?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_en']) ?>)</small>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-link p-0 ms-2" 
                                                    onclick="toggleMoreResults('<?= preg_replace('/[^a-zA-Z0-9]/', '_', $workout['exercise_name']) ?>')"
                                                    title="더 보기">
                                                🔽
                                            </button>
                                        </label>
                                    </div>
                                    
                                    <!-- 나머지 결과들 (숨김) -->
                                    <div id="more_results_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $workout['exercise_name']) ?>" class="more-results" style="display: none;">
                                        <?php for ($i = 1; $i < count($exerciseResults[$workout['exercise_name']]); $i++): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="selected_exercise_<?= $workout['exercise_name'] ?>" 
                                                   id="ex_<?= $workout['exercise_name'] ?>_<?= $i ?>" 
                                                   value="<?= $exerciseResults[$workout['exercise_name']][$i]['ex_id'] ?>">
                                            <label class="form-check-label" for="ex_<?= $workout['exercise_name'] ?>_<?= $i ?>">
                                                <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][$i]['name_kr']) ?>
                                                <?php if ($exerciseResults[$workout['exercise_name']][$i]['name_en']): ?>
                                                    <small class="text-muted">(<?= htmlspecialchars($exerciseResults[$workout['exercise_name']][$i]['name_en']) ?>)</small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 운동 정보 입력 폼 (라디오버튼 선택된 운동용) -->
                                <div class="mt-2">
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="무게(kg)" 
                                                   min="0" 
                                                   step="0.5"
                                                   id="weight_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $workout['exercise_name']) ?>"
                                                   value="<?= $workout['weight'] ?? '' ?>">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="횟수" 
                                                   min="0"
                                                   id="reps_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $workout['exercise_name']) ?>"
                                                   value="<?= $workout['reps'] ?? '' ?>">
                                        </div>
                                        <div class="col-4">
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="세트" 
                                                   min="0"
                                                   id="sets_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $workout['exercise_name']) ?>"
                                                   value="<?= $workout['sets'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-warning">
                                    <strong><?= htmlspecialchars($workout['exercise_name']) ?></strong>
                                    <br>
                                    <small>⚠ 임시 운동으로 저장됩니다</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 운동 정보 표시 -->
                        <div class="text-muted">
                            <strong><?= number_format($workout['weight'], 0) ?>kg</strong> × <strong><?= $workout['reps'] ?>회</strong> × <strong><?= $workout['sets'] ?>세트</strong>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 운동 기록하기 버튼 -->
        <div class="text-center mt-3">
            <button type="button" class="btn btn-success btn-lg" onclick="saveWorkout()">
                <i class="fas fa-save"></i> <?= $editMode ? '운동 수정하기' : '운동 기록하기' ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 기존 운동 불러오기 모달 -->
<?php if (!empty($workoutSessions)): ?>
<div class="modal fade" id="loadWorkoutModal" tabindex="-1" aria-labelledby="loadWorkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history"></i> 기존 운동 불러오기
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php foreach ($workoutSessions as $session): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body p-3" style="cursor: pointer;" onclick="loadSession(<?= $session['session_id'] ?>)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">
                                            <?= date('m/d (D)', strtotime($session['workout_date'])) ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-primary"><?= $session['exercise_count'] ?>개</span>
                                </div>
                                
                                <!-- 운동 요약 -->
                                <div class="mt-2">
                                    <div class="mt-1">
                                        <small class="text-dark">
                                            <?php 
                                            if ($session['exercise_summary']) {
                                                $exercises = explode(', ', $session['exercise_summary']);
                                                $numberedExercises = array_map(function($exercise, $index) {
                                                    return ($index + 1) . '. ' . trim($exercise);
                                                }, $exercises, array_keys($exercises));
                                                echo htmlspecialchars(implode(', ', $numberedExercises));
                                            } else {
                                                echo '운동 없음';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <div class="text-muted">카드를 클릭하면 운동이 불러와집니다</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // 할당된 운동을 텍스트 영역에 불러오기
    function loadAssignedWorkout() {
        <?php if ($assignedTemplate && !empty($assignedExercises)): ?>
        let workoutText = '';
        <?php foreach ($assignedExercises as $exercise): ?>
        <?php
        $warmupPrefix = $exercise['is_warmup'] ? '웜업 ' : '';
        $weight = $exercise['weight'] ?: 0;
        $reps = $exercise['reps'] ?: 0;
        $sets = $exercise['sets'] ?: 0;
        ?>
        workoutText += '<?= $warmupPrefix ?><?= addslashes($exercise['exercise_name']) ?> <?= $weight ?> <?= $reps ?> <?= $sets ?>\n';
        <?php endforeach; ?>
        
        document.getElementById('workout_plan').value = workoutText.trim();
        
        // 운동 검색 실행
        document.querySelector('form').submit();
        <?php else: ?>
        alert('할당된 운동이 없습니다.');
        <?php endif; ?>
    }

    // 세션의 모든 운동을 텍스트 영역에 불러오기
    function loadSession(sessionId) {
        // 서버에서 해당 세션의 상세 운동 데이터 가져오기
        fetch('get_session_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'session_id=' + sessionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 운동 데이터를 텍스트로 변환 (번호 제거)
                const workoutTexts = data.exercises.map((exercise) => {
                    const weight = Math.floor(exercise.weight); // 소수점 제거
                    return exercise.name_kr + ' ' + weight + ' ' + exercise.reps + ' ' + exercise.sets;
                });
                
                // 텍스트 영역에 덮어쓰기
                const textarea = document.getElementById('workout_plan');
                const newText = workoutTexts.join('\n');
                textarea.value = newText;
                
                // 모달 닫기
                const modal = bootstrap.Modal.getInstance(document.getElementById('loadWorkoutModal'));
                if (modal) {
                    modal.hide();
                }
                
                // 자동으로 폼 제출하여 검색 실행
                document.getElementById('workoutForm').submit();
            } else {
                alert('세션 데이터를 가져오는 중 오류가 발생했습니다: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('세션 데이터를 가져오는 중 오류가 발생했습니다.');
        });
    }

    function requestExercise(exerciseName) {
        if (confirm('"' + exerciseName + '" 운동을 DB에 등록 요청하시겠습니까?')) {
            fetch('request_exercise.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'exercise_name=' + encodeURIComponent(exerciseName)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('등록 요청이 완료되었습니다.');
                        location.reload();
                    } else {
                        alert('등록 요청 중 오류가 발생했습니다: ' + data.message);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    alert('응답 처리 중 오류가 발생했습니다: ' + text);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('등록 요청 중 오류가 발생했습니다.');
            });
        }
    }

    function saveWorkout() {
        // 선택된 운동들 수집 (카드 순서대로)
        const workouts = [];
        
        // 카드 순서대로 운동 수집
        const cards = document.querySelectorAll('.workout-card');
        console.log('총 카드 개수:', cards.length);
        
        cards.forEach((card, index) => {
            console.log(`카드 ${index + 1} 처리 중:`, card);
            
            // data-index 속성에서 실제 순서 가져오기
            const actualIndex = parseInt(card.getAttribute('data-index')) || index;
            console.log(`실제 순서: ${actualIndex}`);
            
            // 1. 라디오 버튼이 있는 운동들 (여러 검색 결과)
            const checkedRadio = card.querySelector('input[type="radio"]:checked');
            if (checkedRadio) {
                const exerciseName = checkedRadio.name.replace('selected_exercise_', '');
                const exerciseId = checkedRadio.value;
                console.log(`라디오 버튼으로 찾은 운동: ${exerciseName}, ID: ${exerciseId}`);
                processWorkoutCard(card, exerciseName, exerciseId, workouts, actualIndex);
                return;
            }
            
            // 2. 라디오 버튼이 없는 운동들 (검색 결과 1개)
            const successSpan = card.querySelector('.text-success[data-exercise-name]');
            if (successSpan) {
                const exerciseName = successSpan.getAttribute('data-exercise-name');
                const exerciseId = successSpan.getAttribute('data-exercise-id');
                console.log(`성공 스팬으로 찾은 운동: ${exerciseName}, ID: ${exerciseId}`);
                processWorkoutCard(card, exerciseName, exerciseId, workouts, actualIndex);
                return;
            }
            
            // 3. 임시 운동 (text-warning 클래스)
            const tempExerciseDiv = card.querySelector('.text-warning');
            if (tempExerciseDiv) {
                const exerciseNameElement = tempExerciseDiv.querySelector('strong');
                if (exerciseNameElement) {
                    const exerciseName = exerciseNameElement.textContent.trim();
                    console.log(`임시 운동으로 찾은 운동: ${exerciseName}`);
                    processWorkoutCard(card, exerciseName, null, workouts, actualIndex);
                    return;
                }
            }
            
            console.log('운동을 찾을 수 없는 카드:', card);
        });
        
        console.log('총 수집된 운동 개수:', workouts.length);
        
        if (workouts.length === 0) {
            alert('선택된 운동이 없습니다.');
            return;
        }
        
        // order_no 순서대로 정렬
        workouts.sort((a, b) => a.order_no - b.order_no);
        console.log('정렬된 운동 순서:', workouts.map(w => `${w.order_no}: ${w.exercise_name}`));

        // DB에 저장
        console.log('전송할 데이터:', workouts);
        
        // 수정 모드인지 확인
        const editMode = <?= $editMode ? 'true' : 'false' ?>;
        const editSessionId = <?= $editSessionId ? $editSessionId : 'null' ?>;
        const editExerciseId = <?= $editExerciseId ? $editExerciseId : 'null' ?>;
        
        const requestData = {
            workouts: workouts,
            editMode: editMode,
            editSessionId: editSessionId,
            editExerciseId: editExerciseId,
            workoutDate: '<?= $date ?>'
        };
        
        fetch('save_workout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            
            // JSON 파싱 시도
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON:', data);
                
                if (data.success) {
                    alert('운동이 성공적으로 기록되었습니다!');
                    // 입력 페이지에서 조회 페이지로 이동
                    window.location.href = data.redirect_url || 'my_workouts.php';
                } else {
                    alert('운동 기록 중 오류가 발생했습니다: ' + data.message);
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Raw response:', text);
                alert('응답 처리 중 오류가 발생했습니다. 콘솔을 확인해주세요.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('운동 기록 중 오류가 발생했습니다.');
        });
    }
    
    // 운동 카드 처리 함수
    function processWorkoutCard(workoutCard, exerciseName, exerciseId, workouts, orderIndex) {
        console.log('처리 중인 운동:', exerciseName, exerciseId, '순서:', orderIndex);
        console.log('운동 카드:', workoutCard);
        
        // 임시 운동인지 확인 (exerciseId가 null이거나 없음)
        const isTemp = !exerciseId || exerciseId === 'null' || exerciseId === '';
        console.log('임시 운동 여부:', isTemp);
        
        // 라디오버튼으로 선택된 운동의 경우 입력 폼에서 값 가져오기
        console.log('운동명:', exerciseName);
        
        // 라디오버튼이 있는 카드에서 모든 number 입력 필드 찾기
        const allNumberInputs = workoutCard.querySelectorAll('input[type="number"]');
        console.log('카드 내 모든 number 입력 필드:', allNumberInputs.length);
        
        let weightInput = null, repsInput = null, setsInput = null;
        
        // weight, reps, sets 순서로 찾기
        for (let input of allNumberInputs) {
            if (input.id.includes('weight') && !weightInput) {
                weightInput = input;
            } else if (input.id.includes('reps') && !repsInput) {
                repsInput = input;
            } else if (input.id.includes('sets') && !setsInput) {
                setsInput = input;
            }
        }
        
        console.log('입력 폼 찾기 결과:', {
            weightInput: !!weightInput,
            repsInput: !!repsInput,
            setsInput: !!setsInput,
            weightValue: weightInput ? weightInput.value : 'N/A',
            repsValue: repsInput ? repsInput.value : 'N/A',
            setsValue: setsInput ? setsInput.value : 'N/A'
        });
        
        if (weightInput && repsInput && setsInput) {
            // 입력 폼에서 값 가져오기
            const weight = parseInt(weightInput.value) || 0;
            const reps = parseInt(repsInput.value) || 0;
            const sets = parseInt(setsInput.value) || 0;
            
            console.log('입력 폼에서 가져온 값:', { weight, reps, sets });
            
            const workoutData = {
                exercise_name: exerciseName,
                exercise_id: exerciseId,
                weight: weight,
                reps: reps,
                sets: sets,
                order_no: orderIndex + 1
            };
            
            // 임시 운동인 경우 is_temp 플래그 추가
            if (isTemp) {
                workoutData.is_temp = true;
            }
            
            workouts.push(workoutData);
            console.log('입력 폼에서 운동 추가됨:', workouts[workouts.length - 1]);
            return;
        }
        
        // 기존 방식: 운동 정보를 찾는 방법 개선
        let workoutInfo = null;
        
        // 1. text-muted 클래스를 가진 div 찾기 (운동 정보가 있는 곳)
        workoutInfo = workoutCard.querySelector('.text-muted');
        if (!workoutInfo) {
            // 2. 모든 div 중에서 운동 정보가 포함된 것 찾기 (kg, 회, 세트가 모두 포함된)
            const allDivs = workoutCard.querySelectorAll('div');
            for (let div of allDivs) {
                const text = div.textContent.trim();
                if (text.includes('kg') && text.includes('회') && text.includes('세트')) {
                    workoutInfo = div;
                    break;
                }
            }
        }
        
        // 3. 여전히 못 찾았다면, workoutCard 내의 모든 텍스트를 검색
        if (!workoutInfo) {
            const allText = workoutCard.textContent;
            if (allText.includes('kg') && allText.includes('회') && allText.includes('세트')) {
                // 임시로 workoutCard 자체를 사용
                workoutInfo = workoutCard;
            }
        }
        
        console.log('찾은 운동 정보:', workoutInfo);
        
        if (workoutInfo) {
            const infoText = workoutInfo.textContent.trim();
            console.log('운동 정보 텍스트:', infoText);
            
            // 다양한 패턴으로 매치 시도
            let match = infoText.match(/(\d+)kg\s*[×x]\s*(\d+)회\s*[×x]\s*(\d+)세트/);
            if (!match) {
                // strong 태그가 있는 경우도 처리
                match = infoText.match(/(\d+)kg.*?(\d+)회.*?(\d+)세트/);
            }
            if (!match) {
                // 더 간단한 패턴으로 시도
                match = infoText.match(/(\d+).*?kg.*?(\d+).*?회.*?(\d+).*?세트/);
            }
            
            console.log('정규식 매치 결과:', match);
            
            if (match) {
                const workoutData = {
                    exercise_name: exerciseName,
                    exercise_id: exerciseId,
                    weight: parseInt(match[1]),
                    reps: parseInt(match[2]),
                    sets: parseInt(match[3]),
                    order_no: orderIndex + 1  // 순서 번호 추가 (1부터 시작)
                };
                
                // 임시 운동인 경우 is_temp 플래그 추가
                if (isTemp) {
                    workoutData.is_temp = true;
                }
                
                workouts.push(workoutData);
                console.log('운동 추가됨:', workouts[workouts.length - 1]);
            } else {
                console.log('정규식 매치 실패, 텍스트:', infoText);
                // 매치 실패 시에도 기본값으로 저장 시도
                const workoutData = {
                    exercise_name: exerciseName,
                    exercise_id: exerciseId,
                    weight: 0,
                    reps: 0,
                    sets: 0,
                    order_no: orderIndex + 1
                };
                
                // 임시 운동인 경우 is_temp 플래그 추가
                if (isTemp) {
                    workoutData.is_temp = true;
                }
                
                workouts.push(workoutData);
                console.log('기본값으로 운동 추가됨:', workouts[workouts.length - 1]);
            }
        } else {
            console.log('운동 정보를 찾을 수 없음, 기본값으로 저장');
            // 운동 정보를 찾을 수 없어도 저장
            const workoutData = {
                exercise_name: exerciseName,
                exercise_id: exerciseId,
                weight: 0,
                reps: 0,
                sets: 0,
                order_no: orderIndex + 1
            };
            
            // 임시 운동인 경우 is_temp 플래그 추가
            if (isTemp) {
                workoutData.is_temp = true;
            }
            
            workouts.push(workoutData);
            console.log('기본값으로 운동 추가됨:', workouts[workouts.length - 1]);
        }
    }
    


    function toggleMoreResults(exerciseNameId) {
        const moreResultsDiv = document.getElementById(`more_results_${exerciseNameId}`);
        if (moreResultsDiv) {
            moreResultsDiv.style.display = moreResultsDiv.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    // 페이지 로드 시 뒤로가기 감지 및 처리
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            // 뒤로가기로 페이지가 로드된 경우 새로고침
            window.location.reload();
        }
    });
    
    // 브라우저의 뒤로가기/앞으로가기 버튼 사용 시 새로고침
    window.addEventListener('popstate', function(event) {
        window.location.reload();
    });
    
    // 페이지 언로드 시 히스토리 상태 추가
    window.addEventListener('beforeunload', function(event) {
        history.pushState(null, null, location.href);
    });
    
    // 순서 변경 기능
    function moveUp(index) {
        const container = document.getElementById('workout-preview-container');
        const cards = container.querySelectorAll('.workout-card');
        
        if (index > 0) {
            const currentCard = cards[index];
            const previousCard = cards[index - 1];
            
            // 부모 컨테이너 찾기 (col-md-6 col-lg-4 mb-3)
            const currentParent = currentCard.parentElement;
            const previousParent = previousCard.parentElement;
            
            // 부모 컨테이너끼리 위치 교환
            container.insertBefore(currentParent, previousParent);
            
            // 버튼 상태 업데이트
            updateButtonStates();
        }
    }
    
    function moveDown(index) {
        const container = document.getElementById('workout-preview-container');
        const cards = container.querySelectorAll('.workout-card');
        
        if (index < cards.length - 1) {
            const currentCard = cards[index];
            const nextCard = cards[index + 1];
            
            // 부모 컨테이너 찾기 (col-md-6 col-lg-4 mb-3)
            const currentParent = currentCard.parentElement;
            const nextParent = nextCard.parentElement;
            
            // 부모 컨테이너끼리 위치 교환
            if (nextParent.nextSibling) {
                container.insertBefore(currentParent, nextParent.nextSibling);
            } else {
                container.appendChild(currentParent);
            }
            
            // 버튼 상태 업데이트
            updateButtonStates();
        }
    }
    
    function updateButtonStates() {
        const cards = document.querySelectorAll('.workout-card');
        cards.forEach((card, index) => {
            const upButton = card.querySelector('button[onclick*="moveUp"]');
            const downButton = card.querySelector('button[onclick*="moveDown"]');
            
            // 위로 이동 버튼
            if (upButton) {
                upButton.disabled = index === 0;
                upButton.setAttribute('onclick', `moveUp(${index})`);
            }
            
            // 아래로 이동 버튼
            if (downButton) {
                downButton.disabled = index === cards.length - 1;
                downButton.setAttribute('onclick', `moveDown(${index})`);
            }
            
            // 순서 번호 업데이트
            const badge = card.querySelector('.badge');
            if (badge) {
                badge.textContent = index + 1;
            }
            
            // data-index 업데이트
            card.setAttribute('data-index', index);
        });
    }
</script>

<?php include 'footer.php'; ?>
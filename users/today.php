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

// 페이지 제목과 부제목 설정
$pageTitle = '운동 계획 입력';
$pageSubtitle = '오늘의 운동 계획을 세워보세요';

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

    foreach ($searchWords as $word) {
        if (strlen($word) > 1) {
            $conditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
            $searchTerm = '%' . $word . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
    }

    if (empty($conditions)) {
        $conditions[] = "(e.name_kr LIKE ? OR e.name_en LIKE ? OR ea.alias LIKE ?)";
        $searchTerm = '%' . $exerciseName . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
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

<!-- 운동 계획 입력 -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-dumbbell"></i> 운동 계획 입력</h4>
    </div>
    <div class="card-body">
        <form method="post">
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
                ><?= isset($_POST['workout_plan']) ? htmlspecialchars($_POST['workout_plan']) : '' ?></textarea>
            </div>
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-search"></i> 운동 검색하기
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($parsedWorkouts)): ?>
<!-- 운동 계획 미리보기 -->
<div class="card">
    <div class="card-header">
        <h4 class="mb-0"><i class="fas fa-list-check"></i> 운동 계획 미리보기</h4>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($parsedWorkouts as $workout): ?>
        <div class="mb-3">
            <!-- 검색어 표시 -->
            <div class="mb-1">
                <strong><?= htmlspecialchars($workout['exercise_name']) ?></strong>
            </div>
            
            <!-- 검색 결과 표시 -->
            <div class="ms-3">
                <?php if (isset($exerciseResults[$workout['exercise_name']]) && !empty($exerciseResults[$workout['exercise_name']])): ?>
                    <?php if (count($exerciseResults[$workout['exercise_name']]) == 1): ?>
                        <span class="text-success">✓ <?= htmlspecialchars($exerciseResults[$workout['exercise_name']][0]['name_kr']) ?></span>
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
                <?php else: ?>
                    <span class="text-danger">✗ 찾을 수 없음</span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" 
                            onclick="requestExercise('<?= htmlspecialchars($workout['exercise_name']) ?>')">
                        DB 등록 요청
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- 운동 정보 표시 -->
            <div class="ms-3 text-muted">
                <?= $workout['weight'] ?>kg <?= $workout['reps'] ?>회 <?= $workout['sets'] ?>세트
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        
        <!-- 운동 기록하기 버튼 -->
        <div class="text-center mt-3">
            <button type="button" class="btn btn-success btn-lg" onclick="saveWorkout()">
                <i class="fas fa-save"></i> 운동 기록하기
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
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
        // 선택된 운동들 수집
        const workouts = [];
        const workoutInputs = document.querySelectorAll('input[type="radio"]:checked');
        
        console.log('선택된 운동 개수:', workoutInputs.length);
        
        if (workoutInputs.length === 0) {
            alert('선택된 운동이 없습니다.');
            return;
        }

        workoutInputs.forEach((input, index) => {
            const exerciseName = input.name.replace('selected_exercise_', '');
            const exerciseId = input.value;
            const workoutCard = input.closest('.mb-3');
            
            console.log(`운동 ${index + 1}:`, exerciseName, exerciseId);
            console.log('운동 카드:', workoutCard);
            
            // 운동 정보를 찾는 방법 개선
            let workoutInfo = null;
            
            // 1. ms-3 text-muted 클래스를 모두 가진 div 찾기 (운동 정보가 있는 곳)
            workoutInfo = workoutCard.querySelector('.ms-3.text-muted');
            if (!workoutInfo) {
                // 2. ms-3 클래스만 가진 div 찾기
                workoutInfo = workoutCard.querySelector('.ms-3');
            }
            if (!workoutInfo) {
                // 3. 모든 div 중에서 운동 정보가 포함된 것 찾기 (kg, 회, 세트가 모두 포함된)
                const allDivs = workoutCard.querySelectorAll('div');
                for (let div of allDivs) {
                    const text = div.textContent.trim();
                    if (text.includes('kg') && text.includes('회') && text.includes('세트')) {
                        workoutInfo = div;
                        break;
                    }
                }
            }
            
            // 4. 여전히 못 찾았다면, workoutCard 내의 모든 텍스트를 검색
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
                
                const match = infoText.match(/(\d+)kg\s+(\d+)회\s+(\d+)세트/);
                console.log('정규식 매치 결과:', match);
                
                if (match) {
                    workouts.push({
                        exercise_name: exerciseName,
                        exercise_id: exerciseId,
                        weight: parseInt(match[1]),
                        reps: parseInt(match[2]),
                        sets: parseInt(match[3])
                    });
                    console.log('운동 추가됨:', workouts[workouts.length - 1]);
                } else {
                    console.log('정규식 매치 실패');
                }
            } else {
                console.log('운동 정보를 찾을 수 없음');
            }
        });

        console.log('최종 수집된 운동들:', workouts);

        if (workouts.length === 0) {
            alert('운동 정보를 가져올 수 없습니다.');
            return;
        }

        // DB에 저장
        console.log('전송할 데이터:', workouts);
        
        fetch('save_workout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(workouts)
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

    function toggleMoreResults(exerciseNameId) {
        const moreResultsDiv = document.getElementById(`more_results_${exerciseNameId}`);
        if (moreResultsDiv) {
            moreResultsDiv.style.display = moreResultsDiv.style.display === 'none' ? 'block' : 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>
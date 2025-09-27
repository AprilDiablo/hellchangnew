<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$pageTitle = '운동 분석';
$pageSubtitle = '운동 수행 분석을 확인해보세요';

// 날짜 파라미터 (기본값: 오늘)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$sessionIdParam = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;

$pdo = getDB();

// 세션 단건 보기 또는 날짜별 보기 분기
if ($sessionIdParam) {
    // 특정 세션의 운동 데이터 가져오기
    $stmt = $pdo->prepare('
        SELECT ws.*, 
               COUNT(we.wx_id) as exercise_count,
               SUM(we.weight * we.reps * we.sets) as total_volume
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        WHERE ws.session_id = ? AND ws.user_id = ?
        GROUP BY ws.session_id
    ');
    $stmt->execute([$sessionIdParam, $user['id']]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sessionRow) {
        $exerciseCount = $sessionRow['exercise_count'];
        $totalVolume = $sessionRow['total_volume'] ?: 0;
        $sessionRow['exercise_count'] = $exerciseCount;
        $sessionRow['total_volume'] = $totalVolume;
        $workoutSessions = [$sessionRow];
        // 단건 모드에서는 $date를 세션 날짜로 동기화
        $date = $sessionRow['workout_date'];
    } else {
        $workoutSessions = [];
    }
} else {
    // 해당 날짜의 모든 운동 세션 가져오기 (회차별로)
    $stmt = $pdo->prepare('
        SELECT ws.*, 
               COUNT(we.wx_id) as exercise_count,
               SUM(we.weight * we.reps * we.sets) as total_volume
        FROM m_workout_session ws
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        WHERE ws.user_id = ? AND ws.workout_date = ?
        GROUP BY ws.session_id
        ORDER BY ws.session_id DESC
    ');
    $stmt->execute([$user['id'], $date]);
    $workoutSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 각 세션별 운동 데이터 가져오기
$sessionsWithExercises = [];
$allMuscleAnalysis = [];
$performanceByBodyPart = [];
$performanceByMuscle = [];
$totalDayVolume = 0;
$totalWeightedVolume = 0;

foreach ($workoutSessions as $session) {
    $stmt = $pdo->prepare('
        SELECT we.*, e.name_kr as exercise_name, e.name_en as exercise_name_en
        FROM m_workout_exercise we
        JOIN m_exercise e ON we.exercise_code = e.exercise_code
        WHERE we.session_id = ?
        ORDER BY we.order_num
    ');
    $stmt->execute([$session['session_id']]);
    $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 각 운동별 완료 세트 수 계산
    foreach ($exercises as &$exercise) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as completed_sets
            FROM m_workout_set
            WHERE wx_id = ? AND is_completed = 1
        ');
        $stmt->execute([$exercise['wx_id']]);
        $completedSets = $stmt->fetch(PDO::FETCH_ASSOC);
        $exercise['completed_sets'] = $completedSets['completed_sets'];
    }
    
    $sessionsWithExercises[] = [
        'session' => $session,
        'exercises' => $exercises
    ];
    
    // 운동 분석 데이터 수집
    foreach ($exercises as $exercise) {
        $exerciseVolume = $exercise['weight'] * $exercise['reps'] * $exercise['sets'];
        $totalDayVolume += $exerciseVolume;
        
        // 완료된 운동의 근육 분석
        $stmt = $pdo->prepare('
            SELECT emt.*, m.name_kr as muscle_name, m.part_code, bp.part_name_kr
            FROM m_exercise_muscle_target emt
            JOIN m_muscle m ON emt.muscle_code = m.muscle_code
            JOIN m_body_part bp ON m.part_code = bp.part_code
            WHERE emt.exercise_code = ?
        ');
        $stmt->execute([$exercise['exercise_code']]);
        $muscleTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $exerciseVolumeByPart = [];
        foreach ($muscleTargets as $target) {
            $partCode = $target['part_code'];
            if (!isset($exerciseVolumeByPart[$partCode])) {
                $exerciseVolumeByPart[$partCode] = 0;
            }
            $exerciseVolumeByPart[$partCode] += $volume;
        }
        
        // 근육별 분석 (가중치 적용)
        foreach ($muscleTargets as $target) {
            $muscleCode = $target['muscle_code'];
            $partCode = $target['part_code'];
            $partName = $target['part_name_kr'];
            $weightedVolume = $exerciseVolume * $target['weight'];
            $totalWeightedVolume += $weightedVolume;
            
            if (!isset($allMuscleAnalysis[$muscleCode])) {
                $allMuscleAnalysis[$muscleCode] = [
                    'muscle_name' => $target['muscle_name'],
                    'part_name' => $partName,
                    'part_code' => $partCode,
                    'weighted_volume' => 0,
                    'percentage' => 0
                ];
            }
            $allMuscleAnalysis[$muscleCode]['weighted_volume'] += $weightedVolume;
        }
    }
}

// 퍼센트 계산
if ($totalWeightedVolume > 0) {
    foreach ($allMuscleAnalysis as $muscleCode => &$muscleData) {
        $muscleData['percentage'] = ($muscleData['weighted_volume'] / $totalWeightedVolume) * 100;
    }
}

// 날짜 포맷팅
$formattedDate = date('Y년 m월 d일', strtotime($date));
$dayOfWeek = ['일', '월', '화', '수', '목', '금', '토'][date('w', strtotime($date))];

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary mb-1"><?= $pageTitle ?></h2>
                    <p class="text-muted mb-0"><?= $pageSubtitle ?></p>
                </div>
                <div>
                    <a href="my_workouts_ing.php?date=<?= $date ?><?= $sessionIdParam ? '&session_id=' . $sessionIdParam : '' ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> 운동 기록으로 돌아가기
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 날짜 네비게이션 -->
<div class="date-navigation mb-4">
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?><?= $sessionIdParam ? '&session_id=' . $sessionIdParam : '' ?>" class="btn btn-outline-primary btn-custom">
        <i class="fas fa-chevron-left"></i>
    </a>
    <div class="date-display">
        <input type="date" id="datePicker" value="<?= $date ?>" onchange="changeDate(this.value)" class="form-control">
    </div>
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?><?= $sessionIdParam ? '&session_id=' . $sessionIdParam : '' ?>" class="btn btn-outline-primary btn-custom">
        <i class="fas fa-chevron-right"></i>
    </a>
</div>

<?php if (!empty($allMuscleAnalysis)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="text-primary mb-0">
                <i class="fas fa-chart-line"></i> 전체 운동 분석
            </h5>
            <div class="mt-2">
                <small class="text-muted">
                    총 볼륨: <?= number_format($totalDayVolume) ?>kg | 
                    가중치 볼륨: <?= number_format($totalWeightedVolume) ?>kg
                </small>
            </div>
        </div>
        <div class="card-body">
            <!-- 운동 수행률 요약 (계획 vs 수행) -->
            <div class="muscle-summary-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-info mb-0">
                        <i class="fas fa-chart-bar"></i> 운동 수행률 요약
                    </h6>
                    <!-- 범례 -->
                    <div>
                        <span class="badge bg-success me-2">수행률</span>
                        <span class="badge bg-info">계획률</span>
                    </div>
                </div>
                
                <?php
                // 계획된 운동 부위별 데이터
                $plannedParts = [];
                foreach ($allMuscleAnalysis as $muscleCode => $muscleData) {
                    if ($muscleData['percentage'] > 0) {
                        $partName = $muscleData['part_name'];
                        if (!isset($plannedParts[$partName])) {
                            $plannedParts[$partName] = 0;
                        }
                        $plannedParts[$partName] += $muscleData['percentage'];
                    }
                }
                
                // 수행된 운동 부위별 데이터
                $performedParts = [];
                foreach ($performanceByBodyPart as $partCode => $partData) {
                    if ($partData['percentage'] > 0) {
                        $partName = $partData['part_name'];
                        $performedParts[$partName] = $partData['percentage'];
                    }
                }
                
                // 모든 부위 통합 (계획 + 수행)
                $allParts = array_unique(array_merge(array_keys($plannedParts), array_keys($performedParts)));
                
                // 퍼센트 기준으로 정렬 (계획 기준)
                uasort($allParts, function($a, $b) use ($plannedParts) {
                    $aPercent = $plannedParts[$a] ?? 0;
                    $bPercent = $plannedParts[$b] ?? 0;
                    return $bPercent <=> $aPercent;
                });
                
                // 1, 2등과 기타 분리
                $topParts = array_slice($allParts, 0, 2, true);
                $otherParts = array_slice($allParts, 2, null, true);
                ?>
                
                <div class="row">
                    <!-- 1, 2등 부위 -->
                    <?php foreach ($topParts as $partName): ?>
                        <?php 
                        $plannedPercent = $plannedParts[$partName] ?? 0;
                        $performedPercent = $performedParts[$partName] ?? 0;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="part-summary-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($partName) ?></strong>
                                        <?php if ($performedPercent > 0): ?>
                                            <span class="badge bg-success ms-2"><?= round($performedPercent, 1) ?>%</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info ms-1"><?= round($plannedPercent, 1) ?>%</span>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                    <!-- 100% 회색 배경 -->
                                    <!-- 계획된 부분 (파란색) -->
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $plannedPercent ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                    <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                    <?php if ($performedPercent > 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $performedPercent ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- 기타 부위들 -->
                    <?php if (!empty($otherParts)): ?>
                        <?php 
                        $otherPlannedTotal = 0;
                        $otherPerformedTotal = 0;
                        foreach ($otherParts as $partName) {
                            $otherPlannedTotal += $plannedParts[$partName] ?? 0;
                            $otherPerformedTotal += $performedParts[$partName] ?? 0;
                        }
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="part-summary-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>기타</strong>
                                        <?php if ($otherPerformedTotal > 0): ?>
                                            <span class="badge bg-success ms-2"><?= round($otherPerformedTotal, 1) ?>%</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info ms-1"><?= round($otherPlannedTotal, 1) ?>%</span>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                    <!-- 100% 회색 배경 -->
                                    <!-- 계획된 부분 (파란색) -->
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPlannedTotal ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                    <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                    <?php if ($otherPerformedTotal > 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPerformedTotal ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 부위별 수행률 요약 (각 부위 100% 기준) -->
            <div class="muscle-summary-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-info mb-0">
                        <i class="fas fa-chart-bar"></i> 부위별 수행률 요약
                    </h6>
                    <!-- 범례 -->
                    <div>
                        <span class="badge bg-success me-2">수행률</span>
                        <span class="badge bg-info">계획률</span>
                    </div>
                </div>
                
                <?php
                // 각 부위별로 100% 기준으로 계산
                $partSummary100 = [];
                foreach ($allParts as $partName) {
                    $plannedPercent = $plannedParts[$partName] ?? 0;
                    $performedPercent = $performedParts[$partName] ?? 0;
                    
                    if ($plannedPercent > 0) {
                        // 각 부위를 100%로 정규화
                        $partSummary100[$partName] = [
                            'planned' => 100, // 항상 100%
                            'performed' => $plannedPercent > 0 ? round(($performedPercent / $plannedPercent) * 100, 1) : 0
                        ];
                    }
                }
                
                // 퍼센트 기준으로 정렬 (계획 기준)
                uasort($partSummary100, function($a, $b) use ($plannedParts, $partSummary100) {
                    $aKey = array_search($a, $partSummary100);
                    $bKey = array_search($b, $partSummary100);
                    $aPercent = $plannedParts[$aKey] ?? 0;
                    $bPercent = $plannedParts[$bKey] ?? 0;
                    return $bPercent <=> $aPercent;
                });
                
                // 1, 2등과 기타 분리
                $topParts100 = array_slice($partSummary100, 0, 2, true);
                $otherParts100 = array_slice($partSummary100, 2, null, true);
                ?>
                
                <div class="row">
                    <!-- 1, 2등 부위 -->
                    <?php foreach ($topParts100 as $partName => $data): ?>
                        <div class="col-md-6 mb-3">
                            <div class="part-summary-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($partName) ?></strong>
                                        <?php if ($data['performed'] > 0): ?>
                                            <span class="badge bg-success ms-2"><?= $data['performed'] ?>%</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info ms-1"><?= $data['planned'] ?>%</span>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                    <!-- 100% 회색 배경 -->
                                    <!-- 계획된 부분 (파란색) - 항상 100% -->
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: 100%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                    <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                    <?php if ($data['performed'] > 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $data['performed'] ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                    <?php endif; ?>
                                </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                    
                    <!-- 기타 부위들 -->
                    <?php if (!empty($otherParts100)): ?>
                        <?php 
                        $otherPlannedTotal100 = 0;
                        $otherPerformedTotal100 = 0;
                        foreach ($otherParts100 as $partName => $data) {
                            $otherPlannedTotal100 += $data['planned'];
                            $otherPerformedTotal100 += $data['performed'];
                        }
                        $otherCount = count($otherParts100);
                        $otherPlannedAvg = $otherCount > 0 ? $otherPlannedTotal100 / $otherCount : 0;
                        $otherPerformedAvg = $otherCount > 0 ? $otherPerformedTotal100 / $otherCount : 0;
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="part-summary-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>기타</strong>
                                        <?php if ($otherPerformedAvg > 0): ?>
                                            <span class="badge bg-success ms-2"><?= round($otherPerformedAvg, 1) ?>%</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info ms-1"><?= round($otherPlannedAvg, 1) ?>%</span>
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 12px; background-color: #e9ecef; position: relative;">
                                    <!-- 100% 회색 배경 -->
                                    <!-- 계획된 부분 (파란색) - 항상 100% -->
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: 100%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                    <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                    <?php if ($otherPerformedAvg > 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $otherPerformedAvg ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 근육 사용률 분석 (상세) -->
            <div class="muscle-analysis-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary mb-0">
                        <i class="fas fa-chart-pie"></i> 근육 사용률 분석 (상세)
                    </h6>
                    <!-- 범례 -->
                    <div>
                        <span class="badge bg-success me-2">수행률</span>
                        <span class="badge bg-info">계획률</span>
                    </div>
                </div>
                
                <div class="muscle-analysis">
                    <?php 
                    // 근육별 수행 데이터 수집
                    $musclePerformance = [];
                    foreach ($performanceByMuscle as $muscleCode => $muscleData) {
                        if ($muscleData['percentage'] > 0) {
                            $musclePerformance[$muscleCode] = $muscleData['percentage'];
                        }
                    }
                    
                    // 계획된 근육 데이터와 수행된 근육 데이터 통합
                    $allMuscleCodes = array_unique(array_merge(array_keys($allMuscleAnalysis), array_keys($musclePerformance)));
                    
                    // 퍼센트 기준으로 정렬 (계획 기준)
                    uasort($allMuscleCodes, function($a, $b) use ($allMuscleAnalysis) {
                        $aPercent = $allMuscleAnalysis[$a]['percentage'] ?? 0;
                        $bPercent = $allMuscleAnalysis[$b]['percentage'] ?? 0;
                        return $bPercent <=> $aPercent;
                    });
                    ?>
                    
                    <?php foreach ($allMuscleCodes as $muscleCode): ?>
                        <?php 
                        $muscleData = $allMuscleAnalysis[$muscleCode] ?? null;
                        $plannedPercent = $muscleData['percentage'] ?? 0;
                        $performedPercent = $musclePerformance[$muscleCode] ?? 0;
                        
                        if ($plannedPercent > 0): 
                        ?>
                            <div class="muscle-item mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($muscleData['muscle_name']) ?></strong>
                                        <small class="text-muted">(<?= htmlspecialchars($muscleData['part_name']) ?>)</small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($performedPercent > 0): ?>
                                            <span class="badge bg-success me-1"><?= round($performedPercent, 1) ?>%</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info"><?= round($plannedPercent, 1) ?>%</span>
                                        <br>
                                        <small class="text-muted"><?= number_format($muscleData['weighted_volume']) ?>kg</small>
                                    </div>
                                </div>
                                <div class="progress mt-1" style="height: 8px; background-color: #e9ecef; position: relative;">
                                    <!-- 100% 회색 배경 -->
                                    <!-- 계획된 부분 (파란색) -->
                                    <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $plannedPercent ?>%; background-color: #0dcaf0; border-radius: 0.375rem;"></div>
                                    <!-- 수행된 부분 (녹색) - 계획된 부분 위에 중첩 -->
                                    <?php if ($performedPercent > 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; height: 100%; width: <?= $performedPercent ?>%; background-color: #198754; border-radius: 0.375rem;"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
    </div>
<?php else: ?>
    <!-- 운동 기록 없음 -->
    <div class="card">
        <div class="card-body text-center">
            <i class="fas fa-chart-line fa-3x text-muted"></i>
            <h4 class="text-muted">분석할 운동 데이터가 없습니다</h4>
            <p class="text-muted">운동을 기록한 후 분석을 확인해보세요!</p>
            <a href="my_workouts_ing.php?date=<?= $date ?>" class="btn btn-primary btn-custom">
                <i class="fas fa-arrow-left"></i> 운동 기록으로 돌아가기
            </a>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>

<script>
// 날짜 변경 함수
function changeDate(dateString) {
    const urlParams = new URLSearchParams(window.location.search);
    const sessionId = urlParams.get('session_id');
    let url = '?date=' + dateString;
    if (sessionId) {
        url += '&session_id=' + sessionId;
    }
    window.location.href = url;
}
</script>

<style>
.date-navigation {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.date-display input {
    text-align: center;
    font-weight: bold;
}

.btn-custom {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.muscle-summary-section, .muscle-analysis-section {
    margin-bottom: 30px;
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background-color: #f8f9fa;
}

.part-summary-item, .muscle-item {
    padding: 15px;
    background-color: white;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.muscle-analysis {
    max-height: 400px;
    overflow-y: auto;
}
</style>

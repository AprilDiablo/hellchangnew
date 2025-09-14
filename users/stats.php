<?php
// AJAX 요청 처리 (header.php 로드 전에 처리)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once 'auth_check.php';
    
    $user = getCurrentUser();
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'get_body_part_stats') {
        try {
            $pdo = getDB();
            
            // 부위별 평균 무게 통계 계산
            $stmt = $pdo->prepare("
                SELECT 
                    bp.part_name_kr as body_part,
                    AVG(we.weight) as avg_weight,
                    COUNT(DISTINCT we.wx_id) as exercise_count,
                    COUNT(DISTINCT ws.session_id) as session_count,
                    MAX(we.weight) as max_weight,
                    MIN(we.weight) as min_weight
                FROM m_workout_exercise we
                JOIN m_exercise_muscle_target emt ON we.ex_id = emt.ex_id
                JOIN m_muscle m ON emt.muscle_code = m.muscle_code
                JOIN m_body_part bp ON m.part_code = bp.part_code
                JOIN m_workout_session ws ON we.session_id = ws.session_id
                WHERE ws.user_id = ?
                GROUP BY bp.part_code, bp.part_name_kr
                ORDER BY avg_weight DESC
            ");
            $stmt->execute([$user['id']]);
            $bodyPartStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 통계 데이터를 배열로 변환
            $stats = [];
            foreach ($bodyPartStats as $stat) {
                $stats[$stat['body_part']] = [
                    'avgWeight' => (float)$stat['avg_weight'],
                    'exerciseCount' => (int)$stat['exercise_count'],
                    'sessionCount' => (int)$stat['session_count'],
                    'maxWeight' => (float)$stat['max_weight'],
                    'minWeight' => (float)$stat['min_weight']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
        }
            
        } elseif ($_POST['action'] === 'get_date_weight_data') {
            $bodyPart = $_POST['body_part'] ?? '';
            
            try {
                $pdo = getDB();
                
                // 부위 목록 가져오기
                $stmt2 = $pdo->prepare("
                    SELECT DISTINCT bp.part_name_kr
                    FROM m_workout_exercise we
                    JOIN m_exercise_muscle_target emt ON we.ex_id = emt.ex_id
                    JOIN m_muscle m ON emt.muscle_code = m.muscle_code
                    JOIN m_body_part bp ON m.part_code = bp.part_code
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE ws.user_id = ?
                    ORDER BY bp.part_name_kr
                ");
                $stmt2->execute([$user['id']]);
                $bodyParts = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($bodyPart)) {
                    // 부위가 선택되지 않았으면 부위 목록만 반환
                    echo json_encode([
                        'success' => true,
                        'data' => [],
                        'bodyParts' => $bodyParts
                    ]);
                    exit;
                }
                
                // 날짜별 평균 무게 데이터 계산
                $stmt = $pdo->prepare("
                    SELECT 
                        ws.workout_date,
                        AVG(we.weight) as avg_weight,
                        COUNT(DISTINCT we.wx_id) as exercise_count
                    FROM m_workout_exercise we
                    JOIN m_exercise_muscle_target emt ON we.ex_id = emt.ex_id
                    JOIN m_muscle m ON emt.muscle_code = m.muscle_code
                    JOIN m_body_part bp ON m.part_code = bp.part_code
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE ws.user_id = ? AND bp.part_name_kr = ?
                    GROUP BY ws.workout_date
                    ORDER BY ws.workout_date ASC
                ");
                $stmt->execute([$user['id'], $bodyPart]);
                $dateWeightData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $dateWeightData,
                    'bodyParts' => $bodyParts
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        } elseif ($_POST['action'] === 'get_exercises_for_body_part') {
            $bodyPart = $_POST['body_part'] ?? '';
            
            if (empty($bodyPart)) {
                echo json_encode([
                    'success' => false,
                    'message' => '부위를 선택해주세요.'
                ]);
                exit;
            }
            
            try {
                $pdo = getDB();
                
                // 선택한 부위의 운동 목록 가져오기
                $stmt = $pdo->prepare("
                    SELECT DISTINCT 
                        e.ex_id,
                        e.name_kr,
                        e.name_en
                    FROM m_workout_exercise we
                    JOIN m_exercise e ON we.ex_id = e.ex_id
                    JOIN m_exercise_muscle_target emt ON we.ex_id = emt.ex_id
                    JOIN m_muscle m ON emt.muscle_code = m.muscle_code
                    JOIN m_body_part bp ON m.part_code = bp.part_code
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE ws.user_id = ? AND bp.part_name_kr = ?
                    ORDER BY e.name_kr
                ");
                $stmt->execute([$user['id'], $bodyPart]);
                $exercises = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'exercises' => $exercises
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        } elseif ($_POST['action'] === 'get_exercise_weight_data') {
            $exerciseId = $_POST['exercise_id'] ?? '';
            
            if (empty($exerciseId)) {
                echo json_encode([
                    'success' => false,
                    'message' => '운동을 선택해주세요.'
                ]);
                exit;
            }
            
            try {
                $pdo = getDB();
                
                // 선택한 운동의 날짜별 무게 데이터 계산
                $stmt = $pdo->prepare("
                    SELECT 
                        ws.workout_date,
                        AVG(we.weight) as avg_weight,
                        COUNT(we.wx_id) as exercise_count
                    FROM m_workout_exercise we
                    JOIN m_workout_session ws ON we.session_id = ws.session_id
                    WHERE ws.user_id = ? AND we.ex_id = ?
                    GROUP BY ws.workout_date
                    ORDER BY ws.workout_date ASC
                ");
                $stmt->execute([$user['id'], $exerciseId]);
                $exerciseWeightData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 운동 이름도 가져오기
                $stmt2 = $pdo->prepare("SELECT name_kr FROM m_exercise WHERE ex_id = ?");
                $stmt2->execute([$exerciseId]);
                $exerciseName = $stmt2->fetchColumn();
                
                echo json_encode([
                    'success' => true,
                    'data' => $exerciseWeightData,
                    'exerciseName' => $exerciseName
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
                exit;
            }
        }
} else {
    // 일반 페이지 로딩
    $pageTitle = "통계";
    require_once 'header.php';
    
    $user = getCurrentUser();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4"><i class="fas fa-chart-bar"></i> 운동 통계</h2>
        </div>
    </div>
    
    <!-- 통계 카드들 -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-dumbbell fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">총 운동일</h5>
                    <h3 class="text-primary">0일</h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-weight fa-3x text-success mb-3"></i>
                    <h5 class="card-title">총 볼륨</h5>
                    <h3 class="text-success">0kg</h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-fire fa-3x text-warning mb-3"></i>
                    <h5 class="card-title">평균 세트</h5>
                    <h3 class="text-warning">0세트</h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-trophy fa-3x text-info mb-3"></i>
                    <h5 class="card-title">완료율</h5>
                    <h3 class="text-info">0%</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 날짜별 평균 무게 그래프 -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-line"></i> 날짜별 평균 무게 변화</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="bodyPartSelect" class="form-label">부위 선택:</label>
                        <select class="form-select" id="bodyPartSelect" onchange="loadDateGraph()">
                            <option value="">부위를 선택하세요</option>
                        </select>
                    </div>
                    
                    <!-- 부위별 차트 -->
                    <div id="bodyPartChartContainer">
                        <canvas id="dateWeightChart" width="400" height="150"></canvas>
                    </div>
                    
                    <!-- 운동 목록 -->
                    <div id="exerciseList" class="mb-3" style="display: none;">
                        <label class="form-label">세부 운동 선택 (선택사항):</label>
                        <div id="exerciseButtons" class="d-flex flex-wrap gap-2">
                            <!-- 운동 버튼들이 여기에 표시됩니다 -->
                        </div>
                    </div>
                    
                    <!-- 운동별 차트 -->
                    <div id="exerciseChartContainer" style="display: none;">
                        <h6 class="mt-4 mb-3"><i class="fas fa-chart-line"></i> 개별 운동 차트</h6>
                        <canvas id="exerciseWeightChart" width="400" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 차트 영역 -->
    <div class="row">
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> 부위별 운동</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-3">부위별 운동 통계를 확인하세요</p>
                    <button type="button" class="btn btn-success" onclick="openBodyPartStats()">
                        <i class="fas fa-eye"></i> 부위별 통계 보기
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 상세 통계 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-list"></i> 상세 통계</h6>
                </div>
                <div class="card-body">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-cog fa-3x mb-3"></i>
                        <p>상세 통계 기능 준비 중...</p>
                        <small>곧 다양한 통계 정보를 제공할 예정입니다.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 부위별 통계 모달 -->
<div class="modal fade" id="bodyPartStatsModal" tabindex="-1" aria-labelledby="bodyPartStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="bodyPartStatsModalLabel">
                    <i class="fas fa-chart-pie"></i> 부위별 운동 통계
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div id="bodyPartStatsContent">
                    <div class="text-center">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">로딩 중...</span>
                        </div>
                        <p class="mt-2">통계 데이터를 불러오는 중...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25);
}

.card-body i {
    opacity: 0.7;
}

.text-primary { color: #4e73df !important; }
.text-success { color: #1cc88a !important; }
.text-warning { color: #f6c23e !important; }
.text-info { color: #36b9cc !important; }

.body-part-item {
    border-left: 4px solid #1cc88a;
    padding-left: 15px;
    margin-bottom: 15px;
    background: #f8f9fa;
    border-radius: 0 8px 8px 0;
}

.body-part-name {
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.body-part-stats {
    color: #6c757d;
    font-size: 0.9em;
}

.progress {
    height: 8px;
    border-radius: 4px;
}

.weight-badge {
    background: linear-gradient(45deg, #1cc88a, #17a2b8);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

#dateWeightChart {
    max-height: 300px !important;
    height: 300px !important;
}

#exerciseWeightChart {
    max-height: 300px !important;
    height: 300px !important;
}
</style>

<script>
// 부위별 통계 모달 열기
function openBodyPartStats() {
    const modal = new bootstrap.Modal(document.getElementById('bodyPartStatsModal'));
    modal.show();
    
    // 통계 데이터 로드
    loadBodyPartStats();
}

// 부위별 통계 데이터 로드
function loadBodyPartStats() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_body_part_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayBodyPartStats(data.stats);
        } else {
            document.getElementById('bodyPartStatsContent').innerHTML = 
                '<div class="alert alert-danger">통계 데이터를 불러올 수 없습니다.</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('bodyPartStatsContent').innerHTML = 
            '<div class="alert alert-danger">오류가 발생했습니다.</div>';
    });
}

// 부위별 통계 표시
function displayBodyPartStats(stats) {
    let html = '<div class="row">';
    
    // 최대 평균 무게 계산 (진행률 바용)
    const maxAvgWeight = Math.max(...Object.values(stats).map(stat => stat.avgWeight));
    
    // 부위별 통계 표시
    Object.entries(stats).forEach(([bodyPart, stat]) => {
        const percentage = maxAvgWeight > 0 ? (stat.avgWeight / maxAvgWeight * 100) : 0;
        const progressWidth = Math.min(percentage, 100);
        
        html += `
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="body-part-item">
                    <div class="body-part-name">${bodyPart}</div>
                    <div class="body-part-stats">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>평균 무게: <strong>${stat.avgWeight.toFixed(1)}kg</strong></span>
                            <span class="weight-badge">${stat.sessionCount}회차</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" style="width: ${progressWidth}%"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small>최대: ${stat.maxWeight.toFixed(1)}kg</small>
                            <small>최소: ${stat.minWeight.toFixed(1)}kg</small>
                        </div>
                        <div class="text-center mt-2">
                            <small class="text-muted">${stat.exerciseCount}개 운동</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // 총계 표시
    const totalSessions = Object.values(stats).reduce((sum, stat) => sum + stat.sessionCount, 0);
    const totalExercises = Object.values(stats).reduce((sum, stat) => sum + stat.exerciseCount, 0);
    const overallAvgWeight = Object.values(stats).reduce((sum, stat) => sum + stat.avgWeight, 0) / Object.keys(stats).length;
    
    html += `
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="mb-2"><i class="fas fa-trophy"></i> 전체 수행 능력</h6>
            <div class="row text-center">
                <div class="col-4">
                    <strong class="text-success">${overallAvgWeight.toFixed(1)}kg</strong>
                    <br><small>전체 평균 무게</small>
                </div>
                <div class="col-4">
                    <strong class="text-primary">${Object.keys(stats).length}개</strong>
                    <br><small>운동 부위</small>
                </div>
                <div class="col-4">
                    <strong class="text-info">${totalSessions}회차</strong>
                    <br><small>총 운동 횟수</small>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('bodyPartStatsContent').innerHTML = html;
}

// Chart.js 인스턴스
let dateWeightChart = null;
let exerciseWeightChart = null;

// 페이지 로드 시 부위 목록 로드
document.addEventListener('DOMContentLoaded', function() {
    loadBodyPartList();
});

// 부위 목록 로드
function loadBodyPartList() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_date_weight_data&body_part='
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('bodyPartSelect');
            select.innerHTML = '<option value="">부위를 선택하세요</option>';
            
            data.bodyParts.forEach(bodyPart => {
                const option = document.createElement('option');
                option.value = bodyPart;
                option.textContent = bodyPart;
                select.appendChild(option);
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// 날짜별 그래프 로드 (부위별)
function loadDateGraph() {
    const selectedBodyPart = document.getElementById('bodyPartSelect').value;
    const exerciseList = document.getElementById('exerciseList');
    const exerciseChartContainer = document.getElementById('exerciseChartContainer');
    
    if (!selectedBodyPart) {
        exerciseList.style.display = 'none';
        exerciseChartContainer.style.display = 'none';
        if (dateWeightChart) {
            dateWeightChart.destroy();
            dateWeightChart = null;
        }
        if (exerciseWeightChart) {
            exerciseWeightChart.destroy();
            exerciseWeightChart = null;
        }
        return;
    }
    
    // 1. 부위별 차트 로드
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=get_date_weight_data&body_part=${encodeURIComponent(selectedBodyPart)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            createBodyPartChart(data.data, selectedBodyPart);
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
    
    // 2. 운동 목록 로드
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=get_exercises_for_body_part&body_part=${encodeURIComponent(selectedBodyPart)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayExerciseButtons(data.exercises);
            exerciseList.style.display = 'block';
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// 운동 버튼들 표시
function displayExerciseButtons(exercises) {
    const exerciseButtons = document.getElementById('exerciseButtons');
    exerciseButtons.innerHTML = '';
    
    exercises.forEach(exercise => {
        const button = document.createElement('button');
        button.className = 'btn btn-outline-primary btn-sm';
        button.textContent = exercise.name_kr;
        button.onclick = () => loadExerciseWeightData(exercise.ex_id, exercise.name_kr);
        exerciseButtons.appendChild(button);
    });
}

// 운동별 날짜별 무게 데이터 로드
function loadExerciseWeightData(exerciseId, exerciseName) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=get_exercise_weight_data&exercise_id=${exerciseId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            createExerciseChart(data.data, data.exerciseName);
            document.getElementById('exerciseChartContainer').style.display = 'block';
        } else {
            console.error('Error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// 부위별 차트 생성
function createBodyPartChart(data, bodyPart) {
    const ctx = document.getElementById('dateWeightChart').getContext('2d');
    
    // 기존 차트가 있으면 제거
    if (dateWeightChart) {
        dateWeightChart.destroy();
    }
    
    const labels = data.map(item => item.workout_date);
    const weights = data.map(item => parseFloat(item.avg_weight));
    const exerciseCounts = data.map(item => parseInt(item.exercise_count));
    
    // Y축 범위 계산
    const minWeight = Math.min(...weights);
    const maxWeight = Math.max(...weights);
    const range = maxWeight - minWeight;
    const padding = Math.max(Math.round(range * 0.1), 1); // 10% 패딩, 최소 1kg
    const yMin = Math.max(0, Math.floor(minWeight - padding));
    const yMax = Math.ceil(maxWeight + padding);
    
    dateWeightChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: `${bodyPart} 평균 무게 (kg)`,
                data: weights,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#1cc88a',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: `${bodyPart} 날짜별 평균 무게 변화`,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const index = context.dataIndex;
                            return `운동 종류: ${exerciseCounts[index]}개`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '날짜'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: '평균 무게 (kg)'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    },
                    min: yMin,
                    max: yMax,
                    ticks: {
                        stepSize: Math.max(1, Math.round(range / 10)), // 최대 10개 구간, 1kg 단위
                        callback: function(value) {
                            return Math.round(value) + 'kg';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// 운동별 차트 생성
function createExerciseChart(data, exerciseName) {
    const ctx = document.getElementById('exerciseWeightChart').getContext('2d');
    
    // 기존 차트가 있으면 제거
    if (exerciseWeightChart) {
        exerciseWeightChart.destroy();
    }
    
    const labels = data.map(item => item.workout_date);
    const weights = data.map(item => parseFloat(item.avg_weight));
    const exerciseCounts = data.map(item => parseInt(item.exercise_count));
    
    // Y축 범위 계산
    const minWeight = Math.min(...weights);
    const maxWeight = Math.max(...weights);
    const range = maxWeight - minWeight;
    const padding = Math.max(Math.round(range * 0.1), 1); // 10% 패딩, 최소 1kg
    const yMin = Math.max(0, Math.floor(minWeight - padding));
    const yMax = Math.ceil(maxWeight + padding);
    
    exerciseWeightChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: `${exerciseName} 평균 무게 (kg)`,
                data: weights,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4e73df',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: `${exerciseName} 날짜별 평균 무게 변화`,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const index = context.dataIndex;
                            return `운동 횟수: ${exerciseCounts[index]}회`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '날짜'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: '평균 무게 (kg)'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0,0,0,0.1)'
                    },
                    min: yMin,
                    max: yMax,
                    ticks: {
                        stepSize: Math.max(1, Math.round(range / 10)), // 최대 10개 구간, 1kg 단위
                        callback: function(value) {
                            return Math.round(value) + 'kg';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>

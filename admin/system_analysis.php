<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();
$error = '';

// 통계 데이터 가져오기
$stats = [];
try {
    // 기본 통계
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise");
    $stats['total_exercises'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_part_zone");
    $stats['total_zones'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_muscle");
    $stats['total_muscles'] = $stmt->fetch()['count'];
    
    // 매핑 통계
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise_zone_target");
    $stats['zone_mappings'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise_muscle_target");
    $stats['muscle_mappings'] = $stmt->fetch()['count'];
    
    // 매핑된 운동 개수
    $stmt = $pdo->query("SELECT COUNT(DISTINCT ex_id) as count FROM m_exercise_zone_target");
    $stats['exercises_with_zones'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT ex_id) as count FROM m_exercise_muscle_target");
    $stats['exercises_with_muscles'] = $stmt->fetch()['count'];
    
    // 매핑 비율 계산
    $stats['zone_mapping_rate'] = $stats['total_exercises'] > 0 ? round(($stats['exercises_with_zones'] / $stats['total_exercises']) * 100, 1) : 0;
    $stats['muscle_mapping_rate'] = $stats['total_exercises'] > 0 ? round(($stats['exercises_with_muscles'] / $stats['total_exercises']) * 100, 1) : 0;
    
    // 상위 매핑 운동
    $stmt = $pdo->query("
        SELECT e.name_kr, COUNT(ez.zone_code) as zone_count
        FROM m_exercise e
        JOIN m_exercise_zone_target ez ON e.ex_id = ez.ex_id
        GROUP BY e.ex_id
        ORDER BY zone_count DESC
        LIMIT 10
    ");
    $stats['top_zone_exercises'] = $stmt->fetchAll();
    
    $stmt = $pdo->query("
        SELECT e.name_kr, COUNT(em.muscle_code) as muscle_count
        FROM m_exercise e
        JOIN m_exercise_muscle_target em ON e.ex_id = em.ex_id
        GROUP BY e.ex_id
        ORDER BY muscle_count DESC
        LIMIT 10
    ");
    $stats['top_muscle_exercises'] = $stmt->fetchAll();
    
    // 매핑되지 않은 운동
    $stmt = $pdo->query("
        SELECT e.name_kr
        FROM m_exercise e
        LEFT JOIN m_exercise_zone_target ez ON e.ex_id = ez.ex_id
        WHERE ez.ex_id IS NULL
        ORDER BY e.name_kr
        LIMIT 20
    ");
    $stats['unmapped_exercises'] = $stmt->fetchAll();
    
    // 부위별 매핑 현황
    $stmt = $pdo->query("
        SELECT z.zone_name_kr, COUNT(ez.ex_id) as exercise_count
        FROM m_part_zone z
        LEFT JOIN m_exercise_zone_target ez ON z.zone_code = ez.zone_code
        GROUP BY z.zone_code
        ORDER BY exercise_count DESC
    ");
    $stats['zone_usage'] = $stmt->fetchAll();
    
    // 근육별 매핑 현황
    $stmt = $pdo->query("
        SELECT m.name_kr, COUNT(em.ex_id) as exercise_count
        FROM m_muscle m
        LEFT JOIN m_exercise_muscle_target em ON m.muscle_code = em.muscle_code
        GROUP BY m.muscle_code
        ORDER BY exercise_count DESC
    ");
    $stats['muscle_usage'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "통계 정보를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>시스템 분석 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .analysis-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .analysis-card .card-body {
            padding: 1.5rem;
        }
        .analysis-card .card-header {
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
        .progress-custom {
            height: 25px;
            border-radius: 15px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
                <i class="fas fa-chart-bar me-3"></i>시스템 분석
            </h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- 전체 통계 요약 -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-dumbbell fa-2x mb-2"></i>
                    <h4><?= number_format($stats['total_exercises']) ?></h4>
                    <small>전체 운동</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                    <h4><?= number_format($stats['total_zones']) ?></h4>
                    <small>세부부위</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-muscle fa-2x mb-2"></i>
                    <h4><?= number_format($stats['total_muscles']) ?></h4>
                    <small>근육</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-link fa-2x mb-2"></i>
                    <h4><?= number_format($stats['zone_mappings']) ?></h4>
                    <small>부위 매핑</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-project-diagram fa-2x mb-2"></i>
                    <h4><?= number_format($stats['muscle_mappings']) ?></h4>
                    <small>근육 매핑</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-percentage fa-2x mb-2"></i>
                    <h4><?= $stats['zone_mapping_rate'] ?>%</h4>
                    <small>매핑률</small>
                </div>
            </div>
        </div>

        <!-- 매핑 현황 분석 -->
        <div class="row">
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>부위 매핑 현황
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>매핑된 운동</span>
                                <span><?= $stats['exercises_with_zones'] ?> / <?= $stats['total_exercises'] ?></span>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-success" style="width: <?= $stats['zone_mapping_rate'] ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $stats['zone_mapping_rate'] ?>% 완료</small>
                        </div>
                        
                        <div class="chart-container">
                            <canvas id="zoneChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>근육 매핑 현황
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>매핑된 운동</span>
                                <span><?= $stats['exercises_with_muscles'] ?> / <?= $stats['total_exercises'] ?></span>
                            </div>
                            <div class="progress progress-custom">
                                <div class="progress-bar bg-info" style="width: <?= $stats['muscle_mapping_rate'] ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $stats['muscle_mapping_rate'] ?>% 완료</small>
                        </div>
                        
                        <div class="chart-container">
                            <canvas id="muscleChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 상위 매핑 운동 -->
        <div class="row">
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>부위 매핑 상위 운동
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>순위</th>
                                        <th>운동명</th>
                                        <th>부위 수</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['top_zone_exercises'] as $index => $exercise): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>">
                                                    <?= $index + 1 ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($exercise['name_kr']) ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?= $exercise['zone_count'] ?>개</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>근육 매핑 상위 운동
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>순위</th>
                                        <th>운동명</th>
                                        <th>근육 수</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['top_muscle_exercises'] as $index => $exercise): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>">
                                                    <?= $index + 1 ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($exercise['name_kr']) ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?= $exercise['muscle_count'] ?>개</span>
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

        <!-- 부위/근육 사용 현황 -->
        <div class="row">
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>부위별 사용 현황
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="zoneUsageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-muscle me-2"></i>근육별 사용 현황
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="muscleUsageChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 매핑되지 않은 운동 -->
        <div class="row">
            <div class="col-12">
                <div class="analysis-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>매핑되지 않은 운동
                            <span class="badge bg-warning ms-2"><?= count($stats['unmapped_exercises']) ?>개</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stats['unmapped_exercises'])): ?>
                            <div class="text-center text-success">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h5>모든 운동이 매핑되었습니다!</h5>
                                <p class="text-muted">완벽한 시스템 상태입니다.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($stats['unmapped_exercises'] as $exercise): ?>
                                    <div class="col-md-3 col-sm-6 mb-2">
                                        <span class="badge bg-light text-dark p-2 w-100">
                                            <?= htmlspecialchars($exercise['name_kr']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3">
                                <a href="exercise_zone_mapping.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i>부위 매핑 추가
                                </a>
                                <a href="exercise_muscle_mapping.php" class="btn btn-info btn-sm">
                                    <i class="fas fa-plus me-1"></i>근육 매핑 추가
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 부위 매핑 차트
        const zoneCtx = document.getElementById('zoneChart').getContext('2d');
        new Chart(zoneCtx, {
            type: 'doughnut',
            data: {
                labels: ['매핑됨', '매핑 안됨'],
                datasets: [{
                    data: [<?= $stats['exercises_with_zones'] ?>, <?= $stats['total_exercises'] - $stats['exercises_with_zones'] ?>],
                    backgroundColor: ['#28a745', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 근육 매핑 차트
        const muscleCtx = document.getElementById('muscleChart').getContext('2d');
        new Chart(muscleCtx, {
            type: 'doughnut',
            data: {
                labels: ['매핑됨', '매핑 안됨'],
                datasets: [{
                    data: [<?= $stats['exercises_with_muscles'] ?>, <?= $stats['total_exercises'] - $stats['exercises_with_muscles'] ?>],
                    backgroundColor: ['#17a2b8', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 부위 사용 현황 차트
        const zoneUsageCtx = document.getElementById('zoneUsageChart').getContext('2d');
        new Chart(zoneUsageCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($zone) { return "'" . addslashes($zone['zone_name_kr']) . "'"; }, array_slice($stats['zone_usage'], 0, 10))) ?>],
                datasets: [{
                    label: '사용된 운동 수',
                    data: [<?= implode(',', array_map(function($zone) { return $zone['exercise_count']; }, array_slice($stats['zone_usage'], 0, 10))) ?>],
                    backgroundColor: '#28a745'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // 근육 사용 현황 차트
        const muscleUsageCtx = document.getElementById('muscleUsageChart').getContext('2d');
        new Chart(muscleUsageCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($muscle) { return "'" . addslashes($muscle['name_kr']) . "'"; }, array_slice($stats['muscle_usage'], 0, 10))) ?>],
                datasets: [{
                    label: '사용된 운동 수',
                    data: [<?= implode(',', array_map(function($muscle) { return $muscle['exercise_count']; }, array_slice($stats['muscle_usage'], 0, 10))) ?>],
                    backgroundColor: '#17a2b8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 통계 정보 가져오기
$stats = [];
try {
    // 운동 개수
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise");
    $stats['exercise_count'] = $stmt->fetch()['count'];
    
    // 세부부위 개수
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_part_zone");
    $stats['zone_count'] = $stmt->fetch()['count'];
    
    // 근육 개수
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_muscle");
    $stats['muscle_count'] = $stmt->fetch()['count'];
    
    // 운동-세부부위 매핑 개수
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise_zone_target");
    $stats['zone_mapping_count'] = $stmt->fetch()['count'];
    
    // 운동-근육 매핑 개수
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_exercise_muscle_target");
    $stats['muscle_mapping_count'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    $error = "통계 정보를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>운동 관리 시스템 - 관리자</title>
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
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
        </a>
        
        <h1 class="text-center mb-5">
            <i class="fas fa-dumbbell me-3"></i>운동 관리 시스템
        </h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- 통계 카드들 -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-dumbbell fa-2x mb-2"></i>
                    <h4><?= number_format($stats['exercise_count']) ?></h4>
                    <small>운동</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-map-marker-alt fa-2x mb-2"></i>
                    <h4><?= number_format($stats['zone_count']) ?></h4>
                    <small>세부부위</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-muscle fa-2x mb-2"></i>
                    <h4><?= number_format($stats['muscle_count']) ?></h4>
                    <small>근육</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-link fa-2x mb-2"></i>
                    <h4><?= number_format($stats['zone_mapping_count']) ?></h4>
                    <small>부위 매핑</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-project-diagram fa-2x mb-2"></i>
                    <h4><?= number_format($stats['muscle_mapping_count']) ?></h4>
                    <small>근육 매핑</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card text-center">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h4>시스템</h4>
                    <small>운영중</small>
                </div>
            </div>
        </div>
        
        <!-- 메뉴 카드들 -->
        <div class="row">
            <div class="col-md-4">
                <div class="menu-card text-center">
                    <div class="menu-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h5>운동 마스터 관리</h5>
                    <p class="text-muted">운동 종목 및 동의어 관리</p>
                    <a href="exercise_master.php" class="btn btn-primary">관리하기</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="menu-card text-center">
                    <div class="menu-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <h5>운동-부위 매핑</h5>
                    <p class="text-muted">운동별 타겟 부위 설정</p>
                    <a href="exercise_zone_mapping.php" class="btn btn-primary">관리하기</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="menu-card text-center">
                    <div class="menu-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h5>운동-근육 매핑</h5>
                    <p class="text-muted">운동별 타겟 근육 설정</p>
                    <a href="exercise_muscle_mapping.php" class="btn btn-primary">관리하기</a>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="menu-card text-center">
                    <div class="menu-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h5>세부부위 관리</h5>
                    <p class="text-muted">신체 부위, 근육, 세부존 통합 관리</p>
                    <a href="body_part_master.php" class="btn btn-primary">관리하기</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="menu-card text-center">
                    <div class="menu-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5>시스템 분석</h5>
                    <p class="text-muted">매핑 현황 및 통계 분석</p>
                    <a href="system_analysis.php" class="btn btn-primary">분석하기</a>
                </div>
            </div>
        </div>
        
        <!-- 시스템 정보 -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>시스템 정보</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>운동 관리 시스템 특징</h6>
                                <ul>
                                    <li>초보자가 운동명만 입력해도 자동으로 타겟 부위/근육 매핑</li>
                                    <li>가슴 상부/중부/하부 등 세밀한 부위 구분</li>
                                    <li>주/보조 근육 가중치 설정으로 정확한 운동 효과 분석</li>
                                    <li>운동별 동의어(별칭) 지원</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>사용 예시</h6>
                                <ul>
                                    <li><strong>벤치프레스</strong> → 가슴 중부(주), 삼두(보조), 어깨 전면(보조)</li>
                                    <li><strong>인클라인 덤벨 프레스</strong> → 가슴 상부(주), 삼두(보조)</li>
                                    <li><strong>라잉 트라이셉스</strong> → 삼두(주)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

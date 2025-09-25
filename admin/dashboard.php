<?php
// 인증 확인
require_once 'includes/auth_check.php';
require_once '../config/database.php';

// 로그아웃 처리
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}

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
    
    // 운동 템플릿 개수 (테이블이 존재하는 경우에만)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM m_workout_template");
        $stats['template_count'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['template_count'] = 0;
    }
    
} catch (Exception $e) {
    $error = "통계 정보를 가져오는 중 오류가 발생했습니다: " . $e->getMessage();
}
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>운동 관리 시스템 - 관리자</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* 사이드바 스크롤바 스타일링 */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        
        .main-content {
            background-color: #f8fafc;
            min-height: 100vh;
            margin-left: 250px;
            padding-left: 20px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: none;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .timeline {
            position: relative;
            padding-left: 20px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .timeline-content {
            padding-left: 10px;
        }
        
        .timeline-content h6 {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .timeline-content p {
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .timeline-content small {
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
    </div>

        <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-white mb-4">
                <i class="fas fa-dumbbell me-2"></i>
                운동 관리
            </h4>
            
                                    <nav class="nav flex-column">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                대시보드
                            </a>
                            <a class="nav-link" href="exercise_management.php">
                                <i class="fas fa-dumbbell"></i>
                                운동 관리
                            </a>
                            <a class="nav-link" href="workout_template_management.php">
                                <i class="fas fa-clipboard-list"></i>
                                운동 템플릿 관리
                            </a>
                            <a class="nav-link" href="template_assignment.php">
                                <i class="fas fa-user-plus"></i>
                                템플릿 할당
                            </a>
                            <a class="nav-link" href="schedule_management.php">
                                <i class="fas fa-calendar-alt"></i>
                                스케줄 관리
                            </a>
                            <a class="nav-link" href="user_workout_history.php">
                                <i class="fas fa-user-clock"></i>
                                개인 운동 이력
                            </a>
                                            <a class="nav-link" href="user_management.php">
                    <i class="fas fa-users"></i>
                    사용자 관리
                </a>
                <a class="nav-link" href="trainer_management.php">
                    <i class="fas fa-dumbbell"></i>
                    트레이너 관리
                </a>
                            <a class="nav-link" href="#">
                                <i class="fas fa-cog"></i>
                                설정
                            </a>
                        </nav>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
                    <!-- Top Navigation -->
                    <nav class="navbar navbar-expand-lg navbar-light">
                        <div class="container-fluid">
                            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                                <span class="navbar-toggler-icon"></span>
                            </button>
                            
                            <div class="navbar-nav ms-auto">
                                <div class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-bell me-2"></i>
                                        알림
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#">새로운 운동이 추가되었습니다.</a></li>
                                        <li><a class="dropdown-item" href="#">매핑 데이터가 업데이트되었습니다.</a></li>
                                    </ul>
                                </div>
                                <div class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-user me-2"></i>
                                        관리자
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>프로필</a></li>
                                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>설정</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="post" style="display: inline;">
                                                <button type="submit" name="logout" class="dropdown-item">
                                                    <i class="fas fa-sign-out-alt me-2"></i>로그아웃
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                        </div>
    </div>
                    </nav>
                    
                    <!-- Page Content -->
                    <div class="p-4">
                        <!-- Dashboard Content -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="mb-0">운동 관리 시스템 대시보드</h2>
                            <div class="text-muted">오늘: <span id="current-date"></span></div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">총 운동</h6>
                                            <h3 class="mb-0"><?= number_format($stats['exercise_count']) ?></h3>
                                            <small class="text-success">+3개</small>
                                        </div>
                                        <div class="icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                            <i class="fas fa-dumbbell"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">세부부위</h6>
                                            <h3 class="mb-0"><?= number_format($stats['zone_count']) ?></h3>
                                            <small class="text-info">+2개</small>
                                        </div>
                                        <div class="icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">총 근육</h6>
                                            <h3 class="mb-0"><?= number_format($stats['muscle_count']) ?></h3>
                                            <small class="text-warning">+5개</small>
                                        </div>
                                        <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                            <i class="fas fa-muscle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="stats-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">운동 템플릿</h6>
                                            <h3 class="mb-0"><?= number_format($stats['template_count']) ?></h3>
                                            <small class="text-primary">새 기능</small>
                                        </div>
                                        <div class="icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions and Recent Activities -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>빠른 액션</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="exercise_master.php" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>새 운동 추가
                                            </a>
                                            <a href="exercise_management.php" class="btn btn-success">
                                                <i class="fas fa-dumbbell me-2"></i>운동 관리
                                            </a>
                                            <a href="workout_template_management.php" class="btn btn-info">
                                                <i class="fas fa-clipboard-list me-2"></i>운동 템플릿 관리
                                            </a>
                                            <a href="template_assignment.php" class="btn btn-success">
                                                <i class="fas fa-user-plus me-2"></i>템플릿 할당
                                            </a>
                                            <a href="schedule_management.php" class="btn btn-warning">
                                                <i class="fas fa-calendar-alt me-2"></i>스케줄 관리
                                            </a>
                                            <a href="user_workout_history.php" class="btn btn-info">
                                                <i class="fas fa-user-clock me-2"></i>개인 운동 이력
                                            </a>
                                            <a href="#" class="btn btn-warning">
                                                <i class="fas fa-chart-bar me-2"></i>분석 보기
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>최근 활동</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="timeline">
                                            <div class="timeline-item">
                                                <div class="timeline-marker bg-success"></div>
                                                <div class="timeline-content">
                                                    <h6 class="mb-1">새로운 운동 추가</h6>
                                                    <p class="text-muted mb-0">라잉 트라이셉스 익스텐션이 추가되었습니다.</p>
                                                    <small class="text-muted">2분 전</small>
                                                </div>
                                            </div>
                                            <div class="timeline-item">
                                                <div class="timeline-marker bg-info"></div>
                                                <div class="timeline-content">
                                                    <h6 class="mb-1">매핑 업데이트</h6>
                                                    <p class="text-muted mb-0">벤치프레스-가슴 중부 매핑이 업데이트되었습니다.</p>
                                                    <small class="text-muted">15분 전</small>
                                                </div>
                                            </div>
                                            <div class="timeline-item">
                                                <div class="timeline-marker bg-warning"></div>
                                                <div class="timeline-content">
                                                    <h6 class="mb-1">동의어 추가</h6>
                                                    <p class="text-muted mb-0">인클라인 DB 프레스 동의어가 추가되었습니다.</p>
                                                    <small class="text-muted">1시간 전</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Overview -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>시스템 개요</h5>
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

                        <!-- Additional Stats -->
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-pie fa-3x text-primary mb-3"></i>
                                        <h4>운동 분포</h4>
                                        <p class="text-muted">가슴: 15개, 등: 12개, 하체: 8개</p>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: 75%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-link fa-3x text-success mb-3"></i>
                                        <h4>매핑 현황</h4>
                                        <p class="text-muted">완성도: <?= round(($stats['zone_mapping_count'] + $stats['muscle_mapping_count']) / ($stats['exercise_count'] * 3) * 100, 1) ?>%</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, ($stats['zone_mapping_count'] + $stats['muscle_mapping_count']) / ($stats['exercise_count'] * 3) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-warning mb-3"></i>
                                        <h4>시스템 상태</h4>
                                        <p class="text-muted">운영중</p>
                                        <div class="progress">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: 100%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Set current date
        document.getElementById('current-date').textContent = new Date().toLocaleDateString('ko-KR');
        
        // Loading spinner functions
        function showLoading() {
            document.getElementById('loading').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        // Active navigation highlighting
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage || 
                    (currentPage === '' && link.getAttribute('href') === 'dashboard.php')) {
                    link.classList.add('active');
                }
            });
        });
        
        // Card hover effects
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.1)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.05)';
            });
        });
    </script>
</body>
</html>

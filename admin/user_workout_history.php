<?php
session_start();
require_once 'includes/auth_check.php';
require_once '../config/database.php';

$pdo = getDB();

// 현재 월/년도 설정
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// 선택된 날짜
$selected_date = isset($_GET['date']) ? $_GET['date'] : null;

// 선택된 날짜의 운동자들 가져오기
$workout_users = [];
if ($selected_date) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.email, 
               ws.session_id, ws.start_time, ws.end_time,
               COUNT(we.wx_id) as exercise_count
        FROM users u
        INNER JOIN m_workout_session ws ON u.id = ws.user_id
        LEFT JOIN m_workout_exercise we ON ws.session_id = we.session_id
        WHERE DATE(ws.start_time) = ?
        GROUP BY u.id, ws.session_id
        ORDER BY ws.start_time ASC
    ");
    $stmt->execute([$selected_date]);
    $workout_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 해당 월의 운동 데이터 가져오기 (달력 표시용)
$month_start = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$month_end = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

$stmt = $pdo->prepare("
    SELECT DATE(ws.start_time) as workout_date, 
           COUNT(DISTINCT ws.user_id) as user_count,
           COUNT(ws.session_id) as session_count
    FROM m_workout_session ws
    WHERE DATE(ws.start_time) >= ? AND DATE(ws.start_time) <= ?
    GROUP BY DATE(ws.start_time)
    ORDER BY workout_date ASC
");
$stmt->execute([$month_start, $month_end]);
$workout_days = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 달력 데이터 생성
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$last_day = mktime(0, 0, 0, $current_month + 1, 0, $current_year);
$days_in_month = date('t', $first_day);
$first_day_of_week = date('w', $first_day);

// 메시지 처리
$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>개인 운동 이력 - HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exercise-card {
            border-left: 4px solid #007bff;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* 달력 스타일 */
        .week-row {
            margin-bottom: 8px;
        }
        
        .week-days {
            display: flex;
            gap: 2px;
            justify-content: center;
            padding: 5px 0;
        }
        
        .day-cell {
            width: 60px;
            height: 50px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #fff;
            position: relative;
            margin: 1px;
        }
        
        .day-cell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .day-cell.has-workout {
            background: linear-gradient(135deg, #28a745, #20c997);
            border-color: #28a745;
            color: white;
        }
        
        .day-cell.selected {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-color: #007bff;
            color: white;
            box-shadow: 0 4px 12px rgba(0,123,255,0.4);
        }
        
        .day-cell.current-month {
            opacity: 1;
        }
        
        .day-cell.other-month {
            opacity: 0.4;
        }
        
        .day-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .day-number {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .workout-indicator {
            position: absolute;
            bottom: 4px;
            font-size: 12px;
        }
        
        /* 토요일 스타일 (하늘색) */
        .day-cell.saturday {
            color: #17a2b8 !important;
            border-color: #17a2b8 !important;
        }
        
        .day-cell.saturday.has-workout {
            background: linear-gradient(135deg, #17a2b8, #20c997) !important;
            border-color: #17a2b8 !important;
            color: white !important;
        }
        
        /* 일요일 스타일 (빨간색) */
        .day-cell.sunday {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        
        .day-cell.sunday.has-workout {
            background: linear-gradient(135deg, #dc3545, #e74c3c) !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .user-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        .user-card.border-primary {
            border-color: #007bff !important;
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <!-- 헤더 -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-user-clock me-2"></i>개인 운동 이력</h2>
                        <p class="text-muted">사용자별 상세한 운동 이력을 달력으로 확인합니다</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
                    </a>
                </div>
            </div>
        </div>

        <!-- 달력 (작은 크기) -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar me-2"></i>
                                <?= $current_year ?>년 <?= $current_month ?>월
                            </h6>
                            <div>
                                <a href="?month=<?= $current_month == 1 ? 12 : $current_month - 1 ?>&year=<?= $current_month == 1 ? $current_year - 1 : $current_year ?>" 
                                   class="btn btn-outline-secondary btn-sm me-1">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="?month=<?= $current_month == 12 ? 1 : $current_month + 1 ?>&year=<?= $current_month == 12 ? $current_year + 1 : $current_year ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <!-- 요일 헤더 -->
                        <div class="week-days mb-1">
                            <?php 
                            $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
                            foreach ($weekdays as $day): 
                            ?>
                            <div class="day-cell text-center fw-bold" style="height: 25px; background: #f8f9fa; border: 1px solid #dee2e6; font-size: 11px;">
                                <?= $day ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- 달력 날짜들 -->
                        <?php 
                        $workout_days_map = [];
                        foreach ($workout_days as $day) {
                            $workout_days_map[$day['workout_date']] = $day;
                        }
                        
                        $day_count = 1;
                        $current_week = 0;
                        ?>
                        
                        <?php for ($week = 0; $week < 6; $week++): ?>
                        <div class="week-days">
                            <?php for ($day = 0; $day < 7; $day++): ?>
                                <?php 
                                $cell_day = null;
                                $is_current_month = false;
                                
                                if ($week == 0 && $day < $first_day_of_week) {
                                    // 이전 달의 마지막 날들
                                    $prev_month = $current_month == 1 ? 12 : $current_month - 1;
                                    $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
                                    $prev_month_days = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
                                    $cell_day = $prev_month_days - ($first_day_of_week - $day - 1);
                                } elseif ($day_count <= $days_in_month) {
                                    // 현재 달의 날들
                                    $cell_day = $day_count;
                                    $is_current_month = true;
                                    $day_count++;
                                } else {
                                    // 다음 달의 첫 날들
                                    $cell_day = $day_count - $days_in_month;
                                }
                                
                                $date_str = $is_current_month ? 
                                    sprintf('%04d-%02d-%02d', $current_year, $current_month, $cell_day) : 
                                    null;
                                    
                                $has_workout = $date_str && isset($workout_days_map[$date_str]);
                                $workout_data = $has_workout ? $workout_days_map[$date_str] : null;
                                ?>
                                
                                <div class="day-cell <?= $is_current_month ? 'current-month' : 'other-month' ?> <?= $has_workout ? 'has-workout' : '' ?> <?= $selected_date == $date_str ? 'selected' : '' ?> <?= $day == 0 ? 'sunday' : ($day == 6 ? 'saturday' : '') ?>"
                                     style="cursor: pointer; height: 50px; font-size: 12px;"
                                     onclick="<?= $is_current_month ? "selectDate('$date_str')" : '' ?>">
                                    <div class="day-number"><?= $cell_day ?></div>
                                    <?php if ($has_workout): ?>
                                    <div class="workout-indicator">
                                        <small style="font-size: 8px;">
                                            <i class="fas fa-dumbbell"></i>
                                            <?= $workout_data['user_count'] ?>명
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            
            <!-- 사용자 선택 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>사용자 선택</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>사용자명</th>
                                        <th>이메일</th>
                                        <th>사용자 ID</th>
                                        <th>액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // 사용자 목록 가져오기
                                    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE is_active = 1 ORDER BY username ASC');
                                    $stmt->execute();
                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr style="cursor: pointer;" onclick="selectUser(<?= $user['id'] ?>)">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user me-2"></i>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['email']): ?>
                                            <i class="fas fa-envelope me-1 text-muted"></i>
                                            <?= htmlspecialchars($user['email']) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $user['id'] ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="event.stopPropagation(); selectUser(<?= $user['id'] ?>)">
                                                <i class="fas fa-calendar-alt me-1"></i>보기
                                            </button>
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

        <!-- 선택된 날짜의 운동자 목록 -->
        <?php if ($selected_date): ?>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            <?= date('Y년 m월 d일', strtotime($selected_date)) ?> 운동한 사용자
                            <span class="badge bg-primary ms-2"><?= count($workout_users) ?>명</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($workout_users)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">이 날에는 운동한 사용자가 없습니다</h5>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>사용자명</th>
                                        <th>이메일</th>
                                        <th>운동 시작시간</th>
                                        <th>운동 종료시간</th>
                                        <th>운동 개수</th>
                                        <th>세션 ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workout_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user me-2 text-primary"></i>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($user['email']): ?>
                                            <i class="fas fa-envelope me-1 text-muted"></i>
                                            <?= htmlspecialchars($user['email']) ?>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-play-circle me-1 text-success"></i>
                                            <?= $user['start_time'] ? date('H:i', strtotime($user['start_time'])) : '-' ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-stop-circle me-1 text-danger"></i>
                                            <?= $user['end_time'] ? date('H:i', strtotime($user['end_time'])) : '-' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="fas fa-dumbbell me-1"></i>
                                                <?= $user['exercise_count'] ?>개
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $user['session_id'] ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
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
    
    // 날짜 선택
    function selectDate(date) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('date', date);
        window.location.href = currentUrl.toString();
    }
    
    // 사용자 선택
    function selectUser(userId) {
        window.location.href = `user_calendar.php?user_id=${userId}`;
    }
    </script>
</body>
</html>

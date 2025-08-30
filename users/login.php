<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$error = '';
$success = '';

// 이미 로그인된 경우 대시보드로 리다이렉트
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    // 세션 유효성 확인
    $stmt = $pdo->prepare('
        SELECT u.*, s.session_token, s.expires_at 
        FROM users u 
        JOIN sessions s ON u.id = s.user_id 
        WHERE u.id = ? AND s.session_token = ? AND s.expires_at > NOW()
    ');
    $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
    $user = $stmt->fetch();
    
    if ($user) {
        header('Location: today.php');
        exit;
    } else {
        // 유효하지 않은 세션 삭제
        session_destroy();
    }
}

// 카카오 로그인 처리
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // 카카오 액세스 토큰 받기
    $token_url = 'https://kauth.kakao.com/oauth/token';
    $token_data = [
        'grant_type' => 'authorization_code',
        'client_id' => '53df77572469e1e93d4ce5021f2d1995', // 카카오 REST API 키로 변경 필요
        'redirect_uri' => 'http://hellchangnew.freeduck.co.kr/users/login.php',
        'code' => $code
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_info = json_decode($response, true);
    
    if (isset($token_info['access_token'])) {
        // 카카오 사용자 정보 받기
        $user_url = 'https://kapi.kakao.com/v2/user/me';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $user_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token_info['access_token'],
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_info = json_decode($user_response, true);
        
        if (isset($user_info['id'])) {
            $kakao_id = $user_info['id'];
            $username = $user_info['properties']['nickname'] ?? '사용자';
            $profile_image = $user_info['properties']['profile_image'] ?? null;
            $email = $user_info['kakao_account']['email'] ?? null;
            
            // 이메일이 없는 경우 카카오 ID 기반으로 고유한 이메일 생성
            if (!$email) {
                $email = 'kakao_' . $kakao_id . '@hellchang.com';
            }
            
            // 사용자 존재 여부 확인
            $stmt = $pdo->prepare('SELECT * FROM users WHERE kakao_id = ?');
            $stmt->execute([$kakao_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // 새 사용자 등록
                $stmt = $pdo->prepare('INSERT INTO users (kakao_id, username, email, profile_image) VALUES (?, ?, ?, ?)');
                $stmt->execute([$kakao_id, $username, $email, $profile_image]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
                // 프로필 정보 업데이트 (이메일은 기존 것 유지)
                $stmt = $pdo->prepare('UPDATE users SET username = ?, profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$username, $profile_image, $user_id]);
            }
            
            // 세션 생성
            $session_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, $session_token, $expires_at]);
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['session_token'] = $session_token;
            $_SESSION['username'] = $username;
            
            $success = '로그인되었습니다.';
            header('Location: today.php');
            exit;
        } else {
            $error = '카카오 사용자 정보를 가져올 수 없습니다.';
        }
    } else {
        $error = '카카오 인증에 실패했습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 헬스 루틴</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .kakao-btn {
            background: #FEE500;
            border: none;
            color: #000;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .kakao-btn:hover {
            background: #FDD835;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(254, 229, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 class="mb-4">헬스 루틴</h2>
            <p class="text-muted mb-4">나만의 운동 루틴을 만들어보세요</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div class="mb-4">
                <a href="https://kauth.kakao.com/oauth/authorize?client_id=53df77572469e1e93d4ce5021f2d1995&redirect_uri=<?= urlencode('http://hellchangnew.freeduck.co.kr/users/login.php') ?>&response_type=code" 
                   class="kakao-btn">
                    <img src="https://developers.kakao.com/assets/img/about/logos/kakaolink/kakaolink_btn_small.png" 
                         alt="카카오 로그인" width="20" height="20">
                    카카오로 시작하기
                </a>
            </div>
            
            <div class="text-muted small">
                <p>카카오 계정으로 간편하게 로그인하세요</p>
                <p>개인정보는 안전하게 보호됩니다</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
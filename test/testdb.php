<?php
// 데이터베이스 설정 파일 포함
require_once '../config/database.php';

// HTML 시작
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>데이터베이스 연결 테스트</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .test-button:hover {
            background-color: #0056b3;
        }
        .details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-family: monospace;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>데이터베이스 연결 테스트</h1>
        
        <?php
        // 데이터베이스 연결 테스트
        if (isset($_POST['test_db'])) {
            echo '<div class="info">데이터베이스 연결을 테스트하고 있습니다...</div>';
            
            try {
                // 데이터베이스 연결 시도
                $pdo = getDB();
                
                // 연결 성공 시 추가 정보 확인
                $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                $connectionInfo = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
                
                echo '<div class="success">';
                echo '<h3>✅ 데이터베이스 연결 성공!</h3>';
                echo '<p>데이터베이스에 성공적으로 연결되었습니다.</p>';
                echo '</div>';
                
                echo '<div class="details">';
                echo '<h4>연결 정보:</h4>';
                echo '<p><strong>호스트:</strong> ' . DB_HOST . '</p>';
                echo '<p><strong>데이터베이스:</strong> ' . DB_NAME . '</p>';
                echo '<p><strong>사용자:</strong> ' . DB_USER . '</p>';
                echo '<p><strong>서버 버전:</strong> ' . $serverInfo . '</p>';
                echo '<p><strong>연결 상태:</strong> ' . $connectionInfo . '</p>';
                echo '</div>';
                
                // 간단한 쿼리 테스트
                try {
                    $stmt = $pdo->query('SELECT 1 as test');
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        echo '<div class="success">';
                        echo '<h4>✅ 쿼리 테스트 성공</h4>';
                        echo '<p>기본 SQL 쿼리가 정상적으로 실행됩니다.</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">';
                    echo '<h4>❌ 쿼리 테스트 실패</h4>';
                    echo '<p>오류: ' . $e->getMessage() . '</p>';
                    echo '</div>';
                }
                
            } catch (PDOException $e) {
                echo '<div class="error">';
                echo '<h3>❌ 데이터베이스 연결 실패</h3>';
                echo '<p>데이터베이스에 연결할 수 없습니다.</p>';
                echo '</div>';
                
                echo '<div class="details">';
                echo '<h4>오류 정보:</h4>';
                echo '<p><strong>오류 코드:</strong> ' . $e->getCode() . '</p>';
                echo '<p><strong>오류 메시지:</strong> ' . $e->getMessage() . '</p>';
                echo '<h4>연결 설정:</h4>';
                echo '<p><strong>호스트:</strong> ' . DB_HOST . '</p>';
                echo '<p><strong>데이터베이스:</strong> ' . DB_NAME . '</p>';
                echo '<p><strong>사용자:</strong> ' . DB_USER . '</p>';
                echo '</div>';
                
                echo '<div class="info">';
                echo '<h4>문제 해결 방법:</h4>';
                echo '<ul>';
                echo '<li>MySQL 서버가 실행 중인지 확인하세요</li>';
                echo '<li>데이터베이스 이름이 올바른지 확인하세요</li>';
                echo '<li>사용자명과 비밀번호가 올바른지 확인하세요</li>';
                echo '<li>호스트 주소가 올바른지 확인하세요</li>';
                echo '<li>방화벽 설정을 확인하세요</li>';
                echo '</ul>';
                echo '</div>';
            }
        }
        ?>
        
        <form method="post" style="text-align: center; margin-top: 30px;">
            <button type="submit" name="test_db" class="test-button">데이터베이스 연결 테스트</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <h4>테스트 방법:</h4>
            <p>위의 "데이터베이스 연결 테스트" 버튼을 클릭하면 현재 설정된 데이터베이스에 연결을 시도합니다.</p>
            <p>연결이 성공하면 연결 정보와 서버 정보를 표시하고, 실패하면 오류 메시지와 문제 해결 방법을 제시합니다.</p>
        </div>
        
        <div class="back-link">
            <a href="../index.php">← 메인 페이지로 돌아가기</a>
        </div>
    </div>
</body>
</html>

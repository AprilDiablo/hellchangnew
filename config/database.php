<?php
// DB 접속 정보 (실제 운영시 환경변수 등으로 분리 권장)
define('DB_HOST', 'localhost');
define('DB_NAME', 'hellchang');
define('DB_USER', 'freeduck');
define('DB_PASS', 'Jesusu9178!@');

function getDB() {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die('DB 연결 실패: ' . $e->getMessage());
    }
} 
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'auth_check.php';

// 로그인 확인
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>HellChang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fc;
            min-height: 100vh;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding-bottom: 60px;
        }
        
        .container { 
            padding-top: 0.5rem; 
            padding-bottom: 0.5rem; 
        }
        
        .card { 
            border: none; 
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background: #ffffff; 
            transition: all 0.15s ease-in-out;
        }
        
        .card:hover {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25);
        }
        
        .card-header { 
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); 
            color: white; 
            border-radius: 0.35rem 0.35rem 0 0 !important; 
            border: none; 
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        
        .btn-custom { 
            border-radius: 0.35rem; 
            padding: 0.375rem 0.75rem; 
            font-weight: 600;
            color: white;
            background: #4e73df;
            border: none;
            transition: all 0.15s ease-in-out;
        }
        
        .btn-custom:hover {
            background: #2e59d9;
            transform: translateY(-1px);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        /* 하단 메뉴 스타일 */
        .bottom-nav { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
            background: #ffffff; 
            border-top: 1px solid #e3e6f0; 
            box-shadow: 0 -0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1000; 
            padding: 0.5rem 0; 
        }
        
        .bottom-nav .nav-link { 
            color: #858796; 
            text-decoration: none; 
            text-align: center; 
            padding: 0.5rem; 
            border-radius: 0.35rem; 
            transition: all 0.15s ease-in-out; 
        }
        
        .bottom-nav .nav-link:hover, .bottom-nav .nav-link.active { 
            color: #4e73df; 
            background: rgba(78, 115, 223, 0.1); 
        }
        
        .bottom-nav .nav-link.active { 
            color: #4e73df; 
            font-weight: 700; 
            background: rgba(78, 115, 223, 0.15);
        }
        
        .bottom-nav .nav-link i { 
            font-size: 1.1rem; 
            display: block; 
            margin-bottom: 0.25rem; 
        }
        
        .bottom-nav .nav-link span { 
            font-size: 0.8rem; 
            display: block; 
            font-weight: 600;
        }
        
        .date-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .date-display {
            text-align: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: #5a5c69;
        }
        
        .exercise-row {
            padding: 0.75rem 0;
            margin: 0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .exercise-row:last-child {
            border-bottom: none;
        }
        
        .exercise-name {
            font-weight: 600;
            color: #5a5c69;
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">

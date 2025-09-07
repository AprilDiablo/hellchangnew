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
            padding-bottom: 100px;
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
        
        .card-header h5 {
            color: white !important;
        }
        
        .card-header .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .card-header a {
            transition: all 0.2s ease;
        }
        
        .card-header a:hover {
            transform: scale(1.05);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        .exercise-name a:hover {
            color: #007bff !important;
            text-decoration: underline !important;
        }
        
        .exercise-name a:hover strong {
            color: #007bff !important;
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
            padding: 0.3rem 0; 
        }
        
        .bottom-nav .nav-link { 
            color: #858796; 
            text-decoration: none; 
            text-align: center; 
            padding: 0.8rem 0.1rem; 
            border-radius: 0.35rem; 
            transition: all 0.15s ease-in-out; 
            margin: 0 0.05rem;
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
            font-size: 1.3rem; 
            display: block; 
            margin-bottom: 0.2rem; 
        }
        
        .bottom-nav .nav-link span { 
            font-size: 0.75rem; 
            display: block; 
            font-weight: 600;
        }
        
        .bottom-nav .row {
            margin: 0;
        }
        
        .bottom-nav .col {
            padding: 0;
        }
        
        .date-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
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
        
        /* 근육 분석 스타일 */
        .muscle-summary-section {
            border-top: 2px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 1rem;
        }
        
        .muscle-analysis-section {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 1rem;
        }
        
        .part-summary-item {
            padding: 1rem;
            border-radius: 0.5rem;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .part-summary-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .toggle-icon {
            transition: transform 0.3s ease;
            color: #6c757d;
        }
        
        .part-summary-item:hover .toggle-icon {
            color: #495057;
        }
        
        .part-details {
            border-top: 1px solid #e9ecef;
            padding-top: 0.5rem;
        }
        
        .muscle-analysis {
            /* 스크롤 제거 - 모든 항목을 나열 */
        }
        
        .muscle-item {
            padding: 0.5rem;
            border-radius: 0.25rem;
            background: #f8f9fa;
            border-left: 3px solid #4e73df;
        }
        
        .muscle-item:hover {
            background: #e9ecef;
        }
        
        .progress {
            background-color: #e9ecef;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #4e73df 0%, #224abe 100%);
            transition: width 0.6s ease;
        }
        
        .exercise-row {
            transition: all 0.2s ease;
        }
        
        .exercise-row:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .date-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .date-display {
            flex: 1;
            text-align: center;
            margin: 0 1rem;
        }
        
        .date-display input {
            max-width: 200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">

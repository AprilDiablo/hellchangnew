<?php
session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// 관리자 권한 확인
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: login.php');
    exit;
}
$pdo = getDB();

// 페이지 제목 설정
$pageTitle = '운동 템플릿 관리';
$pageSubtitle = '운동 세트 템플릿을 생성하고 관리하세요';

// 템플릿 목록 가져오기
$stmt = $pdo->prepare('
    SELECT 
        t.template_id,
        t.template_name,
        t.description,
        t.created_at,
        a.name as created_by_name,
        COUNT(te.id) as exercise_count
    FROM m_workout_template t
    LEFT JOIN admins a ON t.created_by = a.id
    LEFT JOIN m_workout_template_exercise te ON t.template_id = te.template_id
    GROUP BY t.template_id, t.template_name, t.description, t.created_at, a.name
    ORDER BY t.created_at DESC
');
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// POST 요청 처리
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_template') {
            $template_name = trim($_POST['template_name']);
            $description = trim($_POST['description']);
            
            if (!empty($template_name)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO m_workout_template (template_name, description, created_by) VALUES (?, ?, ?)");
                    $stmt->execute([$template_name, $description, $admin['id']]);
                    
                    $template_id = $pdo->lastInsertId();
                    $message = "템플릿이 성공적으로 생성되었습니다.";
                    
                    // 새 템플릿으로 리다이렉트
                    header("Location: workout_template_edit.php?id={$template_id}");
                    exit;
                } catch (Exception $e) {
                    $error = "템플릿 생성 중 오류가 발생했습니다: " . $e->getMessage();
                }
            } else {
                $error = "템플릿 이름을 입력해주세요.";
            }
        }
        
        elseif ($_POST['action'] === 'delete_template') {
            $template_id = (int)$_POST['template_id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM m_workout_template WHERE template_id = ?");
                $stmt->execute([$template_id]);
                $message = "템플릿이 삭제되었습니다.";
            } catch (Exception $e) {
                $error = "템플릿 삭제 중 오류가 발생했습니다: " . $e->getMessage();
            }
        }
    }
}

// HTML 헤더
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>운동 템플릿 관리 - 관리자</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .template-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .template-card .card-body {
            padding: 1.5rem;
        }
        .template-card .card-header {
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
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>대시보드로 돌아가기
            </a>
            <h1 class="mb-0">
                <i class="fas fa-clipboard-list me-3"></i>운동 템플릿 관리
            </h1>
        </div>

        <!-- 메시지 표시 -->
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 새 템플릿 추가 버튼 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addTemplateForm">
                <i class="fas fa-plus me-2"></i>새 템플릿 추가
            </button>
        </div>

        <!-- 새 템플릿 추가 폼 (숨김) -->
        <div class="collapse" id="addTemplateForm">
            <div class="template-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-plus me-2"></i>새 운동 템플릿 생성
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="create_template">
                        
                        <div class="col-md-6">
                            <label for="template_name" class="form-label">템플릿 이름 *</label>
                            <input type="text" class="form-control" id="template_name" name="template_name" 
                                   placeholder="예: 어깨운동-1, 등운동-하부위주" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="description" class="form-label">설명</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   placeholder="템플릿에 대한 간단한 설명...">
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>템플릿 생성
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-toggle="collapse" data-bs-target="#addTemplateForm">
                                취소
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 템플릿 목록 -->
        <?php if (empty($templates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">생성된 템플릿이 없습니다</h5>
                <p class="text-muted">새 템플릿을 생성하여 운동 세트를 만들어보세요.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($templates as $template): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="template-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-dumbbell text-primary me-2"></i>
                                <?= htmlspecialchars($template['template_name']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($template['description']): ?>
                            <p class="text-muted mb-3">
                                <?= htmlspecialchars($template['description']) ?>
                            </p>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-list me-1"></i> <?= $template['exercise_count'] ?>개 운동
                                </small>
                                <small class="text-muted">
                                    <?= date('Y-m-d', strtotime($template['created_at'])) ?>
                                </small>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="workout_template_edit.php?id=<?= $template['template_id'] ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i> 편집
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="deleteTemplate(<?= $template['template_id'] ?>, '<?= htmlspecialchars($template['template_name']) ?>')">
                                    <i class="fas fa-trash me-1"></i> 삭제
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<!-- 삭제 확인 모달 -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning"></i> 템플릿 삭제 확인
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>정말로 <strong id="deleteTemplateName"></strong> 템플릿을 삭제하시겠습니까?</p>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle"></i> 
                    이 작업은 되돌릴 수 없으며, 템플릿 내의 모든 운동 정보가 삭제됩니다.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete_template">
                    <input type="hidden" name="template_id" id="deleteTemplateId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> 삭제
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteTemplate(templateId, templateName) {
    document.getElementById('deleteTemplateId').value = templateId;
    document.getElementById('deleteTemplateName').textContent = templateName;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
}
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

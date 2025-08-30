<?php
// 데이터베이스 연결
require_once '../config/database.php';

$pdo = getDB();
$tables = [];
$relationships = [];

try {
    // 테이블 목록 가져오기
    $stmt = $pdo->query("SHOW TABLES");
    $tableList = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 각 테이블의 구조 분석
    foreach ($tableList as $tableName) {
        // 테이블 정보
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 테이블 코멘트
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name = '$tableName'");
        $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $comment = $tableInfo['Comment'] ?? '';
        
        $tables[$tableName] = [
            'columns' => $columns,
            'comment' => $comment
        ];
        
        // 외래키 관계 찾기
        $stmt = $pdo->query("
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$tableName' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($foreignKeys as $fk) {
            $relationships[] = [
                'from_table' => $tableName,
                'from_column' => $fk['COLUMN_NAME'],
                'to_table' => $fk['REFERENCED_TABLE_NAME'],
                'to_column' => $fk['REFERENCED_COLUMN_NAME']
            ];
        }
    }
    
} catch (Exception $e) {
    $error = "데이터베이스 분석 중 오류가 발생했습니다: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>데이터베이스 ERD - Mermaid.js</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Mermaid.js -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10.6.1/dist/mermaid.min.js"></script>
    
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        .table-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .column-info {
            font-size: 0.9rem;
        }
        
        .primary-key {
            background: #d4edda;
            color: #155724;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .foreign-key {
            background: #d1ecf1;
            color: #0c5460;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .erd-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .erd-diagram {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
        }
        
        .table-box {
            background: #f0f2f5;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            width: 250px;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .table-header {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .table-columns {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .column {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .column-name {
            font-weight: bold;
        }
        
        .column-type {
            font-style: italic;
            color: #6c757d;
        }
        
        .key-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .pk {
            background-color: #d4edda;
            color: #155724;
        }
        
        .fk {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .relationship-lines {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none; /* 클릭 이벤트 방지 */
        }
        
        .relationship-line {
            stroke: #999;
            stroke-width: 2;
            fill: none;
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
        
        .table-stats {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-database me-3"></i>데이터베이스 ERD</h1>
                    <p class="mb-0">Mermaid.js를 이용한 테이블 구조 시각화</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../admin/dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>대시보드로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- 통계 정보 -->
        <div class="table-stats">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($tables) ?></div>
                        <div class="stat-label">총 테이블</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= array_sum(array_map(function($table) { return count($table['columns']); }, $tables)) ?></div>
                        <div class="stat-label">총 컬럼</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($relationships) ?></div>
                        <div class="stat-label">관계 수</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?= count(array_filter($tables, function($table) { return !empty($table['comment']); })) ?></div>
                        <div class="stat-label">코멘트 있음</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ERD 다이어그램 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>ERD 다이어그램</h5>
            </div>
            <div class="card-body">
                <div class="erd-container">
                    <div class="erd-diagram">
                        <?php foreach ($tables as $tableName => $tableInfo): ?>
                            <?php $safeTableName = preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName); ?>
                            <div class="table-box" id="<?= $safeTableName ?>">
                                <div class="table-header"><?= htmlspecialchars($tableName) ?></div>
                                <div class="table-columns">
                                    <?php foreach ($tableInfo['columns'] as $column): ?>
                                        <?php
                                        $columnName = $column['Field'];
                                        $columnType = $column['Type'];
                                        $columnKey = $column['Key'];
                                        $isPrimary = $columnKey === 'PRI';
                                        $isForeign = $columnKey === 'MUL';
                                        ?>
                                        <div class="column <?= $isPrimary ? 'primary-key' : ($isForeign ? 'foreign-key' : '') ?>">
                                            <span class="column-name"><?= htmlspecialchars($columnName) ?></span>
                                            <span class="column-type"><?= htmlspecialchars($columnType) ?></span>
                                            <?php if ($isPrimary): ?>
                                                <span class="key-badge pk">PK</span>
                                            <?php elseif ($isForeign): ?>
                                                <span class="key-badge fk">FK</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- 관계선을 위한 SVG -->
                        <svg class="relationship-lines" width="100%" height="100%">
                            <?php foreach ($relationships as $rel): ?>
                                <?php 
                                $safeFromTable = preg_replace('/[^a-zA-Z0-9_]/', '_', $rel['from_table']);
                                $safeToTable = preg_replace('/[^a-zA-Z0-9_]/', '_', $rel['to_table']);
                                ?>
                                <line class="relationship-line" 
                                      data-from="<?= $safeFromTable ?>" 
                                      data-to="<?= $safeToTable ?>"
                                      x1="0" y1="0" x2="0" y2="0">
                                </line>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- 테이블 상세 정보 -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i>테이블 상세 정보</h5>
            </div>
            <div class="card-body">
                <?php foreach ($tables as $tableName => $tableInfo): ?>
                    <div class="table-info">
                        <h6 class="mb-3">
                            <i class="fas fa-table me-2"></i>
                            <?= htmlspecialchars($tableName) ?>
                            <?php if (!empty($tableInfo['comment'])): ?>
                                <small class="text-muted">(<?= htmlspecialchars($tableInfo['comment']) ?>)</small>
                            <?php endif; ?>
                        </h6>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>컬럼명</th>
                                        <th>타입</th>
                                        <th>NULL</th>
                                        <th>키</th>
                                        <th>기본값</th>
                                        <th>추가</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableInfo['columns'] as $column): ?>
                                        <tr>
                                            <td>
                                                <?php if ($column['Key'] === 'PRI'): ?>
                                                    <span class="primary-key"><?= htmlspecialchars($column['Field']) ?></span>
                                                <?php elseif ($column['Key'] === 'MUL'): ?>
                                                    <span class="foreign-key"><?= htmlspecialchars($column['Field']) ?></span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($column['Field']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($column['Type']) ?></code></td>
                                            <td><?= $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL' ?></td>
                                            <td>
                                                <?php if ($column['Key'] === 'PRI'): ?>
                                                    <span class="badge bg-success">PK</span>
                                                <?php elseif ($column['Key'] === 'MUL'): ?>
                                                    <span class="badge bg-info">FK</span>
                                                <?php elseif ($column['Key'] === 'UNI'): ?>
                                                    <span class="badge bg-warning">UNIQUE</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $column['Default'] ?: '-' ?></td>
                                            <td><?= $column['Extra'] ?: '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 관계 정보 -->
        <?php if (!empty($relationships)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>테이블 관계</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>테이블</th>
                                <th>컬럼</th>
                                <th>참조 테이블</th>
                                <th>참조 컬럼</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relationships as $rel): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rel['from_table']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($rel['from_column']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($rel['to_table']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($rel['to_column']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ERD 관계선 위치 조정 -->
    <script>
        // 페이지 로드 후 관계선 위치 조정
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(adjustRelationshipLines, 500);
        });
        
        // 윈도우 리사이즈 시 관계선 위치 재조정
        window.addEventListener('resize', function() {
            setTimeout(adjustRelationshipLines, 100);
        });
        
        // 관계선 위치 조정 함수
        function adjustRelationshipLines() {
            const lines = document.querySelectorAll('.relationship-line');
            const container = document.querySelector('.erd-container');
            
            if (!container) return;
            
            lines.forEach(line => {
                const fromId = line.getAttribute('data-from');
                const toId = line.getAttribute('data-from');
                const fromBox = document.getElementById(fromId);
                const toBox = document.getElementById(toId);
                
                if (fromBox && toBox) {
                    const fromRect = fromBox.getBoundingClientRect();
                    const toRect = toBox.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();
                    
                    // 컨테이너 기준으로 상대 위치 계산
                    const x1 = fromRect.right - containerRect.left;
                    const y1 = fromRect.top + fromRect.height / 2 - containerRect.top;
                    const x2 = toRect.left - containerRect.left;
                    const y2 = toRect.top + toRect.height / 2 - containerRect.top;
                    
                    line.setAttribute('x1', x1);
                    line.setAttribute('y1', y1);
                    line.setAttribute('x2', x2);
                    line.setAttribute('y2', y2);
                }
            });
        }
    </script>
</body>
</html>

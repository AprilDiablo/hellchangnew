    </div> <!-- container 닫기 -->

    <!-- 하단 메뉴 -->
    <nav class="bottom-nav">
        <div class="container">
            <div class="row">
                <div class="col">
                    <a href="today.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'today.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">➕</span>
                        <span>입력</span>
                    </a>
                </div>
                <div class="col">
                    <a href="my_workouts.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_workouts.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">📅</span>
                        <span>오늘</span>
                    </a>
                </div>
                <div class="col">
                    <a href="history.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'history.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">📋</span>
                        <span>전체</span>
                    </a>
                </div>
                <div class="col">
                    <a href="trainer_request.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'trainer_request.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">👥</span>
                        <span>친구</span>
                    </a>
                </div>
                <div class="col">
                    <a href="stats.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'stats.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">📊</span>
                        <span>통계</span>
                    </a>
                </div>
                <div class="col">
                    <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                        <span style="font-size: 1.2em;">⚙️</span>
                        <span>설정</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>

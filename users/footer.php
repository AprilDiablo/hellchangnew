    </div> <!-- container 닫기 -->

    <!-- 하단 메뉴 -->
    <nav class="bottom-nav">
        <div class="container">
            <div class="row">
                <div class="col-2">
                    <a href="today.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'today.php' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span>운동 입력</span>
                    </a>
                </div>
                <div class="col-2">
                    <a href="my_workouts.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'my_workouts.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>오늘 기록</span>
                    </a>
                </div>
                <div class="col-2">
                    <a href="history.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'history.php' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i>
                        <span>전체 기록</span>
                    </a>
                </div>
                <div class="col-3">
                    <a href="trainer_request.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'trainer_request.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-tie"></i>
                        <span>트레이너</span>
                    </a>
                </div>
                <div class="col-3">
                    <a href="profile.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-cog"></i>
                        <span>프로필</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

-- 프리/포스트 루틴 기록 테이블 생성
CREATE TABLE m_routine_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,  -- users.id와 동일한 타입
    session_id BIGINT NOT NULL,  -- m_workout_session.session_id와 동일한 타입
    routine_type ENUM('pre', 'post') NOT NULL,
    routine_name VARCHAR(100) NOT NULL,  -- 사용자가 변경한 이름
    routine_content TEXT NOT NULL,       -- 실제 수행 내용
    is_completed BOOLEAN DEFAULT FALSE,
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    duration INT DEFAULT 0,              -- 초 단위
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES m_workout_session(session_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='프리/포스트 루틴 기록';

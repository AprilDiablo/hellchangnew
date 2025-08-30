-- 트레이너 시스템 관련 테이블들

-- 1. 트레이너-회원 관계 테이블
CREATE TABLE IF NOT EXISTS trainer_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'terminated') DEFAULT 'pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approval_date DATETIME NULL,
    termination_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (trainer_id, member_id)
);

-- 2. 트레이너 스케줄 테이블
CREATE TABLE IF NOT EXISTS trainer_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    activity_type ENUM('workout', 'consultation', 'assessment', 'other') DEFAULT 'workout',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. 트레이너 코멘트 테이블
CREATE TABLE IF NOT EXISTS trainer_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    workout_session_id INT NULL,
    comment_type ENUM('general', 'workout_feedback', 'progress_note', 'goal_setting') DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workout_session_id) REFERENCES m_workout_session(session_id) ON DELETE SET NULL
);

-- 4. 트레이너 평가 테이블
CREATE TABLE IF NOT EXISTS trainer_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    category ENUM('strength', 'endurance', 'flexibility', 'body_composition', 'overall') DEFAULT 'overall',
    score DECIMAL(3,1) CHECK (score >= 0 AND score <= 10),
    notes TEXT,
    next_goal TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. 트레이너 권한 설정 테이블
CREATE TABLE IF NOT EXISTS trainer_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    can_view_workouts BOOLEAN DEFAULT TRUE,
    can_view_body_data BOOLEAN DEFAULT FALSE,
    can_create_schedules BOOLEAN DEFAULT TRUE,
    can_add_comments BOOLEAN DEFAULT TRUE,
    can_assess BOOLEAN DEFAULT TRUE,
    can_edit_goals BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (trainer_id, member_id)
);

-- 인덱스 생성
CREATE INDEX idx_trainer_relationships_trainer ON trainer_relationships(trainer_id);
CREATE INDEX idx_trainer_relationships_member ON trainer_relationships(member_id);
CREATE INDEX idx_trainer_relationships_status ON trainer_relationships(status);
CREATE INDEX idx_trainer_schedules_date ON trainer_schedules(schedule_date);
CREATE INDEX idx_trainer_schedules_trainer_member ON trainer_schedules(trainer_id, member_id);
CREATE INDEX idx_trainer_comments_trainer_member ON trainer_comments(trainer_id, member_id);
CREATE INDEX idx_trainer_assessments_trainer_member ON trainer_assessments(trainer_id, member_id);

-- 샘플 데이터 (테스트용)
INSERT IGNORE INTO trainer_relationships (trainer_id, member_id, status, request_date, approval_date) VALUES
(1, 2, 'approved', NOW(), NOW()),
(1, 3, 'pending', NOW(), NULL),
(2, 1, 'approved', NOW(), NOW());

-- 기본 권한 설정
INSERT IGNORE INTO trainer_permissions (trainer_id, member_id) VALUES
(1, 2),
(1, 3),
(2, 1);

-- 운동 템플릿 할당 테이블 생성
CREATE TABLE IF NOT EXISTS `m_template_assignment` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL COMMENT '할당된 템플릿 ID',
  `user_id` int(11) NOT NULL COMMENT '할당받은 사용자 ID',
  `assigned_by` int(11) NOT NULL COMMENT '할당한 관리자 ID',
  `workout_date` date NOT NULL COMMENT '운동 예정일',
  `status` enum('assigned','completed','cancelled') DEFAULT 'assigned' COMMENT '할당 상태',
  `note` text DEFAULT NULL COMMENT '관리자 메모',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_workout_date` (`workout_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_assignment_template` FOREIGN KEY (`template_id`) REFERENCES `m_workout_template` (`template_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_admin` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 템플릿 할당';

-- 할당된 운동 세션 연결 테이블
CREATE TABLE IF NOT EXISTS `m_template_workout_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL COMMENT '할당 ID',
  `session_id` bigint(20) NOT NULL COMMENT '생성된 운동 세션 ID',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assignment_id` (`assignment_id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_tws_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `m_template_assignment` (`assignment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tws_session` FOREIGN KEY (`session_id`) REFERENCES `m_workout_session` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='할당된 템플릿의 운동 세션 연결';

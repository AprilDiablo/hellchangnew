-- 프리/엔드루틴 테이블 생성
CREATE TABLE IF NOT EXISTS `m_routine_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) NOT NULL,
  `routine_type` enum('pre','post') NOT NULL COMMENT 'pre=프리루틴, post=엔드루틴',
  `content` text NOT NULL COMMENT '루틴 내용',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session_type` (`session_id`, `routine_type`),
  CONSTRAINT `fk_routine_session` FOREIGN KEY (`session_id`) REFERENCES `m_workout_session` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='프리/엔드루틴 기록';

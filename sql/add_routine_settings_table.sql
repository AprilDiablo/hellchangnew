-- 프리/엔드루틴 설정 테이블 생성
CREATE TABLE IF NOT EXISTS `m_routine_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pre_routine` text DEFAULT NULL COMMENT '프리루틴 내용',
  `post_routine` text DEFAULT NULL COMMENT '엔드루틴 내용',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_routine` (`user_id`),
  CONSTRAINT `fk_routine_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자별 프리/엔드루틴 설정';

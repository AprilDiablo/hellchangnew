-- 임시 운동 마스터 테이블 생성
CREATE TABLE IF NOT EXISTS `m_temp_exercise` (
  `temp_ex_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exercise_name` varchar(160) NOT NULL COMMENT '사용자가 입력한 운동명',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_ex_id` int(11) DEFAULT NULL COMMENT '승인 시 연결될 정식 운동 ID',
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`temp_ex_id`),
  KEY `idx_temp_user` (`user_id`),
  KEY `idx_temp_status` (`status`),
  CONSTRAINT `fk_temp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='임시 운동 마스터';

-- m_workout_exercise 테이블에 컬럼 추가
ALTER TABLE `m_workout_exercise` 
ADD COLUMN `temp_ex_id` int(11) DEFAULT NULL COMMENT '임시 운동 ID',
ADD COLUMN `is_temp` tinyint(1) DEFAULT 0 COMMENT '임시 운동 여부',
ADD CONSTRAINT `fk_wx_temp` FOREIGN KEY (`temp_ex_id`) REFERENCES `m_temp_exercise` (`temp_ex_id`) ON DELETE CASCADE;

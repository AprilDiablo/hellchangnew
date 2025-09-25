-- 운동 세트 템플릿 테이블 생성
CREATE TABLE IF NOT EXISTS `m_workout_template` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL COMMENT '템플릿 이름 (예: 어깨운동-1)',
  `description` text DEFAULT NULL COMMENT '템플릿 설명',
  `created_by` int(11) NOT NULL COMMENT '생성한 관리자 ID',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`template_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_template_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 세트 템플릿';

-- 템플릿 내 운동 목록 테이블
CREATE TABLE IF NOT EXISTS `m_workout_template_exercise` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `ex_id` int(11) DEFAULT NULL COMMENT '운동 ID (정식 운동)',
  `temp_ex_id` int(11) DEFAULT NULL COMMENT '임시 운동 ID',
  `exercise_name` varchar(160) NOT NULL COMMENT '운동명',
  `order_no` int(11) NOT NULL DEFAULT 1 COMMENT '순서',
  `weight` decimal(6,2) DEFAULT NULL COMMENT '무게(kg)',
  `reps` int(11) DEFAULT NULL COMMENT '반복 횟수',
  `sets` int(11) DEFAULT NULL COMMENT '세트 수',
  `note` varchar(255) DEFAULT NULL COMMENT '메모',
  `is_warmup` tinyint(1) DEFAULT 0 COMMENT '웜업 여부',
  PRIMARY KEY (`id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_ex_id` (`ex_id`),
  KEY `idx_temp_ex_id` (`temp_ex_id`),
  CONSTRAINT `fk_template_ex_template` FOREIGN KEY (`template_id`) REFERENCES `m_workout_template` (`template_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_template_ex_exercise` FOREIGN KEY (`ex_id`) REFERENCES `m_exercise` (`ex_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_template_ex_temp` FOREIGN KEY (`temp_ex_id`) REFERENCES `m_temp_exercise` (`temp_ex_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='템플릿 내 운동 목록';

-- --------------------------------------------------------
-- 호스트:                          1.234.53.91
-- 서버 버전:                        10.11.11-MariaDB - MariaDB Server
-- 서버 OS:                        Linux
-- HeidiSQL 버전:                  12.10.0.7000
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- 테이블 hellchang.admins 구조 내보내기
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `username` (`username`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.admins:~1 rows (대략적) 내보내기
INSERT IGNORE INTO `admins` (`id`, `username`, `password`, `name`, `role`, `created_at`, `updated_at`) VALUES
	(1, 'admin', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', '관리자', 'super_admin', '2025-07-16 16:59:56', '2025-07-16 16:59:56');

-- 테이블 hellchang.exercise_requests 구조 내보내기
CREATE TABLE IF NOT EXISTS `exercise_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exercise_name` varchar(160) NOT NULL COMMENT '요청된 운동명',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '처리상태',
  `admin_note` text DEFAULT NULL COMMENT '관리자 메모',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_exercise_name` (`exercise_name`),
  CONSTRAINT `fk_exercise_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 등록 요청';

-- 테이블 데이터 hellchang.exercise_requests:~4 rows (대략적) 내보내기
INSERT IGNORE INTO `exercise_requests` (`id`, `user_id`, `exercise_name`, `status`, `admin_note`, `created_at`, `updated_at`) VALUES
	(1, 6, '룧려ㅗ', 'pending', NULL, '2025-08-28 02:00:06', '2025-08-28 02:00:06'),
	(2, 6, 'dagfd', 'pending', NULL, '2025-08-28 02:03:46', '2025-08-28 02:03:46'),
	(3, 6, 'dagfdaaa', 'pending', NULL, '2025-08-28 02:06:01', '2025-08-28 02:06:01'),
	(4, 6, '벤지프레스', 'pending', NULL, '2025-08-28 07:38:16', '2025-08-28 07:38:16');

-- 테이블 hellchang.m_angle 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_angle` (
  `angle_code` varchar(20) NOT NULL COMMENT '각도 코드',
  `name_kr` varchar(50) NOT NULL COMMENT '각도명 (한글)',
  `name_en` varchar(50) NOT NULL COMMENT '각도명 (영문)',
  PRIMARY KEY (`angle_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='각도 마스터';

-- 테이블 데이터 hellchang.m_angle:~12 rows (대략적) 내보내기
INSERT IGNORE INTO `m_angle` (`angle_code`, `name_kr`, `name_en`) VALUES
	('Decline', '경사 아래', 'Decline'),
	('Flat', '평평', 'Flat'),
	('Horizontal', '수평', 'Horizontal'),
	('Incline', '경사 위', 'Incline'),
	('Kneeling', '무릎 꿇고', 'Kneeling'),
	('Lying', '누워서', 'Lying'),
	('Prone', '엎드려서', 'Prone'),
	('Seated', '앉아서', 'Seated'),
	('Side', '옆으로', 'Side'),
	('Standing', '서서', 'Standing'),
	('Supine', '뒤집어서', 'Supine'),
	('Vertical', '수직', 'Vertical');

-- 테이블 hellchang.m_body_category 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_body_category` (
  `cat_code` varchar(4) NOT NULL COMMENT '카테고리 코드 (예: C01)',
  `cat_name_kr` varchar(50) NOT NULL COMMENT '카테고리명 (한글)',
  `cat_name_en` varchar(50) NOT NULL COMMENT '카테고리명 (영문)',
  PRIMARY KEY (`cat_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='신체부위 카테고리';

-- 테이블 데이터 hellchang.m_body_category:~2 rows (대략적) 내보내기
INSERT IGNORE INTO `m_body_category` (`cat_code`, `cat_name_kr`, `cat_name_en`) VALUES
	('C01', '상체', 'Upper Body'),
	('C02', '하체', 'Lower Body');

-- 테이블 hellchang.m_body_part 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_body_part` (
  `part_code` varchar(4) NOT NULL COMMENT '신체부위 코드 (예: B01)',
  `cat_code` varchar(4) NOT NULL COMMENT '카테고리 코드 (상체/하체)',
  `part_name_kr` varchar(50) NOT NULL COMMENT '신체부위명 (한글)',
  `part_name_en` varchar(50) NOT NULL COMMENT '신체부위명 (영문)',
  PRIMARY KEY (`part_code`),
  KEY `fk_part_category` (`cat_code`),
  CONSTRAINT `fk_part_category` FOREIGN KEY (`cat_code`) REFERENCES `m_body_category` (`cat_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='신체부위';

-- 테이블 데이터 hellchang.m_body_part:~10 rows (대략적) 내보내기
INSERT IGNORE INTO `m_body_part` (`part_code`, `cat_code`, `part_name_kr`, `part_name_en`) VALUES
	('B01', 'C01', '목', 'Neck'),
	('B02', 'C01', '어깨', 'Shoulder'),
	('B03', 'C01', '가슴', 'Chest'),
	('B04', 'C01', '등', 'Back'),
	('B05', 'C01', '배', 'Abdomen'),
	('B06', 'C01', '팔', 'Arm'),
	('B07', 'C02', '엉덩이', 'Glute/Hip'),
	('B08', 'C02', '허벅지', 'Thigh'),
	('B09', 'C02', '종아리', 'Calf'),
	('B10', 'C02', '발목', 'Ankle');

-- 테이블 hellchang.m_equipment 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_equipment` (
  `equipment_code` varchar(20) NOT NULL COMMENT '장비 코드',
  `name_kr` varchar(50) NOT NULL COMMENT '장비명 (한글)',
  `name_en` varchar(50) NOT NULL COMMENT '장비명 (영문)',
  PRIMARY KEY (`equipment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='장비 마스터';

-- 테이블 데이터 hellchang.m_equipment:~9 rows (대략적) 내보내기
INSERT IGNORE INTO `m_equipment` (`equipment_code`, `name_kr`, `name_en`) VALUES
	('Barbell', '바벨', 'Barbell'),
	('Bodyweight', '맨몸', 'Bodyweight'),
	('Cable', '케이블', 'Cable'),
	('Dumbbell', '덤벨', 'Dumbbell'),
	('Foam Roller', '폼롤러', 'Foam Roller'),
	('Kettlebell', '케틀벨', 'Kettlebell'),
	('Machine', '머신', 'Machine'),
	('Medicine Ball', '메디신볼', 'Medicine Ball'),
	('Resistance Band', '저항밴드', 'Resistance Band');

-- 테이블 hellchang.m_exercise 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_exercise` (
  `ex_id` int(11) NOT NULL AUTO_INCREMENT,
  `name_kr` varchar(120) NOT NULL,
  `name_en` varchar(160) DEFAULT NULL,
  `equipment` varchar(60) DEFAULT NULL COMMENT 'Barbell/Dumbbell/Machine/Bodyweight',
  `equipment_kr` varchar(50) DEFAULT NULL COMMENT '장비명 (한글)',
  `angle` varchar(40) DEFAULT NULL COMMENT 'Flat/Incline/Decline 등',
  `angle_kr` varchar(50) DEFAULT NULL COMMENT '각도명 (한글)',
  `movement` varchar(40) DEFAULT NULL COMMENT 'Press/Pull/Extension/Curl 등',
  `movement_kr` varchar(50) DEFAULT NULL COMMENT '동작명 (한글)',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`ex_id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 마스터';

-- 테이블 데이터 hellchang.m_exercise:~64 rows (대략적) 내보내기
INSERT IGNORE INTO `m_exercise` (`ex_id`, `name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) VALUES
	(1, '벤치프레스', 'Barbell Bench Press', 'Barbell', '바벨', 'Flat', '평평', 'Press', '프레스', NULL),
	(2, '인클라인 덤벨 프레스', 'Incline Dumbbell Press', 'Dumbbell', '덤벨', 'Incline', '경사 위', 'Press', '프레스', NULL),
	(3, '라잉 트라이셉스 익스텐션', 'Lying Triceps Extension', 'Barbell/Dumbbell', NULL, 'Flat', '평평', 'Extension', '신전', 'Skull Crusher 포함'),
	(4, '덤벨 벤치 프레스', 'Dumbbell Bench Press', 'Dumbbell', '덤벨', 'Flat', '평평', 'Press', '프레스', NULL),
	(5, '인클라인 바벨 프레스', 'Incline Barbell Press', 'Barbell', '바벨', 'Incline', '경사 위', 'Press', '프레스', NULL),
	(6, '디클라인 바벨 프레스', 'Decline Barbell Press', 'Barbell', '바벨', 'Decline', '경사 아래', 'Press', '프레스', NULL),
	(7, '덤벨 플라이', 'Dumbbell Fly', 'Dumbbell', '덤벨', 'Flat', '평평', 'Fly', '플라이', NULL),
	(8, '케이블 크로스오버', 'Cable Crossover', 'Cable', '케이블', 'Flat', '평평', 'Fly', '플라이', NULL),
	(9, '딥스', 'Dips', 'Bodyweight', '맨몸', 'Flat', '평평', 'Press', '프레스', '가슴/삼두 운동'),
	(10, '푸시업', 'Push-up', 'Bodyweight', '맨몸', 'Flat', '평평', 'Press', '프레스', NULL),
	(11, '바벨 스쿼트', 'Barbell Squat', 'Barbell', '바벨', 'Flat', '평평', 'Squat', '스쿼트', NULL),
	(12, '덤벨 스쿼트', 'Dumbbell Squat', 'Dumbbell', '덤벨', 'Flat', '평평', 'Squat', '스쿼트', NULL),
	(13, '레그 프레스', 'Leg Press', 'Machine', '머신', 'Flat', '평평', 'Press', '프레스', NULL),
	(14, '레그 익스텐션', 'Leg Extension', 'Machine', '머신', 'Flat', '평평', 'Extension', '신전', NULL),
	(15, '레그 컬', 'Leg Curl', 'Machine', '머신', 'Flat', '평평', 'Curl', '컬', NULL),
	(16, '런지', 'Lunge', 'Dumbbell', '덤벨', 'Flat', '평평', 'Lunge', '런지', NULL),
	(17, '스텝업', 'Step Up', 'Dumbbell', '덤벨', 'Flat', '평평', 'Step', NULL, NULL),
	(18, '바벨 데드리프트', 'Barbell Deadlift', 'Barbell', '바벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(19, '덤벨 데드리프트', 'Dumbbell Deadlift', 'Dumbbell', '덤벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(20, '바벨 로우', 'Barbell Row', 'Barbell', '바벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(21, '덤벨 로우', 'Dumbbell Row', 'Dumbbell', '덤벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(22, '케이블 로우', 'Cable Row', 'Cable', '케이블', 'Flat', '평평', 'Pull', '풀', NULL),
	(23, '풀업', 'Pull-up', 'Bodyweight', '맨몸', 'Flat', '평평', 'Pull', '풀', NULL),
	(25, '덤벨 컬', 'Dumbbell Curl', 'Dumbbell', '덤벨', 'Flat', '평평', 'Curl', '컬', NULL),
	(26, '해머 컬', 'Hammer Curl', 'Dumbbell', '덤벨', 'Flat', '평평', 'Curl', '컬', NULL),
	(27, '케이블 컬', 'Cable Curl', 'Cable', '케이블', 'Flat', '평평', 'Curl', '컬', NULL),
	(28, '바벨 오버헤드 프레스', 'Barbell Overhead Press', 'Barbell', '바벨', 'Flat', '평평', 'Press', '프레스', NULL),
	(29, '덤벨 숄더 프레스', 'Dumbbell Shoulder Press', 'Dumbbell', '덤벨', 'Flat', '평평', 'Press', '프레스', NULL),
	(30, '사이드 레터럴 레이즈', 'Side Lateral Raise', 'Dumbbell', '덤벨', 'Flat', '평평', 'Raise', NULL, NULL),
	(31, '프론트 레터럴 레이즈', 'Front Lateral Raise', 'Dumbbell', '덤벨', 'Flat', '평평', 'Raise', NULL, NULL),
	(32, '리어 델트 플라이', 'Rear Delt Fly', 'Dumbbell', '덤벨', 'Flat', '평평', 'Fly', '플라이', NULL),
	(33, '바벨 슈러그', 'Barbell Shrug', 'Barbell', '바벨', 'Flat', '평평', 'Shrug', NULL, NULL),
	(34, '덤벨 슈러그', 'Dumbbell Shrug', 'Dumbbell', '덤벨', 'Flat', '평평', 'Shrug', NULL, NULL),
	(35, '바벨 업라이트 로우', 'Barbell Upright Row', 'Barbell', '바벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(36, '덤벨 업라이트 로우', 'Dumbbell Upright Row', 'Dumbbell', '덤벨', 'Flat', '평평', 'Pull', '풀', NULL),
	(37, '바벨 트라이셉스 익스텐션', 'Barbell Triceps Extension', 'Barbell', '바벨', 'Flat', '평평', 'Extension', '신전', NULL),
	(38, '덤벨 트라이셉스 익스텐션', 'Dumbbell Triceps Extension', 'Dumbbell', '덤벨', 'Flat', '평평', 'Extension', '신전', NULL),
	(39, '케이블 트라이셉스 푸시다운', 'Cable Triceps Pushdown', 'Cable', '케이블', 'Flat', '평평', 'Pushdown', NULL, NULL),
	(40, '딥스(삼두)', 'Dips (Triceps)', 'Bodyweight', '맨몸', 'Flat', '평평', 'Press', '프레스', '삼두 중심'),
	(41, '클로즈 그립 벤치프레스', 'Close Grip Bench Press', 'Barbell', '바벨', 'Flat', '평평', 'Press', '프레스', '삼두 중심'),
	(42, '바벨 트라이셉스 익스텐션', 'Barbell Triceps Extension', 'Barbell', '바벨', 'Flat', '평평', 'Extension', '신전', 'Skull Crusher'),
	(43, '덤벨 트라이셉스 익스텐션', 'Dumbbell Triceps Extension', 'Dumbbell', '덤벨', 'Flat', '평평', 'Extension', '신전', NULL),
	(44, '케이블 트라이셉스 익스텐션', 'Cable Triceps Extension', 'Cable', '케이블', 'Flat', '평평', 'Extension', '신전', NULL),
	(45, '바벨 트라이셉스 킥백', 'Barbell Triceps Kickback', 'Barbell', '바벨', 'Flat', '평평', 'Kickback', NULL, NULL),
	(46, '덤벨 트라이셉스 킥백', 'Dumbbell Triceps Kickback', 'Dumbbell', '덤벨', 'Flat', '평평', 'Kickback', NULL, NULL),
	(47, '케이블 트라이셉스 킥백', 'Cable Triceps Kickback', 'Cable', '케이블', 'Flat', '평평', 'Kickback', NULL, NULL),
	(48, '바벨 트라이셉스 딥스', 'Barbell Triceps Dips', 'Barbell', '바벨', 'Flat', '평평', 'Dips', NULL, NULL),
	(49, '덤벨 트라이셉스 딥스', 'Dumbbell Triceps Dips', 'Dumbbell', '덤벨', 'Flat', '평평', 'Dips', NULL, NULL),
	(50, '케이블 트라이셉스 딥스', 'Cable Triceps Dips', 'Cable', '케이블', 'Flat', '평평', 'Dips', NULL, NULL),
	(51, '덤벨 프레스', 'Dumbbell Press', 'Dumbbell', '덤벨', 'Flat', '평평', 'Press', '프레스', NULL),
	(52, '버티컬 체스트', 'Vertical Chest', 'Machine', '머신', 'Vertical', '수직', 'Press', '프레스', '가슴 상부 중심 운동'),
	(53, '펙 덱 플라이', 'Pec Deck Fly', 'Machine', NULL, 'Seated', NULL, 'Press', NULL, NULL),
	(56, '암 풀 다운', 'Arm Pulldown', 'Cable', '케이블', 'Flat', '평평', 'Pull', '풀', '직선 팔로 광배근 수축 강조'),
	(57, '어시스트 풀업', 'Assisted Pull-up', 'Machine', '머신', 'Flat', '평평', 'Pull', '풀', '풀업 보조 머신 또는 밴드 사용'),
	(58, '랫 풀 다운', 'Lat Pulldown', 'Machine', '머신', 'Seated', '앉아서', 'Pull', '풀', '광배근 발달에 효과적인 기본 운동'),
	(59, '시티드 로우', 'Seated Row', 'Machine', '머신', 'Seated', '앉아서', 'Pull', '풀', '등 중앙부 발달에 효과적인 기본 운동'),
	(60, '바벨 컬', 'Barbell Curl', 'Barbell', '바벨', 'Standing', '서서', 'Curl', '컬', '이두근 발달에 효과적인 기본 운동'),
	(61, '인클라인 덤벨 컬', 'Incline Dumbbell Curl', 'Dumbbell', '덤벨', 'Incline', '경사 위', 'Curl', '컬', '이두근 장두 스트레칭 강조 운동'),
	(62, '벤트 오버 레터럴 레이즈', 'Bent-over Lateral Raise', 'Dumbbell', '덤벨', NULL, NULL, 'Raise', '레이즈', '어깨 측면(측면/후면) 집중'),
	(63, '바벨 백 스쿼트', 'Barbell Back Squat', 'Barbell', '바벨', 'Flat', '평평', 'Squat', '스쿼트', '바벨을 어깨에 올리고 수행하는 기본적인 스쿼트 운동'),
	(64, '파워 레그프레스', 'Power Leg Press', 'Machine', '머신', 'Flat', '평평', 'Press', '프레스', '더 높은 무게로 수행하는 레그 프레스 운동'),
	(65, '아웃타이', 'Outer Thigh', 'Machine', '머신', 'Side', '옆으로', 'Abduction', '외전', '대퇴 외전근을 강화하는 운동'),
	(67, '힙스러스트', 'Hip Thrust', 'Barbell', '바벨', 'Supine', '뒤집어서', 'Thrust', '스러스트', '엉덩이(둔근) 중심의 힙 확장 운동'),
	(68, '어덕션', 'Adduction', 'Machine', '머신', 'Side', '옆으로', 'Adduction', '내전', '대퇴 내전근을 강화하는 운동');

-- 테이블 hellchang.m_exercise_alias 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_exercise_alias` (
  `alias` varchar(160) NOT NULL,
  `ex_id` int(11) NOT NULL,
  PRIMARY KEY (`alias`),
  KEY `idx_alias_ex` (`ex_id`),
  CONSTRAINT `fk_alias_ex` FOREIGN KEY (`ex_id`) REFERENCES `m_exercise` (`ex_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 별칭';

-- 테이블 데이터 hellchang.m_exercise_alias:~85 rows (대략적) 내보내기
INSERT IGNORE INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
	('인클라인 DB 프레스', 2),
	('DB 벤치', 4),
	('덤벨 벤치', 4),
	('덤벨 벤치프레스', 4),
	('인클라인 바벨', 5),
	('인클라인 벤치', 5),
	('디클라인 바벨', 6),
	('디클라인 벤치', 6),
	('덤벨 플라이', 7),
	('케이블 크로스', 8),
	('바벨 스쿼트', 11),
	('덤벨 스쿼트', 12),
	('레그 프레스', 13),
	('레그 익스텐션', 14),
	('레그 컬', 15),
	('바벨 데드', 18),
	('바벨 로우', 20),
	('덤벨 로우', 21),
	('덤벨 컬', 25),
	('해머 컬', 26),
	('바벨 숄더', 28),
	('덤벨 숄더', 29),
	('사이드 레터럴', 30),
	('프론트 레터럴', 31),
	('리어 델트', 32),
	('리얼 델트 플라이', 32),
	('바벨 슈러그', 33),
	('덤벨 슈러그', 34),
	('바벨 업라이트', 35),
	('덤벨 업라이트', 36),
	('바벨 트라이', 37),
	('덤벨 트라이', 38),
	('케이블 트라이', 39),
	('딥스 삼두', 40),
	('클로즈 그립', 41),
	('스컬 크러셔', 42),
	('케이블 트라이 익스텐션', 44),
	('바벨 트라이 킥백', 45),
	('덤벨 트라이 킥백', 46),
	('케이블 트라이 킥백', 47),
	('바벨 트라이 딥스', 48),
	('덤벨 트라이 딥스', 49),
	('케이블 트라이 딥스', 50),
	('덤벨 프레스', 51),
	('버티컬 가슴', 52),
	('버티컬 체스트', 52),
	('스트레이트 암 풀다운', 56),
	('암 풀다운', 56),
	('직선 팔 풀다운', 56),
	('머신 풀업', 57),
	('밴드 풀업', 57),
	('보조 풀업', 57),
	('어시스트 풀다운', 57),
	('풀업 보조', 57),
	('광배 풀다운', 58),
	('라트 풀다운', 58),
	('라트풀다운', 58),
	('랫 풀다운', 58),
	('랫풀다운', 58),
	('백 풀다운', 58),
	('와이드 그립 풀다운', 58),
	('머신 로우', 59),
	('백 로우', 59),
	('시티드 로우', 59),
	('시티드 케이블 로우', 59),
	('시티드로우', 59),
	('앉아서 로우', 59),
	('케이블 시티드 로우', 59),
	('경사 덤벨 컬', 61),
	('경사 위 덤벨 컬', 61),
	('인클라인 덤벨 바이셉 컬', 61),
	('인클라인 덤벨 이두 컬', 61),
	('인클라인 덤벨 컬', 61),
	('인클라인 컬', 61),
	('인클라인덤벨컬', 61),
	('벤트 오버 레터럴', 62),
	('벤트 오버 레터럴 레이즈', 62),
	('벤트오버 레터럴', 62),
	('벤트오버 레터럴 레이즈', 62),
	('벤트오버 레터럴레이즈', 62),
	('바벨 백 스쿼트', 63),
	('파워 레그프레스', 64),
	('아웃타이', 65),
	('힙스러스트', 67),
	('어덕션', 68);

-- 테이블 hellchang.m_exercise_muscle_target 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_exercise_muscle_target` (
  `ex_id` int(11) NOT NULL,
  `muscle_code` varchar(6) NOT NULL,
  `priority` tinyint(4) NOT NULL DEFAULT 1,
  `weight` decimal(4,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`ex_id`,`muscle_code`,`priority`),
  KEY `fk_ex_muscle_m` (`muscle_code`),
  CONSTRAINT `fk_ex_muscle_ex` FOREIGN KEY (`ex_id`) REFERENCES `m_exercise` (`ex_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ex_muscle_m` FOREIGN KEY (`muscle_code`) REFERENCES `m_muscle` (`muscle_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동-근육 매핑';

-- 테이블 데이터 hellchang.m_exercise_muscle_target:~151 rows (대략적) 내보내기
INSERT IGNORE INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
	(1, 'M1201', 2, 0.40),
	(1, 'M1302', 1, 1.00),
	(1, 'M1604', 2, 0.60),
	(2, 'M1301', 1, 1.00),
	(3, 'M1604', 1, 1.00),
	(4, 'M1201', 2, 0.40),
	(4, 'M1302', 1, 1.00),
	(4, 'M1604', 2, 0.60),
	(5, 'M1201', 2, 0.40),
	(5, 'M1301', 1, 1.00),
	(5, 'M1604', 2, 0.55),
	(6, 'M1201', 2, 0.40),
	(6, 'M1303', 1, 1.00),
	(6, 'M1604', 2, 0.55),
	(7, 'M1302', 1, 1.00),
	(7, 'M1304', 2, 0.30),
	(8, 'M1302', 1, 1.00),
	(8, 'M1304', 2, 0.30),
	(9, 'M1302', 1, 0.70),
	(9, 'M1604', 1, 0.70),
	(10, 'M1302', 1, 0.70),
	(10, 'M1604', 1, 0.70),
	(11, 'M2101', 1, 1.00),
	(11, 'M2102', 1, 1.00),
	(11, 'M2103', 2, 0.60),
	(12, 'M2101', 1, 1.00),
	(12, 'M2102', 1, 1.00),
	(12, 'M2103', 2, 0.60),
	(13, 'M2101', 1, 1.00),
	(13, 'M2102', 1, 1.00),
	(13, 'M2103', 2, 0.60),
	(14, 'M2101', 1, 1.00),
	(15, 'M2104', 1, 1.00),
	(16, 'M2101', 1, 1.00),
	(16, 'M2102', 1, 1.00),
	(16, 'M2103', 2, 0.60),
	(17, 'M2101', 1, 1.00),
	(17, 'M2102', 1, 1.00),
	(18, 'M1401', 1, 1.00),
	(18, 'M1402', 1, 1.00),
	(18, 'M1403', 1, 1.00),
	(19, 'M1401', 1, 1.00),
	(19, 'M1402', 1, 1.00),
	(19, 'M1403', 1, 1.00),
	(20, 'M1401', 1, 1.00),
	(20, 'M1402', 1, 1.00),
	(21, 'M1401', 1, 1.00),
	(21, 'M1402', 1, 1.00),
	(22, 'M1401', 1, 1.00),
	(22, 'M1402', 1, 1.00),
	(23, 'M1401', 1, 1.00),
	(23, 'M1402', 1, 1.00),
	(25, 'M1601', 1, 1.00),
	(26, 'M1601', 1, 1.00),
	(27, 'M1601', 1, 1.00),
	(28, 'M1201', 1, 1.00),
	(28, 'M1202', 2, 0.60),
	(29, 'M1201', 1, 1.00),
	(29, 'M1202', 2, 0.60),
	(30, 'M1202', 1, 1.00),
	(31, 'M1201', 1, 1.00),
	(32, 'M1203', 1, 1.00),
	(33, 'M1401', 1, 1.00),
	(34, 'M1401', 1, 1.00),
	(35, 'M1201', 1, 1.00),
	(35, 'M1202', 2, 0.60),
	(36, 'M1201', 1, 1.00),
	(36, 'M1202', 2, 0.60),
	(37, 'M1604', 1, 1.00),
	(38, 'M1604', 1, 1.00),
	(39, 'M1604', 1, 1.00),
	(40, 'M1604', 1, 1.00),
	(41, 'M1302', 2, 0.40),
	(41, 'M1604', 1, 1.00),
	(42, 'M1604', 1, 1.00),
	(43, 'M1604', 1, 1.00),
	(44, 'M1604', 1, 1.00),
	(45, 'M1604', 1, 1.00),
	(46, 'M1604', 1, 1.00),
	(47, 'M1604', 1, 1.00),
	(48, 'M1604', 1, 1.00),
	(49, 'M1604', 1, 1.00),
	(50, 'M1604', 1, 1.00),
	(51, 'M1302', 1, 1.00),
	(51, 'M1604', 2, 0.60),
	(52, 'M1301', 1, 1.00),
	(52, 'M1604', 2, 0.60),
	(53, 'M1201', 2, 0.40),
	(53, 'M1302', 1, 1.00),
	(53, 'M1304', 2, 0.30),
	(56, 'M1208', 2, 0.30),
	(56, 'M1404', 1, 1.00),
	(56, 'M1405', 2, 0.20),
	(56, 'M1604', 2, 0.40),
	(57, 'M1203', 2, 0.40),
	(57, 'M1402', 2, 0.60),
	(57, 'M1403', 2, 0.50),
	(57, 'M1404', 1, 1.00),
	(57, 'M1405', 1, 0.80),
	(57, 'M1601', 2, 0.30),
	(57, 'M1602', 2, 0.30),
	(57, 'M1603', 2, 0.20),
	(58, 'M1203', 2, 0.40),
	(58, 'M1208', 2, 0.30),
	(58, 'M1402', 2, 0.60),
	(58, 'M1403', 2, 0.50),
	(58, 'M1404', 1, 1.00),
	(58, 'M1405', 1, 0.80),
	(58, 'M1601', 2, 0.30),
	(58, 'M1602', 2, 0.30),
	(58, 'M1603', 2, 0.20),
	(59, 'M1203', 2, 0.60),
	(59, 'M1208', 2, 0.50),
	(59, 'M1402', 1, 0.90),
	(59, 'M1403', 1, 0.80),
	(59, 'M1404', 2, 0.20),
	(59, 'M1405', 1, 1.00),
	(59, 'M1601', 2, 0.40),
	(59, 'M1602', 2, 0.40),
	(59, 'M1603', 2, 0.30),
	(60, 'M1201', 2, 0.30),
	(60, 'M1402', 2, 0.20),
	(60, 'M1601', 1, 1.00),
	(60, 'M1602', 1, 1.00),
	(60, 'M1603', 2, 0.60),
	(60, 'M1701', 2, 0.15),
	(61, 'M1201', 2, 0.25),
	(61, 'M1601', 1, 0.80),
	(61, 'M1602', 1, 1.00),
	(61, 'M1603', 2, 0.50),
	(61, 'M1701', 2, 0.15),
	(62, 'M1202', 1, 1.00),
	(62, 'M1203', 2, 0.40),
	(63, 'M2101', 1, 1.00),
	(63, 'M2102', 1, 1.00),
	(63, 'M2103', 2, 0.60),
	(64, 'M2101', 1, 1.00),
	(64, 'M2102', 1, 1.00),
	(64, 'M2103', 2, 0.60),
	(65, 'M2101', 1, 1.00),
	(65, 'M2102', 2, 0.50),
	(65, 'M2103', 2, 0.30),
	(67, 'M1401', 1, 1.00),
	(67, 'M1402', 1, 0.80),
	(67, 'M1403', 2, 0.60),
	(67, 'M2104', 2, 0.40),
	(68, 'M1709', 1, 1.00),
	(68, 'M1710', 1, 0.80),
	(68, 'M1711', 1, 0.90),
	(68, 'M1712', 2, 0.60),
	(68, 'M1713', 2, 0.50);

-- 테이블 hellchang.m_exercise_zone_target 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_exercise_zone_target` (
  `ex_id` int(11) NOT NULL,
  `zone_code` varchar(7) NOT NULL,
  `priority` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1=Primary, 2=Secondary, 3=Tertiary',
  `weight` decimal(4,2) NOT NULL DEFAULT 1.00 COMMENT '기여도(합계 자유)',
  PRIMARY KEY (`ex_id`,`zone_code`,`priority`),
  KEY `fk_ex_zone_zone` (`zone_code`),
  CONSTRAINT `fk_ex_zone_ex` FOREIGN KEY (`ex_id`) REFERENCES `m_exercise` (`ex_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ex_zone_zone` FOREIGN KEY (`zone_code`) REFERENCES `m_part_zone` (`zone_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동-세부존 매핑';

-- 테이블 데이터 hellchang.m_exercise_zone_target:~95 rows (대략적) 내보내기
INSERT IGNORE INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
	(1, 'Z-AR-TR', 2, 0.60),
	(1, 'Z-CH-MD', 1, 1.00),
	(1, 'Z-SH-AN', 2, 0.40),
	(2, 'Z-AR-TR', 2, 0.55),
	(2, 'Z-CH-UP', 1, 1.00),
	(2, 'Z-SH-AN', 2, 0.45),
	(3, 'Z-AR-TR', 1, 1.00),
	(4, 'Z-AR-TR', 2, 0.60),
	(4, 'Z-CH-MD', 1, 1.00),
	(4, 'Z-SH-AN', 2, 0.40),
	(5, 'Z-AR-TR', 2, 0.55),
	(5, 'Z-CH-UP', 1, 1.00),
	(5, 'Z-SH-AN', 2, 0.45),
	(6, 'Z-AR-TR', 2, 0.55),
	(6, 'Z-CH-LW', 1, 1.00),
	(6, 'Z-SH-AN', 2, 0.45),
	(7, 'Z-CH-MD', 1, 1.00),
	(8, 'Z-CH-MD', 1, 1.00),
	(9, 'Z-AR-TR', 1, 0.70),
	(9, 'Z-CH-MD', 1, 0.70),
	(10, 'Z-AR-TR', 1, 0.70),
	(10, 'Z-CH-MD', 1, 0.70),
	(11, 'Z-LE-GL', 2, 0.60),
	(11, 'Z-LE-QU', 1, 1.00),
	(12, 'Z-LE-GL', 2, 0.60),
	(12, 'Z-LE-QU', 1, 1.00),
	(13, 'Z-LE-GL', 2, 0.60),
	(13, 'Z-LE-QU', 1, 1.00),
	(14, 'Z-LE-QU', 1, 1.00),
	(15, 'Z-LE-HA', 1, 1.00),
	(16, 'Z-LE-GL', 2, 0.60),
	(16, 'Z-LE-QU', 1, 1.00),
	(17, 'Z-LE-GL', 2, 0.60),
	(17, 'Z-LE-QU', 1, 1.00),
	(18, 'Z-BK-MD', 1, 1.00),
	(18, 'Z-BK-UP', 1, 1.00),
	(19, 'Z-BK-MD', 1, 1.00),
	(19, 'Z-BK-UP', 1, 1.00),
	(20, 'Z-BK-MD', 1, 1.00),
	(20, 'Z-BK-UP', 1, 1.00),
	(21, 'Z-BK-MD', 1, 1.00),
	(21, 'Z-BK-UP', 1, 1.00),
	(22, 'Z-BK-MD', 1, 1.00),
	(22, 'Z-BK-UP', 1, 1.00),
	(23, 'Z-BK-MD', 1, 1.00),
	(23, 'Z-BK-UP', 1, 1.00),
	(25, 'Z-AR-BI', 1, 1.00),
	(26, 'Z-AR-BI', 1, 1.00),
	(27, 'Z-AR-BI', 1, 1.00),
	(28, 'Z-SH-AN', 1, 1.00),
	(28, 'Z-SH-LT', 2, 0.60),
	(29, 'Z-SH-AN', 1, 1.00),
	(29, 'Z-SH-LT', 2, 0.60),
	(30, 'Z-SH-LT', 1, 1.00),
	(31, 'Z-SH-AN', 1, 1.00),
	(32, 'Z-SH-PO', 1, 1.00),
	(33, 'Z-BK-UP', 1, 1.00),
	(34, 'Z-BK-UP', 1, 1.00),
	(35, 'Z-SH-AN', 1, 1.00),
	(35, 'Z-SH-LT', 2, 0.60),
	(36, 'Z-SH-AN', 1, 1.00),
	(36, 'Z-SH-LT', 2, 0.60),
	(37, 'Z-AR-TR', 1, 1.00),
	(38, 'Z-AR-TR', 1, 1.00),
	(39, 'Z-AR-TR', 1, 1.00),
	(40, 'Z-AR-TR', 1, 1.00),
	(41, 'Z-AR-TR', 1, 1.00),
	(41, 'Z-CH-MD', 2, 0.40),
	(42, 'Z-AR-TR', 1, 1.00),
	(43, 'Z-AR-TR', 1, 1.00),
	(44, 'Z-AR-TR', 1, 1.00),
	(45, 'Z-AR-TR', 1, 1.00),
	(46, 'Z-AR-TR', 1, 1.00),
	(47, 'Z-AR-TR', 1, 1.00),
	(48, 'Z-AR-TR', 1, 1.00),
	(49, 'Z-AR-TR', 1, 1.00),
	(50, 'Z-AR-TR', 1, 1.00),
	(51, 'Z-AR-TR', 2, 0.60),
	(51, 'Z-CH-MD', 1, 1.00),
	(52, 'Z-CH-UP', 1, 1.00),
	(52, 'Z-SH-AN', 2, 0.60),
	(53, 'Z-CH-MD', 1, 1.00),
	(53, 'Z-SH-AN', 1, 0.40),
	(62, 'Z-SH-LT', 1, 1.00),
	(62, 'Z-SH-PO', 2, 0.40),
	(63, 'Z-LE-GL', 2, 0.60),
	(63, 'Z-LE-QU', 1, 1.00),
	(64, 'Z-LE-GL', 2, 0.60),
	(64, 'Z-LE-QU', 1, 1.00),
	(65, 'Z-LE-GL', 2, 0.30),
	(65, 'Z-LE-QU', 1, 1.00),
	(67, 'Z-LE-GL', 1, 1.00),
	(67, 'Z-LE-HA', 2, 0.40),
	(68, 'Z-LE-GL', 1, 1.00),
	(68, 'Z-LE-QU', 2, 0.30);

-- 테이블 hellchang.m_movement 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_movement` (
  `movement_code` varchar(20) NOT NULL COMMENT '동작 코드',
  `name_kr` varchar(50) NOT NULL COMMENT '동작명 (한글)',
  `name_en` varchar(50) NOT NULL COMMENT '동작명 (영문)',
  PRIMARY KEY (`movement_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='동작 유형 마스터';

-- 테이블 데이터 hellchang.m_movement:~17 rows (대략적) 내보내기
INSERT IGNORE INTO `m_movement` (`movement_code`, `name_kr`, `name_en`) VALUES
	('Curl', '컬', 'Curl'),
	('Dips', '딥스', 'Dips'),
	('Extension', '신전', 'Extension'),
	('Fly', '플라이', 'Fly'),
	('Isometric', '등척성', 'Isometric'),
	('Kickback', '킥백', 'Kickback'),
	('Lunge', '런지', 'Lunge'),
	('Plyometric', '플라이오메트릭', 'Plyometric'),
	('Press', '프레스', 'Press'),
	('Pull', '풀', 'Pull'),
	('Push', '푸시', 'Push'),
	('Raise', '레이즈', 'Raise'),
	('Rotation', '회전', 'Rotation'),
	('Row', '로우', 'Row'),
	('Shrug', '슈러그', 'Shrug'),
	('Squat', '스쿼트', 'Squat'),
	('Step', '스텝', 'Step');

-- 테이블 hellchang.m_muscle 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_muscle` (
  `muscle_code` varchar(6) NOT NULL COMMENT '근육 코드 (예: M101)',
  `part_code` varchar(4) NOT NULL COMMENT '신체부위 코드 (예: B08)',
  `name_kr` varchar(80) NOT NULL COMMENT '근육(또는 근육군) 이름 - 한글',
  `name_en` varchar(120) NOT NULL COMMENT '근육(또는 근육군) 이름 - 영어',
  `note` varchar(255) DEFAULT NULL COMMENT '비고 (선택)',
  PRIMARY KEY (`muscle_code`),
  UNIQUE KEY `uq_part_name_kr` (`part_code`,`name_kr`),
  KEY `idx_muscle_part` (`part_code`),
  CONSTRAINT `fk_muscle_part` FOREIGN KEY (`part_code`) REFERENCES `m_body_part` (`part_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='신체부위 소속 근육(또는 근육군)';

-- 테이블 데이터 hellchang.m_muscle:~84 rows (대략적) 내보내기
INSERT IGNORE INTO `m_muscle` (`muscle_code`, `part_code`, `name_kr`, `name_en`, `note`) VALUES
	('M1101', 'B01', '흉쇄유돌근', 'Sternocleidomastoid', NULL),
	('M1102', 'B01', '사각근(전/중/후)', 'Scalenes (Ant/Mid/Post)', NULL),
	('M1103', 'B01', '견갑거근', 'Levator Scapulae', NULL),
	('M1104', 'B01', '극상/경근', 'Splenius Capitis/Cervicis', NULL),
	('M1105', 'B01', '승모근 상부', 'Upper Trapezius', NULL),
	('M1201', 'B02', '삼각근(전면)', 'Deltoid (Anterior)', NULL),
	('M1202', 'B02', '삼각근(측면)', 'Deltoid (Lateral)', NULL),
	('M1203', 'B02', '삼각근(후면)', 'Deltoid (Posterior)', NULL),
	('M1204', 'B02', '극상근', 'Supraspinatus', NULL),
	('M1205', 'B02', '극하근', 'Infraspinatus', NULL),
	('M1206', 'B02', '소원근', 'Teres Minor', NULL),
	('M1207', 'B02', '견갑하근', 'Subscapularis', NULL),
	('M1208', 'B02', '대원근', 'Teres Major', NULL),
	('M1301', 'B03', '대흉근(쇄골부)', 'Pectoralis Major (Clavicular Head)', NULL),
	('M1302', 'B03', '대흉근(흉골부)', 'Pectoralis Major (Sternal Head)', NULL),
	('M1303', 'B03', '소흉근', 'Pectoralis Minor', NULL),
	('M1304', 'B03', '전거근', 'Serratus Anterior', NULL),
	('M1305', 'B03', '외늑간근', 'External Intercostals', NULL),
	('M1401', 'B04', '승모근(상부)', 'Trapezius (Upper)', NULL),
	('M1402', 'B04', '승모근(중부)', 'Trapezius (Middle)', NULL),
	('M1403', 'B04', '승모근(하부)', 'Trapezius (Lower)', NULL),
	('M1404', 'B04', '광배근', 'Latissimus Dorsi', NULL),
	('M1405', 'B04', '능형근(대/소)', 'Rhomboid Major/Minor', NULL),
	('M1406', 'B04', '기립근-장늑근', 'Erector Spinae – Iliocostalis', NULL),
	('M1407', 'B04', '기립근-최장근', 'Erector Spinae – Longissimus', NULL),
	('M1408', 'B04', '기립근-극근', 'Erector Spinae – Spinalis', NULL),
	('M1409', 'B04', '견갑거근', 'Levator Scapulae', NULL),
	('M1501', 'B05', '복직근', 'Rectus Abdominis', NULL),
	('M1502', 'B05', '외복사근', 'External Oblique', NULL),
	('M1503', 'B05', '내복사근', 'Internal Oblique', NULL),
	('M1504', 'B05', '복횡근', 'Transversus Abdominis', NULL),
	('M1601', 'B06', '이두근(단두)', 'Biceps Brachii (Short Head)', NULL),
	('M1602', 'B06', '이두근(장두)', 'Biceps Brachii (Long Head)', NULL),
	('M1603', 'B06', '상완근', 'Brachialis', NULL),
	('M1604', 'B06', '삼두근(장두)', 'Triceps Brachii (Long Head)', NULL),
	('M1605', 'B06', '삼두근(외측두)', 'Triceps Brachii (Lateral Head)', NULL),
	('M1606', 'B06', '삼두근(내측두)', 'Triceps Brachii (Medial Head)', NULL),
	('M1607', 'B06', '상완요골근', 'Brachioradialis', NULL),
	('M1608', 'B06', '요측수근굴근', 'Flexor Carpi Radialis', NULL),
	('M1609', 'B06', '척측수근굴근', 'Flexor Carpi Ulnaris', NULL),
	('M1610', 'B06', '장장근', 'Palmaris Longus', NULL),
	('M1611', 'B06', '천지굴근', 'Flexor Digitorum Superficialis', NULL),
	('M1612', 'B06', '요측수근신근-장/단', 'Extensor Carpi Radialis Longus/Brevis', NULL),
	('M1613', 'B06', '척측수근신근', 'Extensor Carpi Ulnaris', NULL),
	('M1614', 'B06', '지신근', 'Extensor Digitorum', NULL),
	('M1701', 'B07', '대둔근', 'Gluteus Maximus', NULL),
	('M1702', 'B07', '중둔근', 'Gluteus Medius', NULL),
	('M1703', 'B07', '소둔근', 'Gluteus Minimus', NULL),
	('M1704', 'B07', '장경근막장근', 'Tensor Fasciae Latae', NULL),
	('M1705', 'B07', '이상근', 'Piriformis', NULL),
	('M1706', 'B07', '폐쇄근(내)', 'Obturator Internus', NULL),
	('M1707', 'B07', '쌍둔근(상/하)', 'Gemellus (Superior/Inferior)', NULL),
	('M1708', 'B07', '대퇴방형근', 'Quadratus Femoris', NULL),
	('M1709', 'B07', '내전근(장)', 'Adductor Longus', NULL),
	('M1710', 'B07', '내전근(단)', 'Adductor Brevis', NULL),
	('M1711', 'B07', '내전근(대)', 'Adductor Magnus', NULL),
	('M1712', 'B07', '치골근', 'Pectineus', NULL),
	('M1713', 'B07', '박근', 'Gracilis', NULL),
	('M1801', 'B08', '대퇴직근', 'Rectus Femoris', NULL),
	('M1802', 'B08', '외측광근', 'Vastus Lateralis', NULL),
	('M1803', 'B08', '내측광근', 'Vastus Medialis', NULL),
	('M1804', 'B08', '중간광근', 'Vastus Intermedius', NULL),
	('M1805', 'B08', '봉공근', 'Sartorius', NULL),
	('M1806', 'B08', '대퇴이두근(장두)', 'Biceps Femoris (Long Head)', NULL),
	('M1807', 'B08', '대퇴이두근(단두)', 'Biceps Femoris (Short Head)', NULL),
	('M1808', 'B08', '반건양근', 'Semitendinosus', NULL),
	('M1809', 'B08', '반막양근', 'Semimembranosus', NULL),
	('M1901', 'B09', '비복근(내측두)', 'Gastrocnemius (Medial Head)', NULL),
	('M1902', 'B09', '비복근(외측두)', 'Gastrocnemius (Lateral Head)', NULL),
	('M1903', 'B09', '가자미근', 'Soleus', NULL),
	('M1904', 'B09', '족저근', 'Plantaris', NULL),
	('M1905', 'B09', '전경골근', 'Tibialis Anterior', NULL),
	('M1906', 'B09', '장비골근', 'Peroneus (Fibularis) Longus', NULL),
	('M1907', 'B09', '단비골근', 'Peroneus (Fibularis) Brevis', NULL),
	('M1908', 'B09', '후경골근', 'Tibialis Posterior', NULL),
	('M1909', 'B09', '장무지굴근', 'Flexor Hallucis Longus', NULL),
	('M1910', 'B09', '장지굴근', 'Flexor Digitorum Longus', NULL),
	('M2001', 'B10', '장무지신근', 'Extensor Hallucis Longus', NULL),
	('M2002', 'B10', '장지신근', 'Extensor Digitorum Longus', NULL),
	('M2003', 'B10', '제3비골근', 'Peroneus (Fibularis) Tertius', NULL),
	('M2101', 'B08', '대퇴사두근', 'Quadriceps Femoris', '대퇴직근+외측광근+내측광근+중간광근'),
	('M2102', 'B08', '대퇴사두근(전면)', 'Quadriceps (Anterior)', '무릎 신전'),
	('M2103', 'B08', '대퇴사두근(측면)', 'Quadriceps (Lateral)', '무릎 안정화'),
	('M2104', 'B08', '햄스트링', 'Hamstrings', '대퇴이두근+반건양근+반막양근');

-- 테이블 hellchang.m_part_zone 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_part_zone` (
  `zone_code` varchar(7) NOT NULL COMMENT '세부부위 코드 (예: Z-CH-UP)',
  `part_code` varchar(4) NOT NULL COMMENT '상위 신체부위 코드 (예: B03=가슴)',
  `zone_name_kr` varchar(80) NOT NULL COMMENT '세부부위명(한글)',
  `zone_name_en` varchar(120) NOT NULL COMMENT '세부부위명(영문)',
  PRIMARY KEY (`zone_code`),
  KEY `idx_zone_part` (`part_code`),
  CONSTRAINT `fk_zone_part` FOREIGN KEY (`part_code`) REFERENCES `m_body_part` (`part_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='신체부위 세부존';

-- 테이블 데이터 hellchang.m_part_zone:~13 rows (대략적) 내보내기
INSERT IGNORE INTO `m_part_zone` (`zone_code`, `part_code`, `zone_name_kr`, `zone_name_en`) VALUES
	('Z-AR-BI', 'B06', '팔 이두', 'Biceps'),
	('Z-AR-TR', 'B06', '팔 삼두', 'Triceps'),
	('Z-BK-MD', 'B04', '등 중부', 'Mid Back/Rhomboids'),
	('Z-BK-UP', 'B04', '등 상부', 'Upper Back/Traps'),
	('Z-CH-LW', 'B03', '가슴 하부', 'Chest Lower (Costal)'),
	('Z-CH-MD', 'B03', '가슴 중부', 'Chest Middle (Sternal)'),
	('Z-CH-UP', 'B03', '가슴 상부', 'Chest Upper (Clavicular)'),
	('Z-LE-GL', 'B07', '엉덩이', 'Glute/Hip'),
	('Z-LE-HA', 'B08', '허벅지 후면', 'Thigh Posterior (Hamstrings)'),
	('Z-LE-QU', 'B08', '허벅지 전면', 'Thigh Anterior (Quadriceps)'),
	('Z-SH-AN', 'B02', '어깨 전면', 'Anterior Deltoid'),
	('Z-SH-LT', 'B02', '어깨 측면', 'Lateral Deltoid'),
	('Z-SH-PO', 'B02', '어깨 후면', 'Posterior Deltoid');

-- 테이블 hellchang.m_temp_exercise 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_temp_exercise` (
  `temp_ex_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exercise_name` varchar(160) NOT NULL COMMENT '사용자가 입력한 운동명',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_ex_id` int(11) DEFAULT NULL COMMENT '승인 시 연결될 정식 운동 ID',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`temp_ex_id`),
  KEY `idx_temp_user` (`user_id`),
  KEY `idx_temp_status` (`status`),
  CONSTRAINT `fk_temp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.m_temp_exercise:~1 rows (대략적) 내보내기
INSERT IGNORE INTO `m_temp_exercise` (`temp_ex_id`, `user_id`, `exercise_name`, `status`, `approved_ex_id`, `created_at`, `updated_at`) VALUES
	(2, 6, '고블린 스쿼트', 'pending', NULL, '2025-09-09 07:01:16', '2025-09-09 07:01:16');

-- 테이블 hellchang.m_workout_exercise 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_workout_exercise` (
  `wx_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) NOT NULL,
  `ex_id` int(11) DEFAULT NULL,
  `order_no` int(11) NOT NULL DEFAULT 1,
  `weight` decimal(6,2) DEFAULT NULL COMMENT '무게(kg)',
  `reps` int(11) DEFAULT NULL COMMENT '반복 횟수',
  `sets` int(11) DEFAULT NULL COMMENT '세트 수',
  `note` varchar(255) DEFAULT NULL,
  `original_exercise_name` varchar(160) DEFAULT NULL COMMENT '사용자가 입력한 원본 운동명',
  `temp_ex_id` int(11) DEFAULT NULL COMMENT '임시 운동 ID',
  `is_temp` tinyint(1) DEFAULT 0 COMMENT '임시 운동 여부',
  PRIMARY KEY (`wx_id`),
  KEY `idx_wx_session` (`session_id`),
  KEY `idx_wx_ex` (`ex_id`),
  KEY `fk_wx_temp` (`temp_ex_id`),
  CONSTRAINT `fk_wx_ex` FOREIGN KEY (`ex_id`) REFERENCES `m_exercise` (`ex_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_wx_session` FOREIGN KEY (`session_id`) REFERENCES `m_workout_session` (`session_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wx_temp` FOREIGN KEY (`temp_ex_id`) REFERENCES `m_temp_exercise` (`temp_ex_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='세션-운동 목록';

-- 테이블 데이터 hellchang.m_workout_exercise:~51 rows (대략적) 내보내기
INSERT IGNORE INTO `m_workout_exercise` (`wx_id`, `session_id`, `ex_id`, `order_no`, `weight`, `reps`, `sets`, `note`, `original_exercise_name`, `temp_ex_id`, `is_temp`) VALUES
	(1, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0),
	(2, 1, 2, 2, NULL, NULL, NULL, NULL, NULL, NULL, 0),
	(3, 1, 3, 3, NULL, NULL, NULL, NULL, NULL, NULL, 0),
	(4, 2, 4, 1, 10.00, 15, 5, NULL, '덤벨 프레스', NULL, 0),
	(5, 2, 12, 2, 20.00, 10, 3, NULL, '바벨 스쿼트', NULL, 0),
	(6, 2, 3, 3, 5.00, 12, 4, NULL, '라잉 트라이셉스', NULL, 0),
	(7, 2, 12, 1, 10.00, 15, 5, NULL, '바벨 스쿼트', NULL, 0),
	(8, 2, 19, 2, 20.00, 10, 3, NULL, '데드 리프트', NULL, 0),
	(9, 4, 4, 1, 10.00, 15, 2, NULL, '덤벨 벤치 프레스', NULL, 0),
	(10, 4, 12, 2, 20.00, 10, 1, NULL, '바벨 스쿼트', NULL, 0),
	(11, 4, 3, 3, 5.00, 12, 8, NULL, '라잉 트라이셉스 익스텐션', NULL, 0),
	(49, 10, 6, 1, 20.00, 12, 5, NULL, '바벨 벤치 프레스', NULL, 0),
	(50, 10, 9, 2, 60.00, 10, 3, NULL, '딥스', NULL, 0),
	(51, 10, 53, 3, 30.00, 15, 4, NULL, '펙덱플라이', NULL, 0),
	(52, 10, 5, 4, 20.00, 12, 5, NULL, '인클라인 바벨 벤치 프레스', NULL, 0),
	(53, 10, 52, 5, 20.00, 15, 4, NULL, '버티컬 체스트', NULL, 0),
	(54, 10, 44, 6, 20.00, 15, 3, NULL, '케이블 익스텐션', NULL, 0),
	(95, 21, 56, 1, 20.00, 15, 5, NULL, '암 풀 다운', NULL, 0),
	(96, 21, 57, 2, 60.00, 12, 5, NULL, '어시스트 풀업', NULL, 0),
	(97, 21, 58, 3, 30.00, 15, 4, NULL, '랫 풀 다운', NULL, 0),
	(98, 21, 20, 4, 20.00, 15, 4, NULL, '바벨로우', NULL, 0),
	(99, 21, 59, 5, 35.00, 20, 4, NULL, '시티드 로우', NULL, 0),
	(100, 21, 60, 6, 20.00, 12, 4, NULL, '바벨컬', NULL, 0),
	(101, 21, 61, 7, 15.00, 15, 3, NULL, '인클라인 덤벨컬', NULL, 0),
	(130, 25, 14, 1, 20.00, 20, 5, NULL, '레그 익스텐션', NULL, 0),
	(131, 25, 63, 2, 20.00, 10, 4, NULL, '바벨 백 스쿼트', NULL, 0),
	(132, 25, 64, 3, 20.00, 10, 4, NULL, '파워 레그프레스', NULL, 0),
	(133, 25, 16, 4, 0.00, 10, 4, NULL, '런지', NULL, 0),
	(134, 25, 65, 5, 10.00, 20, 3, NULL, '아웃타이', NULL, 0),
	(135, 25, 67, 6, 10.00, 15, 3, NULL, '힙스러스트', NULL, 0),
	(136, 25, 68, 7, 10.00, 15, 3, NULL, '어덕션', NULL, 0),
	(137, 26, 19, 1, 0.00, 0, 0, NULL, '데드리프트', NULL, 0),
	(138, 26, 1, 2, 0.00, 0, 0, NULL, '벤치프레스', NULL, 0),
	(139, 26, 20, 3, 20.00, 12, 4, NULL, '바벨로우', NULL, 0),
	(140, 26, 5, 4, 20.00, 12, 4, NULL, '인클라인 바벨 프레스', NULL, 0),
	(141, 26, 59, 5, 35.00, 12, 4, NULL, '시티드 로우', NULL, 0),
	(142, 26, 52, 6, 30.00, 12, 4, NULL, '버티컬 체스트', NULL, 0),
	(143, 27, 28, 1, 20.00, 12, 4, NULL, '바벨 오버헤드 프레스', NULL, 0),
	(144, 27, 29, 2, 20.00, 20, 4, NULL, '덤벨 숄더 프레스', NULL, 0),
	(145, 27, 35, 3, 10.00, 12, 4, NULL, '바벨 업라이트 로우', NULL, 0),
	(146, 27, 30, 4, 10.00, 15, 5, NULL, '사이드 레터럴 레이즈', NULL, 0),
	(147, 27, 32, 5, 10.00, 15, 4, NULL, '리어 델트 플라이', NULL, 0),
	(148, 27, 62, 6, 10.00, 15, 5, NULL, '벤트 오버 레터럴 레이즈', NULL, 0),
	(149, 27, 33, 7, 10.00, 12, 3, NULL, '바벨 슈러그', NULL, 0),
	(150, 28, 14, 1, 20.00, 20, 5, NULL, '레그 익스텐션', NULL, 0),
	(151, 28, 63, 2, 20.00, 10, 4, NULL, '바벨 백 스쿼트', NULL, 0),
	(152, 28, 64, 3, 130.00, 10, 4, NULL, '파워 레그프레스', NULL, 0),
	(153, 28, 16, 4, 0.00, 10, 4, NULL, '런지', NULL, 0),
	(154, 28, 65, 5, 10.00, 20, 3, NULL, '아웃타이', NULL, 0),
	(155, 28, 67, 6, 10.00, 15, 3, NULL, '힙스러스트', NULL, 0),
	(156, 28, 68, 7, 10.00, 15, 3, NULL, '어덕션', NULL, 0);

-- 테이블 hellchang.m_workout_session 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_workout_session` (
  `session_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `workout_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `duration` decimal(6,2) DEFAULT NULL COMMENT '운동 시간(분)',
  `start_time` datetime DEFAULT NULL COMMENT '운동 시작시간',
  `end_time` datetime DEFAULT NULL COMMENT '운동 종료시간',
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='운동 세션(일자)';

-- 테이블 데이터 hellchang.m_workout_session:~9 rows (대략적) 내보내기
INSERT IGNORE INTO `m_workout_session` (`session_id`, `user_id`, `workout_date`, `note`, `duration`, `start_time`, `end_time`) VALUES
	(1, 1, '2025-08-27', '가슴/삼두', NULL, NULL, NULL),
	(2, 6, '2025-08-28', '오늘의 운동', NULL, NULL, NULL),
	(4, 6, '2025-08-28', '오늘의 운동', NULL, NULL, NULL),
	(10, 6, '2025-09-08', '오늘의 운동', NULL, '2025-09-09 18:00:41', NULL),
	(21, 6, '2025-09-09', '', NULL, '2025-09-09 20:18:35', '2025-09-09 21:19:04'),
	(25, 6, '2025-09-11', '', NULL, '2025-09-10 19:57:32', NULL),
	(26, 6, '2025-09-12', '', NULL, NULL, NULL),
	(27, 6, '2025-09-11', '', NULL, NULL, NULL),
	(28, 6, '2025-09-10', '', NULL, '2025-09-10 19:58:29', '2025-09-10 20:55:42');

-- 테이블 hellchang.m_workout_set 구조 내보내기
CREATE TABLE IF NOT EXISTS `m_workout_set` (
  `set_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `wx_id` bigint(20) NOT NULL,
  `set_no` int(11) NOT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `reps` int(11) DEFAULT NULL,
  `rir` tinyint(4) DEFAULT NULL COMMENT '남긴 반복(RIR) 옵션',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '세트 완료 시간',
  `rest_time` int(11) DEFAULT NULL COMMENT '휴식 시간(초)',
  `total_time` int(11) DEFAULT NULL COMMENT '총 운동 시간(초)',
  PRIMARY KEY (`set_id`),
  KEY `idx_ws_wx` (`wx_id`),
  CONSTRAINT `fk_ws_wx` FOREIGN KEY (`wx_id`) REFERENCES `m_workout_exercise` (`wx_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='세트 기록';

-- 테이블 데이터 hellchang.m_workout_set:~79 rows (대략적) 내보내기
INSERT IGNORE INTO `m_workout_set` (`set_id`, `wx_id`, `set_no`, `weight`, `reps`, `rir`, `completed_at`, `rest_time`, `total_time`) VALUES
	(43, 51, 1, 30.00, 15, NULL, '2025-09-08 10:09:37', 77, 378),
	(44, 51, 2, 30.00, 15, NULL, '2025-09-08 10:09:37', 109, 378),
	(45, 51, 3, 30.00, 15, NULL, '2025-09-08 10:09:37', 95, 378),
	(46, 51, 4, 30.00, 15, NULL, '2025-09-08 10:09:37', 97, 378),
	(52, 49, 1, 20.00, 12, NULL, '2025-09-08 10:22:09', 7, 7),
	(53, 49, 2, 20.00, 12, NULL, '2025-09-08 10:22:09', 0, 7),
	(54, 49, 3, 20.00, 12, NULL, '2025-09-08 10:22:09', 0, 7),
	(55, 49, 4, 20.00, 12, NULL, '2025-09-08 10:22:09', 0, 7),
	(56, 49, 5, 20.00, 12, NULL, '2025-09-08 10:22:09', 0, 7),
	(57, 52, 1, 20.00, 12, NULL, '2025-09-08 10:33:32', 111, 488),
	(58, 52, 2, 20.00, 12, NULL, '2025-09-08 10:33:32', 73, 488),
	(59, 52, 3, 20.00, 12, NULL, '2025-09-08 10:33:32', 110, 488),
	(60, 52, 4, 20.00, 12, NULL, '2025-09-08 10:33:32', 85, 488),
	(61, 52, 5, 20.00, 12, NULL, '2025-09-08 10:33:32', 109, 488),
	(62, 50, 1, 60.00, 10, NULL, '2025-09-08 10:40:09', 61, 269),
	(63, 50, 2, 60.00, 10, NULL, '2025-09-08 10:40:09', 107, 269),
	(64, 50, 3, 60.00, 10, NULL, '2025-09-08 10:40:09', 101, 269),
	(65, 53, 1, 20.00, 15, NULL, '2025-09-08 10:48:27', 132, 471),
	(66, 53, 2, 20.00, 15, NULL, '2025-09-08 10:48:27', 109, 471),
	(67, 53, 3, 20.00, 15, NULL, '2025-09-08 10:48:27', 105, 471),
	(68, 53, 4, 20.00, 15, NULL, '2025-09-08 10:48:27', 125, 471),
	(69, 54, 1, 20.00, 15, NULL, '2025-09-08 10:55:15', 163, 334),
	(70, 54, 2, 20.00, 15, NULL, '2025-09-08 10:55:15', 94, 334),
	(71, 54, 3, 20.00, 15, NULL, '2025-09-08 10:55:15', 77, 334),
	(80, 95, 1, 20.00, 15, NULL, '2025-09-09 11:27:07', 77, 481),
	(81, 95, 2, 20.00, 15, NULL, '2025-09-09 11:27:07', 83, 481),
	(82, 95, 3, 20.00, 15, NULL, '2025-09-09 11:27:07', 87, 481),
	(83, 95, 4, 20.00, 15, NULL, '2025-09-09 11:27:07', 119, 481),
	(84, 95, 5, 20.00, 15, NULL, '2025-09-09 11:27:07', 115, 481),
	(85, 97, 1, 30.00, 15, NULL, '2025-09-09 11:35:51', 78, 414),
	(86, 97, 2, 30.00, 15, NULL, '2025-09-09 11:35:51', 107, 414),
	(87, 97, 3, 30.00, 15, NULL, '2025-09-09 11:35:51', 105, 414),
	(88, 97, 4, 30.00, 15, NULL, '2025-09-09 11:35:51', 124, 414),
	(89, 96, 1, 60.00, 12, NULL, '2025-09-09 11:47:15', 61, 500),
	(90, 96, 2, 60.00, 12, NULL, '2025-09-09 11:47:15', 115, 500),
	(91, 96, 3, 60.00, 12, NULL, '2025-09-09 11:47:15', 109, 500),
	(92, 96, 4, 60.00, 12, NULL, '2025-09-09 11:47:15', 109, 500),
	(93, 96, 5, 60.00, 12, NULL, '2025-09-09 11:47:15', 106, 500),
	(94, 98, 1, 20.00, 15, NULL, '2025-09-09 11:54:18', 93, 400),
	(95, 98, 2, 20.00, 15, NULL, '2025-09-09 11:54:18', 123, 400),
	(96, 98, 3, 20.00, 15, NULL, '2025-09-09 11:54:18', 94, 400),
	(97, 98, 4, 20.00, 15, NULL, '2025-09-09 11:54:18', 90, 400),
	(98, 99, 1, 35.00, 20, NULL, '2025-09-09 12:03:48', 0, 372),
	(99, 99, 2, 35.00, 20, NULL, '2025-09-09 12:03:48', 110, 372),
	(100, 99, 3, 35.00, 20, NULL, '2025-09-09 12:03:48', 144, 372),
	(101, 99, 4, 35.00, 20, NULL, '2025-09-09 12:03:48', 118, 372),
	(102, 100, 1, 20.00, 12, NULL, '2025-09-09 12:11:06', 132, 406),
	(103, 100, 2, 20.00, 12, NULL, '2025-09-09 12:11:06', 68, 406),
	(104, 100, 3, 20.00, 12, NULL, '2025-09-09 12:11:06', 120, 406),
	(105, 100, 4, 20.00, 12, NULL, '2025-09-09 12:11:06', 86, 406),
	(106, 101, 1, 15.00, 15, NULL, '2025-09-09 12:18:40', 0, 197),
	(107, 101, 2, 15.00, 15, NULL, '2025-09-09 12:18:40', 101, 197),
	(108, 101, 3, 15.00, 15, NULL, '2025-09-09 12:18:40', 96, 197),
	(109, 150, 1, 20.00, 20, NULL, '2025-09-10 11:06:08', 78, 446),
	(110, 150, 2, 20.00, 20, NULL, '2025-09-10 11:06:08', 101, 446),
	(111, 150, 3, 20.00, 20, NULL, '2025-09-10 11:06:08', 93, 446),
	(112, 150, 4, 20.00, 20, NULL, '2025-09-10 11:06:08', 87, 446),
	(113, 150, 5, 20.00, 20, NULL, '2025-09-10 11:06:08', 87, 446),
	(114, 151, 1, 20.00, 10, NULL, '2025-09-10 11:15:00', 131, 519),
	(115, 151, 2, 20.00, 10, NULL, '2025-09-10 11:15:00', 108, 519),
	(116, 151, 3, 20.00, 10, NULL, '2025-09-10 11:15:00', 121, 519),
	(117, 151, 4, 20.00, 10, NULL, '2025-09-10 11:15:00', 159, 519),
	(118, 152, 1, 20.00, 10, NULL, '2025-09-10 11:23:09', 146, 475),
	(119, 152, 2, 20.00, 10, NULL, '2025-09-10 11:23:09', 115, 475),
	(120, 152, 3, 20.00, 10, NULL, '2025-09-10 11:23:09', 108, 475),
	(121, 152, 4, 20.00, 10, NULL, '2025-09-10 11:23:09', 106, 475),
	(122, 153, 1, 0.00, 10, NULL, '2025-09-10 11:33:22', 109, 567),
	(123, 153, 2, 0.00, 10, NULL, '2025-09-10 11:33:22', 128, 567),
	(124, 153, 3, 0.00, 10, NULL, '2025-09-10 11:33:22', 201, 567),
	(125, 153, 4, 0.00, 10, NULL, '2025-09-10 11:33:22', 129, 567),
	(126, 154, 1, 10.00, 20, NULL, '2025-09-10 11:40:25', 82, 304),
	(127, 154, 2, 10.00, 20, NULL, '2025-09-10 11:40:25', 121, 304),
	(128, 154, 3, 10.00, 20, NULL, '2025-09-10 11:40:25', 101, 304),
	(129, 155, 1, 10.00, 15, NULL, '2025-09-10 11:47:24', 110, 398),
	(130, 155, 2, 10.00, 15, NULL, '2025-09-10 11:47:24', 123, 398),
	(131, 155, 3, 10.00, 15, NULL, '2025-09-10 11:47:24', 165, 398),
	(132, 156, 1, 10.00, 15, NULL, '2025-09-10 11:55:29', 141, 455),
	(133, 156, 2, 10.00, 15, NULL, '2025-09-10 11:55:29', 155, 455),
	(134, 156, 3, 10.00, 15, NULL, '2025-09-10 11:55:29', 159, 455);

-- 테이블 hellchang.sessions 구조 내보내기
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `session_token` (`session_token`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `idx_sessions_token` (`session_token`) USING BTREE,
  KEY `idx_sessions_expires` (`expires_at`) USING BTREE,
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.sessions:~15 rows (대략적) 내보내기
INSERT IGNORE INTO `sessions` (`id`, `user_id`, `session_token`, `expires_at`, `created_at`) VALUES
	(42, 6, 'b91c0d9e2d54eb5a18ee81276ac5d6d8cf31cba70e0b2066973626f02d69b71a', '2025-09-26 15:52:52', '2025-08-28 00:52:52'),
	(43, 6, 'e8ff4cb341cf403bd2b0be825adde845fad1d33d66652397c20451c0e246050c', '2025-09-26 16:22:21', '2025-08-28 01:22:21'),
	(44, 6, '0ba6a60ca12eb7a3e836fb1ff958683ae864d917298d4afada771b351ce6eb77', '2025-09-26 17:02:57', '2025-08-28 02:02:57'),
	(45, 6, '7828e204d2fdabb08969409589d297717e9231b49cd908ba1241082dca764159', '2025-09-28 20:33:24', '2025-08-30 05:33:24'),
	(46, 6, '8e15de7c54240c4158dfdf8e4a5af7e38829cfe495aef503d88af216cda66dae', '2025-09-29 00:02:26', '2025-08-30 09:02:26'),
	(47, 6, 'ed0941eb9498b8bc33481beb26b45017d8827e2bfecf658c3fad81fe5998a61c', '2025-10-02 01:27:34', '2025-09-02 10:27:34'),
	(48, 6, '393d1d0ec449b27502fd9e28ad7f2145e962e5b3db7ff52cad36763774f7814a', '2025-10-02 16:37:05', '2025-09-03 01:37:05'),
	(49, 6, 'fb4cd4f41fe638d1fddfafb20544616c0176c581d4182372ed644b155fd51f94', '2025-10-03 20:45:04', '2025-09-04 05:45:04'),
	(50, 6, '89a229ae90847eb7b1a8f2f2efe63204c0c6a2ebf0d561ea2a76961de54aed61', '2025-10-03 20:55:22', '2025-09-04 05:55:22'),
	(51, 6, '6c67fb79ee014b8f844db1721d956e6261e360df0bfccdb4fe891969490fba4a', '2025-10-06 22:29:46', '2025-09-07 07:29:46'),
	(52, 6, '227955d740edfd0aa068fb71fca40714884e48ad1ff486d9ee81e4afd7ef8ea5', '2025-10-08 00:46:59', '2025-09-08 09:46:59'),
	(53, 6, '414c332296d86f3cdb9c7580487d6d47465b443c4909319db13fd04c700ad174', '2025-10-08 17:47:01', '2025-09-09 02:47:01'),
	(54, 6, '563dac7e11acbc7996f63b4a7f0968d35ddc7ee92de92d9f1c270e412cc25382', '2025-10-09 21:28:45', '2025-09-10 06:28:45'),
	(55, 6, 'e0c72578924302ffe854cfaef4590e7aaf4661f1702e86e686de60167efc835e', '2025-10-09 23:07:42', '2025-09-10 08:07:42'),
	(56, 6, '158bd59eb871a7d47dcbc4dbd2254cbbf020dc351da38552282ee9ecb8879903', '2025-10-10 20:17:26', '2025-09-11 05:17:26');

-- 테이블 hellchang.trainer_assessments 구조 내보내기
CREATE TABLE IF NOT EXISTS `trainer_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `assessment_date` date NOT NULL,
  `category` enum('strength','endurance','flexibility','body_composition','overall') DEFAULT 'overall',
  `score` decimal(3,1) DEFAULT NULL CHECK (`score` >= 0 and `score` <= 10),
  `notes` text DEFAULT NULL,
  `next_goal` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `idx_trainer_assessments_trainer_member` (`trainer_id`,`member_id`),
  CONSTRAINT `trainer_assessments_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_assessments_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.trainer_assessments:~0 rows (대략적) 내보내기

-- 테이블 hellchang.trainer_comments 구조 내보내기
CREATE TABLE IF NOT EXISTS `trainer_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `workout_session_id` int(11) DEFAULT NULL,
  `comment_type` enum('general','workout_feedback','progress_note','goal_setting') DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_private` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `idx_trainer_comments_trainer_member` (`trainer_id`,`member_id`),
  CONSTRAINT `trainer_comments_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_comments_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.trainer_comments:~0 rows (대략적) 내보내기

-- 테이블 hellchang.trainer_permissions 구조 내보내기
CREATE TABLE IF NOT EXISTS `trainer_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `can_view_workouts` tinyint(1) DEFAULT 1,
  `can_view_body_data` tinyint(1) DEFAULT 0,
  `can_create_schedules` tinyint(1) DEFAULT 1,
  `can_add_comments` tinyint(1) DEFAULT 1,
  `can_assess` tinyint(1) DEFAULT 1,
  `can_edit_goals` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permission` (`trainer_id`,`member_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `trainer_permissions_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_permissions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.trainer_permissions:~4 rows (대략적) 내보내기
INSERT IGNORE INTO `trainer_permissions` (`id`, `trainer_id`, `member_id`, `can_view_workouts`, `can_view_body_data`, `can_create_schedules`, `can_add_comments`, `can_assess`, `can_edit_goals`, `created_at`, `updated_at`) VALUES
	(1, 8, 7, 1, 0, 1, 1, 1, 0, '2025-08-30 17:32:37', '2025-08-30 17:32:37'),
	(2, 9, 10, 1, 0, 1, 1, 1, 0, '2025-08-30 17:32:37', '2025-08-30 17:32:37'),
	(6, 8, 6, 1, 0, 1, 1, 1, 0, '2025-08-30 18:06:00', '2025-08-30 18:06:00'),
	(7, 16, 6, 1, 0, 1, 1, 1, 0, '2025-08-30 18:06:12', '2025-08-30 18:06:12');

-- 테이블 hellchang.trainer_relationships 구조 내보내기
CREATE TABLE IF NOT EXISTS `trainer_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','terminated') DEFAULT 'pending',
  `request_date` datetime DEFAULT current_timestamp(),
  `approval_date` datetime DEFAULT NULL,
  `termination_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relationship` (`trainer_id`,`member_id`),
  KEY `idx_trainer_relationships_trainer` (`trainer_id`),
  KEY `idx_trainer_relationships_member` (`member_id`),
  KEY `idx_trainer_relationships_status` (`status`),
  CONSTRAINT `trainer_relationships_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_relationships_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.trainer_relationships:~4 rows (대략적) 내보내기
INSERT IGNORE INTO `trainer_relationships` (`id`, `trainer_id`, `member_id`, `status`, `request_date`, `approval_date`, `termination_date`, `created_at`, `updated_at`) VALUES
	(1, 8, 7, 'approved', '2025-08-30 17:32:37', '2025-08-30 17:32:37', NULL, '2025-08-30 17:32:37', '2025-08-30 17:32:37'),
	(2, 9, 10, 'approved', '2025-08-30 17:32:37', '2025-08-30 17:32:37', NULL, '2025-08-30 17:32:37', '2025-08-30 17:32:37'),
	(6, 8, 6, 'pending', '2025-08-30 18:06:00', NULL, NULL, '2025-08-30 18:06:00', '2025-08-30 18:06:00'),
	(7, 16, 6, 'approved', '2025-08-30 18:06:12', '2025-09-09 11:21:30', NULL, '2025-08-30 18:06:12', '2025-09-09 11:21:30');

-- 테이블 hellchang.trainer_schedules 구조 내보내기
CREATE TABLE IF NOT EXISTS `trainer_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `activity_type` enum('workout','consultation','assessment','other') DEFAULT 'workout',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `idx_trainer_schedules_date` (`schedule_date`),
  KEY `idx_trainer_schedules_trainer_member` (`trainer_id`,`member_id`),
  CONSTRAINT `trainer_schedules_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trainer_schedules_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.trainer_schedules:~0 rows (대략적) 내보내기

-- 테이블 hellchang.users 구조 내보내기
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kakao_id` bigint(20) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `password` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  UNIQUE KEY `kakao_id` (`kakao_id`) USING BTREE,
  KEY `idx_users_kakao_id` (`kakao_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 테이블 데이터 hellchang.users:~12 rows (대략적) 내보내기
INSERT IGNORE INTO `users` (`id`, `kakao_id`, `username`, `email`, `profile_image`, `created_at`, `updated_at`, `is_active`, `password`) VALUES
	(6, 4351964716, '라그리마', 'kakao_4351964716@hellchang.com', 'http://k.kakaocdn.net/dn/cAmkWq/btsyPBsH7bM/MZvR2kmF4RaQGOPrEEyfp1/img_640x640.jpg', '2025-08-28 00:52:05', '2025-09-11 05:17:26', 1, NULL),
	(7, 1000000001, '김태희', 'kimtaehee@hellchang.com', 'https://example.com/profiles/kimtaehee.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(8, 1000000002, '현빈', 'hyunbin@hellchang.com', 'https://example.com/profiles/hyunbin.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(9, 1000000003, '손예진', 'sonyejin@hellchang.com', 'https://example.com/profiles/sonyejin.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(10, 1000000004, '이병헌', 'leebyeongheon@hellchang.com', 'https://example.com/profiles/leebyeongheon.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(11, 1000000005, '아이유', 'iu@hellchang.com', 'https://example.com/profiles/iu.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(12, 1000000006, '방탄소년단', 'bts@hellchang.com', 'https://example.com/profiles/bts.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(13, 1000000007, '블랙핑크', 'blackpink@hellchang.com', 'https://example.com/profiles/blackpink.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(14, 1000000008, '손흥민', 'sonheungmin@hellchang.com', 'https://example.com/profiles/sonheungmin.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(15, 1000000009, '김연아', 'kimyuna@hellchang.com', 'https://example.com/profiles/kimyuna.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL),
	(16, 1000000010, '피지컬갤러리', 'physicalgallery@hellchang.com', 'https://example.com/profiles/physicalgallery.jpg', '2025-08-30 08:32:37', '2025-08-30 08:32:37', 1, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

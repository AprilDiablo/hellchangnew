-- --------------------------------------------------------
-- 호스트:                          lokkio.co.kr
-- 서버 버전:                        10.6.17-MariaDB-log - MariaDB Server
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

-- 테이블 lokkio20251001.admins 구조 내보내기
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `admin_id` varchar(50) NOT NULL COMMENT '관리자 아이디',
  `password` varchar(255) NOT NULL COMMENT '패스워드',
  `name` varchar(100) NOT NULL COMMENT '이름',
  `authority` enum('super','admin','manager') DEFAULT 'manager' COMMENT '권한',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용유무',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id` (`admin_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_use_yn` (`use_yn`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='관리자 테이블';

-- 테이블 데이터 lokkio20251001.admins:~1 rows (대략적) 내보내기
INSERT IGNORE INTO `admins` (`id`, `admin_id`, `password`, `name`, `authority`, `use_yn`, `created_at`, `updated_at`) VALUES
	(1, 'admin', 'ac9689e2272427085e35b9d3e3e8bed88cb3434828b43b86fc0596cad4c6e270', '시스템관리자', 'super', 'Y', '2025-10-28 10:43:20', '2025-10-28 10:57:15');

-- 테이블 lokkio20251001.centers 구조 내보내기
CREATE TABLE IF NOT EXISTS `centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `name` varchar(100) NOT NULL COMMENT '센터명',
  `address` varchar(255) DEFAULT NULL COMMENT '주소',
  `phone` varchar(20) DEFAULT NULL COMMENT '연락처',
  `manager` varchar(100) DEFAULT NULL COMMENT '담당자',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용유무',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_use_yn` (`use_yn`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='센터 테이블';

-- 테이블 데이터 lokkio20251001.centers:~6 rows (대략적) 내보내기
INSERT IGNORE INTO `centers` (`id`, `name`, `address`, `phone`, `manager`, `use_yn`, `created_at`, `updated_at`) VALUES
	(1, '강남센터', '서울시 강남구 테헤란로 123', '02-1234-5678', '김철수', 'Y', '2025-10-28 11:36:40', '2025-10-28 11:36:40'),
	(2, '서초센터', '서울시 서초구 서초대로 456', '02-2345-6789', '이영희', 'Y', '2025-10-28 11:36:40', '2025-10-28 11:36:40'),
	(3, '송파센터', '서울시 송파구 올림픽로 789', '02-3456-7890', '박민수', 'N', '2025-10-28 11:36:40', '2025-10-28 11:46:20'),
	(4, '강동센터', '서울시 강동구 천호대로 321', '02-4567-8901', '최지영', 'Y', '2025-10-28 11:36:40', '2025-10-28 11:36:40'),
	(5, '광진센터', '서울시 광진구 능동로 654', '02-5678-9012', '정대현', 'Y', '2025-10-28 11:36:40', '2025-10-28 11:36:40'),
	(6, '대구센터', '주소주소1', '연락처1', '담당자1', 'Y', '2025-10-28 11:45:59', '2025-10-28 11:46:14');

-- 테이블 lokkio20251001.gamejoin 구조 내보내기
CREATE TABLE IF NOT EXISTS `gamejoin` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `game_id` int(11) NOT NULL COMMENT '게임ID',
  `user_id` int(11) NOT NULL COMMENT '사용자ID',
  `joined_at` datetime DEFAULT current_timestamp() COMMENT '참가일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game_user` (`game_id`,`user_id`),
  KEY `idx_game_id` (`game_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='게임 참가자';

-- 테이블 데이터 lokkio20251001.gamejoin:~40 rows (대략적) 내보내기
INSERT IGNORE INTO `gamejoin` (`id`, `game_id`, `user_id`, `joined_at`) VALUES
	(1, 3, 8, '2025-11-03 11:09:38'),
	(2, 3, 3, '2025-11-03 11:09:38'),
	(3, 3, 1, '2025-11-03 11:09:38'),
	(4, 3, 5, '2025-11-03 11:09:38'),
	(5, 3, 9, '2025-11-03 11:09:38'),
	(6, 3, 4, '2025-11-03 11:09:38'),
	(7, 3, 11, '2025-11-03 11:09:38'),
	(8, 3, 7, '2025-11-03 11:09:38'),
	(9, 3, 10, '2025-11-03 11:09:38'),
	(10, 3, 6, '2025-11-03 11:09:38'),
	(11, 3, 2, '2025-11-03 11:09:38'),
	(73, 13, 13, '2025-11-03 15:46:12'),
	(74, 13, 14, '2025-11-03 15:46:12'),
	(75, 13, 15, '2025-11-03 15:46:12'),
	(76, 13, 16, '2025-11-03 15:46:12'),
	(77, 13, 17, '2025-11-03 15:46:12'),
	(78, 13, 18, '2025-11-03 15:46:12'),
	(79, 13, 19, '2025-11-03 15:46:12'),
	(80, 13, 20, '2025-11-03 15:46:12'),
	(81, 13, 21, '2025-11-03 15:46:12'),
	(82, 13, 22, '2025-11-03 15:46:12'),
	(83, 13, 23, '2025-11-03 15:46:12'),
	(84, 14, 24, '2025-11-03 15:48:52'),
	(85, 14, 25, '2025-11-03 15:48:52'),
	(87, 14, 27, '2025-11-03 15:48:52'),
	(88, 14, 28, '2025-11-03 15:48:52'),
	(89, 14, 29, '2025-11-03 15:48:52'),
	(90, 14, 30, '2025-11-03 15:48:52'),
	(92, 14, 32, '2025-11-03 15:48:52'),
	(93, 14, 33, '2025-11-03 15:48:52'),
	(94, 14, 34, '2025-11-03 15:48:52'),
	(95, 15, 35, '2025-11-03 15:50:58'),
	(96, 15, 36, '2025-11-03 15:50:58'),
	(97, 15, 37, '2025-11-03 15:50:58'),
	(98, 15, 38, '2025-11-03 15:50:58'),
	(99, 15, 39, '2025-11-03 15:50:58'),
	(100, 15, 40, '2025-11-03 15:50:58'),
	(101, 15, 41, '2025-11-03 15:50:58'),
	(102, 15, 42, '2025-11-03 15:50:58'),
	(103, 15, 43, '2025-11-03 15:50:58');

-- 테이블 lokkio20251001.games 구조 내보내기
CREATE TABLE IF NOT EXISTS `games` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `name` varchar(255) NOT NULL COMMENT '게임명',
  `round` int(11) NOT NULL COMMENT '회차',
  `start_date` datetime NOT NULL COMMENT '시작일',
  `end_date` datetime NOT NULL COMMENT '종료일',
  `status` enum('진행','마감') DEFAULT '진행' COMMENT '진행여부',
  `note` text DEFAULT NULL COMMENT '비고',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용유무',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_use_yn` (`use_yn`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='게임 테이블';

-- 테이블 데이터 lokkio20251001.games:~4 rows (대략적) 내보내기
INSERT IGNORE INTO `games` (`id`, `name`, `round`, `start_date`, `end_date`, `status`, `note`, `use_yn`, `created_at`, `updated_at`) VALUES
	(3, '게임', 1, '2025-11-01 16:22:00', '2025-11-30 16:22:00', '진행', '비고', 'Y', '2025-10-31 16:24:53', '2025-11-03 14:46:40'),
	(13, '게임', 2, '2025-12-01 16:22:00', '2026-01-01 16:22:00', '진행', '', 'Y', '2025-11-03 15:46:12', '2025-11-03 15:46:12'),
	(14, '게임', 3, '2026-01-02 16:22:00', '2026-02-02 16:22:00', '진행', '', 'Y', '2025-11-03 15:48:52', '2025-11-03 15:48:52'),
	(15, '게임', 4, '2026-02-03 16:22:00', '2026-03-03 16:22:00', '진행', '', 'Y', '2025-11-03 15:50:58', '2025-11-03 15:50:58');

-- 테이블 lokkio20251001.notices 구조 내보내기
CREATE TABLE IF NOT EXISTS `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `title` varchar(255) NOT NULL COMMENT '제목',
  `content` text NOT NULL COMMENT '내용',
  `is_popup` char(1) DEFAULT 'N' COMMENT '팝업 표시 여부 (Y/N)',
  `popup_width` int(11) DEFAULT 800 COMMENT '팝업 너비',
  `popup_height` int(11) DEFAULT 600 COMMENT '팝업 높이',
  `start_date` datetime DEFAULT NULL COMMENT '시작일',
  `end_date` datetime DEFAULT NULL COMMENT '종료일',
  `views` int(11) DEFAULT 0 COMMENT '조회수',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용유무',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  KEY `idx_use_yn` (`use_yn`),
  KEY `idx_is_popup` (`is_popup`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='공지사항 테이블';

-- 테이블 데이터 lokkio20251001.notices:~3 rows (대략적) 내보내기
INSERT IGNORE INTO `notices` (`id`, `title`, `content`, `is_popup`, `popup_width`, `popup_height`, `start_date`, `end_date`, `views`, `use_yn`, `created_at`, `updated_at`) VALUES
	(1, 'ㅅㄷㄴㅅ', '<p>ㅅㄷㄴㅅ</p>', 'N', 800, 600, NULL, NULL, 0, 'Y', '2025-10-28 14:05:19', '2025-10-28 14:05:19'),
	(2, 'ㅅㄷㄴㅅ', '<p>ㅅㄷㄴㅅ</p>', 'Y', 800, 600, NULL, NULL, 0, 'Y', '2025-10-28 14:05:29', '2025-10-28 14:05:29'),
	(3, '이미지 업로드 테스트', '<p><img style="width: 376px;" src="http://www.lokkio.co.kr//uploads/notices/6900515998227_1761628505.png"></p><p><br></p><p>테스트 테스트</p>', 'Y', 800, 600, NULL, NULL, 0, 'Y', '2025-10-28 14:15:29', '2025-10-28 14:15:29');

-- 테이블 lokkio20251001.orders 구조 내보내기
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `admin_id` int(11) NOT NULL COMMENT '주문 처리 관리자ID',
  `game_id` int(11) NOT NULL COMMENT '게임ID',
  `user_id` int(11) NOT NULL COMMENT '주문 대상 회원ID',
  `order_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT '주문일시',
  `total_price` decimal(12,0) NOT NULL DEFAULT 0 COMMENT '총액',
  `status` enum('주문','취소') NOT NULL DEFAULT '주문' COMMENT '상태',
  `note` varchar(255) DEFAULT NULL COMMENT '비고',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  KEY `fk_orders_admin` (`admin_id`),
  KEY `fk_orders_user` (`user_id`),
  KEY `idx_game_user` (`game_id`,`user_id`),
  KEY `idx_order_date` (`order_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_orders_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `fk_orders_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='주문';

-- 테이블 데이터 lokkio20251001.orders:~1 rows (대략적) 내보내기
INSERT IGNORE INTO `orders` (`id`, `admin_id`, `game_id`, `user_id`, `order_date`, `total_price`, `status`, `note`, `created_at`, `updated_at`) VALUES
	(1, 1, 3, 1, '2025-11-04 10:37:19', 220000, '주문', NULL, '2025-11-04 10:37:19', '2025-11-04 10:37:19');

-- 테이블 lokkio20251001.order_items 구조 내보내기
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `order_id` int(11) NOT NULL COMMENT '주문ID',
  `product_id` int(11) NOT NULL COMMENT '상품ID',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '수량',
  `unit_price` decimal(12,0) NOT NULL DEFAULT 0 COMMENT '단가(주문 시점)',
  `line_total` decimal(12,0) NOT NULL DEFAULT 0 COMMENT '라인합계',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '생성일시',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='주문 상품';

-- 테이블 데이터 lokkio20251001.order_items:~3 rows (대략적) 내보내기
INSERT IGNORE INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `line_total`, `created_at`) VALUES
	(1, 1, 14, 1, 100000, 100000, '2025-11-04 10:37:19'),
	(2, 1, 12, 1, 100000, 100000, '2025-11-04 10:37:19'),
	(3, 1, 3, 1, 20000, 20000, '2025-11-04 10:37:19');

-- 테이블 lokkio20251001.products 구조 내보내기
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `name` varchar(255) NOT NULL COMMENT '상품명',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용유무',
  `category_l_id` int(11) NOT NULL COMMENT '대분류 ID',
  `category_s_id` int(11) DEFAULT NULL COMMENT '소분류 ID',
  `spec` varchar(255) DEFAULT NULL COMMENT '규격',
  `unit` varchar(50) DEFAULT NULL COMMENT '단위',
  `price` decimal(12,0) NOT NULL DEFAULT 0 COMMENT '단가',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  KEY `fk_products_cat_s` (`category_s_id`),
  KEY `idx_use_yn` (`use_yn`),
  KEY `idx_category` (`category_l_id`,`category_s_id`),
  KEY `idx_name` (`name`),
  CONSTRAINT `fk_products_cat_l` FOREIGN KEY (`category_l_id`) REFERENCES `product_categories` (`id`),
  CONSTRAINT `fk_products_cat_s` FOREIGN KEY (`category_s_id`) REFERENCES `product_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상품';

-- 테이블 데이터 lokkio20251001.products:~15 rows (대략적) 내보내기
INSERT IGNORE INTO `products` (`id`, `name`, `use_yn`, `category_l_id`, `category_s_id`, `spec`, `unit`, `price`, `created_at`, `updated_at`) VALUES
	(1, '오리온 초코파이(12개입)', 'Y', 1, 3, '12개입', '박스', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(2, '농심 새우깡 90g', 'Y', 1, 3, '90g', '개', 20000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(3, '해태 허니버터칩 60g', 'Y', 1, 3, '60g', '개', 20000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(4, '오레오 오리지널 133g', 'Y', 1, 3, '133g', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(5, '롯데 빼빼로(초코) 54g', 'Y', 1, 3, '54g', '개', 20000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(6, '코카콜라 500ml', 'Y', 1, 4, '500ml', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(7, '펩시콜라 500ml', 'Y', 1, 4, '500ml', '개', 20000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(8, '제주삼다수 2L', 'Y', 1, 4, '2L', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(9, '칸타타 라떼 275ml 캔', 'Y', 1, 4, '275ml', '개', 20000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(10, '트로피카나 오렌지 355ml', 'Y', 1, 4, '355ml', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(11, '페브리즈 섬유탈취제 370ml', 'Y', 2, 5, '370ml', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(12, '홈스타 곰팡이제거제 500ml', 'Y', 2, 5, '500ml', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(13, '스카치브라이트 수세미 3개입', 'Y', 2, 5, '3개입', '세트', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03'),
	(14, '깨끗한나라 두루마리휴지 30롤', 'Y', 2, 6, '30롤', '팩', 100000, '2025-11-03 18:42:01', '2025-11-03 18:44:49'),
	(15, '도브 바디워시 900g', 'Y', 2, 6, '900g', '개', 100000, '2025-11-03 18:42:01', '2025-11-03 18:45:03');

-- 테이블 lokkio20251001.product_categories 구조 내보내기
CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `parent_id` int(11) DEFAULT NULL COMMENT '상위 카테고리 ID (NULL=대분류)',
  `depth` tinyint(4) NOT NULL COMMENT '레벨(1=대분류, 2=소분류)',
  `name` varchar(100) NOT NULL COMMENT '카테고리명',
  `use_yn` char(1) NOT NULL DEFAULT 'Y' COMMENT '사용유무',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '정렬순서',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_name` (`parent_id`,`name`),
  CONSTRAINT `fk_category_parent` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상품 카테고리';

-- 테이블 데이터 lokkio20251001.product_categories:~6 rows (대략적) 내보내기
INSERT IGNORE INTO `product_categories` (`id`, `parent_id`, `depth`, `name`, `use_yn`, `sort`, `created_at`, `updated_at`) VALUES
	(1, NULL, 1, '식품', 'Y', 10, '2025-11-03 18:42:01', '2025-11-03 18:42:01'),
	(2, NULL, 1, '생활', 'Y', 20, '2025-11-03 18:42:01', '2025-11-03 18:42:01'),
	(3, 1, 2, '과자', 'Y', 10, '2025-11-03 18:42:01', '2025-11-03 18:42:01'),
	(4, 1, 2, '음료', 'Y', 20, '2025-11-03 18:42:01', '2025-11-03 18:42:01'),
	(5, 2, 2, '청소용품', 'Y', 10, '2025-11-03 18:42:01', '2025-11-03 18:42:01'),
	(6, 2, 2, '욕실용품', 'Y', 20, '2025-11-03 18:42:01', '2025-11-03 18:42:01');

-- 테이블 lokkio20251001.product_images 구조 내보내기
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `product_id` int(11) NOT NULL COMMENT '상품ID',
  `image_url` varchar(500) NOT NULL COMMENT '이미지 경로/URL',
  `is_primary` char(1) NOT NULL DEFAULT 'N' COMMENT '대표이미지 여부(Y/N)',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '정렬순서',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상품 이미지';

-- 테이블 데이터 lokkio20251001.product_images:~1 rows (대략적) 내보내기
INSERT IGNORE INTO `product_images` (`id`, `product_id`, `image_url`, `is_primary`, `sort`, `created_at`) VALUES
	(1, 15, '/adm/uploads/products/pimg_6909537bf34a5_1762218875.png', 'N', 100, '2025-11-04 10:14:35');

-- 테이블 lokkio20251001.users 구조 내보내기
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '고유번호',
  `user_id` varchar(50) NOT NULL COMMENT '아이디',
  `name` varchar(100) NOT NULL COMMENT '이름',
  `password` varchar(255) NOT NULL COMMENT '패스워드',
  `price` decimal(10,0) DEFAULT 220000 COMMENT '몸값',
  `phone` varchar(20) DEFAULT NULL COMMENT '연락처',
  `address` varchar(255) DEFAULT NULL COMMENT '주소',
  `recommender` varchar(50) DEFAULT NULL COMMENT '추천인',
  `bank_name` varchar(50) DEFAULT NULL COMMENT '은행',
  `account_number` varchar(50) DEFAULT NULL COMMENT '계좌번호',
  `account_holder` varchar(100) DEFAULT NULL COMMENT '예금주',
  `center` varchar(100) DEFAULT NULL COMMENT '소속센터',
  `reg_date` datetime DEFAULT current_timestamp() COMMENT '등록일',
  `note` text DEFAULT NULL COMMENT '비고',
  `use_yn` char(1) DEFAULT 'Y' COMMENT '사용유무 (Y:사용, N:미사용)',
  `user_type` enum('회원','아바타') DEFAULT '회원' COMMENT '회원 종류',
  `created_at` datetime DEFAULT current_timestamp() COMMENT '생성일시',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '수정일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_use_yn` (`use_yn`),
  KEY `idx_user_type` (`user_type`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='사용자 테이블';

-- 테이블 데이터 lokkio20251001.users:~43 rows (대략적) 내보내기
INSERT IGNORE INTO `users` (`id`, `user_id`, `name`, `password`, `price`, `phone`, `address`, `recommender`, `bank_name`, `account_number`, `account_holder`, `center`, `reg_date`, `note`, `use_yn`, `user_type`, `created_at`, `updated_at`) VALUES
	(1, 'lokkio1', '로끼오1', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', 220000, '010-1111-2222', '주소주소', '추천인', '은행', '계좌번호', '예금주', '강남센터', '2025-10-28 11:21:57', '비고비고', 'Y', '회원', '2025-10-28 11:21:57', '2025-10-28 11:37:00'),
	(2, 'user001', '홍길동', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-1234-5678', '서울시 강남구 역삼로 123', '김추천', '신한은행', '110-123-456789', '홍길동', '강남센터', '2025-10-28 12:00:55', '정기 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(3, 'user002', '김영희', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-2345-6789', '서울시 서초구 서초대로 456', '이소개', '국민은행', '123-456-789012', '김영희', '서초센터', '2025-10-28 12:00:55', '신규 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(4, 'user003', '이철수', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-3456-7890', '서울시 송파구 올림픽로 789', '박지수', '우리은행', '1002-123-456789', '이철수', '송파센터', '2025-10-28 12:00:55', 'VIP 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(5, 'user004', '박민지', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-4567-8901', '서울시 강동구 천호대로 321', '최동수', '하나은행', '110-456-789012', '박민지', '강동센터', '2025-10-28 12:00:55', '', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(6, 'user005', '최수진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-5678-9012', '서울시 광진구 능동로 654', '정미라', '기업은행', '025-3456-7890', '최수진', '광진센터', '2025-10-28 12:00:55', '플래티넘 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(7, 'user006', '정대호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-6789-0123', '서울시 강남구 테헤란로 234', '손영수', '신한은행', '110-789-012345', '정대호', '강남센터', '2025-10-28 12:00:55', '', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(8, 'user007', '강미영', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-7890-1234', '서울시 서초구 강남대로 567', '한지영', '국민은행', '123-789-012345', '강미영', '서초센터', '2025-10-28 12:00:55', '이벤트 당첨자', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(9, 'user008', '윤성민', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-8901-2345', '서울시 송파구 문정로 890', '강태훈', '우리은행', '1002-456-789012', '윤성민', '송파센터', '2025-10-28 12:00:55', '', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(10, 'user009', '조혜진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-9012-3456', '서울시 강동구 성내천로 432', '류정호', '하나은행', '110-567-890123', '조혜진', '강동센터', '2025-10-28 12:00:55', '골드 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(11, 'user010', '임준호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-0123-4567', '서울시 광진구 자양로 765', '서영희', '기업은행', '025-5678-9012', '임준호', '광진센터', '2025-10-28 12:00:55', '단골 회원', 'Y', '회원', '2025-10-28 12:00:55', '2025-10-28 12:00:55'),
	(13, 'lokkio1_0002', '로끼오1', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', 220000, '010-1111-2222', '주소주소', '추천인', '은행', '계좌번호', '예금주', '강남센터', '2025-11-03 15:46:12', '비고비고', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(14, 'user001_0002', '홍길동', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-1234-5678', '서울시 강남구 역삼로 123', '김추천', '신한은행', '110-123-456789', '홍길동', '강남센터', '2025-11-03 15:46:12', '정기 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(15, 'user002_0002', '김영희', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-2345-6789', '서울시 서초구 서초대로 456', '이소개', '국민은행', '123-456-789012', '김영희', '서초센터', '2025-11-03 15:46:12', '신규 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(16, 'user003_0002', '이철수', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-3456-7890', '서울시 송파구 올림픽로 789', '박지수', '우리은행', '1002-123-456789', '이철수', '송파센터', '2025-11-03 15:46:12', 'VIP 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(17, 'user004_0002', '박민지', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-4567-8901', '서울시 강동구 천호대로 321', '최동수', '하나은행', '110-456-789012', '박민지', '강동센터', '2025-11-03 15:46:12', '', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(18, 'user005_0002', '최수진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-5678-9012', '서울시 광진구 능동로 654', '정미라', '기업은행', '025-3456-7890', '최수진', '광진센터', '2025-11-03 15:46:12', '플래티넘 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(19, 'user006_0002', '정대호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-6789-0123', '서울시 강남구 테헤란로 234', '손영수', '신한은행', '110-789-012345', '정대호', '강남센터', '2025-11-03 15:46:12', '', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(20, 'user007_0002', '강미영', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-7890-1234', '서울시 서초구 강남대로 567', '한지영', '국민은행', '123-789-012345', '강미영', '서초센터', '2025-11-03 15:46:12', '이벤트 당첨자', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(21, 'user008_0002', '윤성민', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-8901-2345', '서울시 송파구 문정로 890', '강태훈', '우리은행', '1002-456-789012', '윤성민', '송파센터', '2025-11-03 15:46:12', '', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(22, 'user009_0002', '조혜진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-9012-3456', '서울시 강동구 성내천로 432', '류정호', '하나은행', '110-567-890123', '조혜진', '강동센터', '2025-11-03 15:46:12', '골드 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(23, 'user010_0002', '임준호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-0123-4567', '서울시 광진구 자양로 765', '서영희', '기업은행', '025-5678-9012', '임준호', '광진센터', '2025-11-03 15:46:12', '단골 회원', 'Y', '아바타', '2025-11-03 15:46:12', '2025-11-05 11:36:11'),
	(24, 'lokkio1_0003', '로끼오1', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', 220000, '010-1111-2222', '주소주소', '추천인', '은행', '계좌번호', '예금주', '강남센터', '2025-11-03 15:48:52', '비고비고', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(25, 'user001_0003', '홍길동', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-1234-5678', '서울시 강남구 역삼로 123', '김추천', '신한은행', '110-123-456789', '홍길동', '강남센터', '2025-11-03 15:48:52', '정기 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(26, 'user002_0003', '김영희', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-2345-6789', '서울시 서초구 서초대로 456', '이소개', '국민은행', '123-456-789012', '김영희', '서초센터', '2025-11-03 15:48:52', '신규 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(27, 'user003_0003', '이철수', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-3456-7890', '서울시 송파구 올림픽로 789', '박지수', '우리은행', '1002-123-456789', '이철수', '송파센터', '2025-11-03 15:48:52', 'VIP 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(28, 'user004_0003', '박민지', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-4567-8901', '서울시 강동구 천호대로 321', '최동수', '하나은행', '110-456-789012', '박민지', '강동센터', '2025-11-03 15:48:52', '', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(29, 'user005_0003', '최수진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-5678-9012', '서울시 광진구 능동로 654', '정미라', '기업은행', '025-3456-7890', '최수진', '광진센터', '2025-11-03 15:48:52', '플래티넘 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(30, 'user006_0003', '정대호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-6789-0123', '서울시 강남구 테헤란로 234', '손영수', '신한은행', '110-789-012345', '정대호', '강남센터', '2025-11-03 15:48:52', '', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(31, 'user007_0003', '강미영', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-7890-1234', '서울시 서초구 강남대로 567', '한지영', '국민은행', '123-789-012345', '강미영', '서초센터', '2025-11-03 15:48:52', '이벤트 당첨자', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(32, 'user008_0003', '윤성민', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-8901-2345', '서울시 송파구 문정로 890', '강태훈', '우리은행', '1002-456-789012', '윤성민', '송파센터', '2025-11-03 15:48:52', '', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(33, 'user009_0003', '조혜진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-9012-3456', '서울시 강동구 성내천로 432', '류정호', '하나은행', '110-567-890123', '조혜진', '강동센터', '2025-11-03 15:48:52', '골드 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(34, 'user010_0003', '임준호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-0123-4567', '서울시 광진구 자양로 765', '서영희', '기업은행', '025-5678-9012', '임준호', '광진센터', '2025-11-03 15:48:52', '단골 회원', 'Y', '아바타', '2025-11-03 15:48:52', '2025-11-05 11:36:11'),
	(35, 'lokkio1_0004', '로끼오1', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', 220000, '010-1111-2222', '주소주소', '추천인', '은행', '계좌번호', '예금주', '강남센터', '2025-11-03 15:50:58', '비고비고', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(36, 'user001_0004', '홍길동', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-1234-5678', '서울시 강남구 역삼로 123', '김추천', '신한은행', '110-123-456789', '홍길동', '강남센터', '2025-11-03 15:50:58', '정기 회원', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(37, 'user003_0004', '이철수', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-3456-7890', '서울시 송파구 올림픽로 789', '박지수', '우리은행', '1002-123-456789', '이철수', '송파센터', '2025-11-03 15:50:58', 'VIP 회원', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(38, 'user004_0004', '박민지', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-4567-8901', '서울시 강동구 천호대로 321', '최동수', '하나은행', '110-456-789012', '박민지', '강동센터', '2025-11-03 15:50:58', '', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(39, 'user005_0004', '최수진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-5678-9012', '서울시 광진구 능동로 654', '정미라', '기업은행', '025-3456-7890', '최수진', '광진센터', '2025-11-03 15:50:58', '플래티넘 회원', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(40, 'user006_0004', '정대호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-6789-0123', '서울시 강남구 테헤란로 234', '손영수', '신한은행', '110-789-012345', '정대호', '강남센터', '2025-11-03 15:50:58', '', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(41, 'user008_0004', '윤성민', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-8901-2345', '서울시 송파구 문정로 890', '강태훈', '우리은행', '1002-456-789012', '윤성민', '송파센터', '2025-11-03 15:50:58', '', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(42, 'user009_0004', '조혜진', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-9012-3456', '서울시 강동구 성내천로 432', '류정호', '하나은행', '110-567-890123', '조혜진', '강동센터', '2025-11-03 15:50:58', '골드 회원', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(43, 'user010_0004', '임준호', '03ac674216f3e15c761ee1a5e255f067953623c8b388b4459e13f978d7c846f4', 220000, '010-0123-4567', '서울시 광진구 자양로 765', '서영희', '기업은행', '025-5678-9012', '임준호', '광진센터', '2025-11-03 15:50:58', '단골 회원', 'Y', '아바타', '2025-11-03 15:50:58', '2025-11-05 11:36:11'),
	(44, 'lokkio1_0005', '로끼오1', '0ffe1abd1a08215353c233d6e009613e95eec4253832a761af28ff37ac5a150c', 220000, '010-1111-2222', '주소주소', 'lokkio1', '은행', '계좌번호', '예금주', '강남센터', '2025-11-06 15:12:53', '비고비고', 'Y', '아바타', '2025-11-06 15:12:53', '2025-11-06 15:12:53');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

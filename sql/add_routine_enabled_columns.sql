-- m_routine_settings 테이블에 사용 체크박스 컬럼 추가
ALTER TABLE `m_routine_settings` 
ADD COLUMN `pre_routine_enabled` TINYINT(1) DEFAULT 1 COMMENT '프리루틴 사용 여부',
ADD COLUMN `post_routine_enabled` TINYINT(1) DEFAULT 1 COMMENT '엔드루틴 사용 여부';

-- 운동 수행 기록을 위한 m_workout_set 테이블 컬럼 추가
-- 실행일: 2025-01-27

ALTER TABLE `m_workout_set` 
ADD COLUMN `completed_at` timestamp NULL DEFAULT NULL COMMENT '세트 완료 시간',
ADD COLUMN `rest_time` int(11) DEFAULT NULL COMMENT '휴식 시간(초)',
ADD COLUMN `total_time` int(11) DEFAULT NULL COMMENT '총 운동 시간(초)';

-- m_workout_exercise 테이블에 시간 컬럼 추가
ALTER TABLE `m_workout_exercise` 
ADD COLUMN `time_seconds` int(11) DEFAULT 0 COMMENT '세트 수행 시간(초)' AFTER `sets`;

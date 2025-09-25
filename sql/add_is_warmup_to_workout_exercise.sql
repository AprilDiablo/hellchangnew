-- m_workout_exercise 테이블에 is_warmup 컬럼 추가
ALTER TABLE `m_workout_exercise` 
ADD COLUMN `is_warmup` tinyint(1) DEFAULT 0 COMMENT '웜업 운동 여부' AFTER `is_temp`;

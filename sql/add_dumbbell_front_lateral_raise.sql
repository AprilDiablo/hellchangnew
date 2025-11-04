-- 덤벨 프론트 레터럴 레이즈 운동 등록 (프론트 레터럴 레이즈, 바벨 프론트 레터럴 레이즈 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('덤벨 프론트 레터럴 레이즈', 'Dumbbell Front Lateral Raise', 'Dumbbell', '덤벨', 'Standing', '서서', 'Raise', '레이즈', '덤벨을 사용하여 앞으로 팔을 들어올리는 어깨 전면 운동');

-- 생성된 ex_id 확인 (예: 94)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 94)
-- 아래 쿼리에서 94를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('덤벨 프론트 레터럴 레이즈', 94),
('덤벨프론트레터럴레이즈', 94),
('Dumbbell Front Lateral Raise', 94),
('Dumbbell Front Raise', 94),
('덤벨 프론트 레이즈', 94),
('덤벨 전면 레이즈', 94),
('덤벨 어깨 전면 레이즈', 94),
('DB 프론트 레이즈', 94);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 94)
-- 프론트 레터럴 레이즈(31)와 바벨 프론트 레터럴 레이즈(71)를 참고하여 작성
-- M1201: 어깨 전면 (Anterior Deltoid) - Primary
-- M1202: 어깨 중부 (Middle Deltoid) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(94, 'M1201', 1, 1.00),
(94, 'M1202', 2, 0.60);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 94)
-- Z-SH-AN: 어깨 전면 (Anterior Deltoid) - Primary
-- Z-SH-LT: 어깨 측면 (Lateral Deltoid) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(94, 'Z-SH-AN', 1, 1.00),
(94, 'Z-SH-LT', 2, 0.60);


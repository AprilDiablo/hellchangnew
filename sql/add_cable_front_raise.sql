-- 케이블 프론트 레이즈 운동 등록 (프론트 레터럴 레이즈, 바벨 프론트 레터럴 레이즈 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('케이블 프론트 레이즈', 'Cable Front Raise', 'Cable', '케이블', 'Standing', '서서', 'Raise', '레이즈', '케이블을 사용하여 앞으로 팔을 들어올리는 어깨 전면 운동');

-- 생성된 ex_id 확인 (예: 90)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 90)
-- 아래 쿼리에서 90을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('케이블 프론트 레이즈', 90),
('케이블 프론트레이즈', 90),
('케이블프론트레이즈', 90),
('Cable Front Raise', 90),
('Cable Front Raises', 90),
('케이블 전면 레이즈', 90),
('케이블 어깨 전면 레이즈', 90),
('케이블 프론트 레터럴 레이즈', 90);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 90)
-- 프론트 레터럴 레이즈(31)와 바벨 프론트 레터럴 레이즈(71)를 참고하여 작성
-- M1201: 어깨 전면 (Anterior Deltoid) - Primary
-- M1202: 어깨 중부 (Middle Deltoid) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(90, 'M1201', 1, 1.00),
(90, 'M1202', 2, 0.60);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 90)
-- Z-SH-AN: 어깨 전면 (Anterior Deltoid) - Primary
-- Z-SH-LT: 어깨 측면 (Lateral Deltoid) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(90, 'Z-SH-AN', 1, 1.00),
(90, 'Z-SH-LT', 2, 0.60);


-- 원암 덤벨 로우 운동 등록
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('원암 덤벨 로우', 'One-Arm Dumbbell Row', 'Dumbbell', '덤벨', 'Bent Over', '앞으로 구부려서', 'Pull', '풀', '한 팔로 수행하는 덤벨 로우 운동');

-- 생성된 ex_id 확인 (예: 83)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 83)
-- 아래 쿼리에서 83을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('원암덤벨로우', 83),
('원암 덤벨 로우', 83),
('One-Arm Dumbbell Row', 83),
('원암 로우', 83),
('한팔 덤벨 로우', 83),
('원팔 덤벨 로우', 83),
('원암 DB 로우', 83);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 83)
-- M1401: 광배근 상부 (Latissimus Dorsi Upper)
-- M1402: 광배근 하부 (Latissimus Dorsi Lower)
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(83, 'M1401', 1, 1.00),
(83, 'M1402', 1, 1.00);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 83)
-- Z-BK-MD: 등 중앙부 (Back Middle)
-- Z-BK-UP: 등 상부 (Back Upper)
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(83, 'Z-BK-MD', 1, 1.00),
(83, 'Z-BK-UP', 1, 1.00);


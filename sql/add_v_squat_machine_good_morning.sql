-- V스쿼트 머신 굿모닝 운동 등록 (V스쿼트 머신, 굿모닝 운동 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('V스쿼트 머신 굿모닝', 'V-Squat Machine Good Morning', 'Machine', '머신', 'Standing', '서서', 'Good Morning', '굿모닝', 'V자형 머신에서 앞으로 구부리며 수행하는 굿모닝 운동, 햄스트링과 둔근 발달');

-- 생성된 ex_id 확인 (예: 86)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 86)
-- 아래 쿼리에서 86을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('V스쿼트 머신 굿모닝', 86),
('V스쿼트머신 굿모닝', 86),
('V스쿼트굿모닝', 86),
('V Squat Machine Good Morning', 86),
('V-Squat Machine Good Morning', 86),
('V머신 굿모닝', 86),
('V 머신 굿모닝', 86),
('V스쿼트 굿모닝', 86);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 86)
-- 굿모닝 운동은 주로 햄스트링과 둔근을 타겟으로 함
-- M2104: 햄스트링 (Hamstrings) - Primary
-- M1806: 대퇴이두근 장두 (Biceps Femoris Long Head) - Primary
-- M1808: 반건양근 (Semitendinosus) - Primary
-- M1809: 반막양근 (Semimembranosus) - Primary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Secondary
-- M1402: 광배근 하부 (Latissimus Dorsi Lower) - Secondary
-- M1403: 대능형근 (Rhomboids) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(86, 'M2104', 1, 1.00),
(86, 'M1806', 1, 0.90),
(86, 'M1808', 1, 0.90),
(86, 'M1809', 1, 0.90),
(86, 'M1801', 1, 0.80),
(86, 'M1802', 2, 0.60),
(86, 'M1402', 2, 0.50),
(86, 'M1403', 2, 0.40);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 86)
-- Z-LE-HA: 허벅지 후면 (Thigh Posterior/Hamstrings) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Primary
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(86, 'Z-LE-HA', 1, 1.00),
(86, 'Z-LE-GL', 1, 0.80),
(86, 'Z-BK-MD', 2, 0.50);


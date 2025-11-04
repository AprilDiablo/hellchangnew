-- 언더그립 바벨로우 운동 등록 (바벨 로우 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('언더그립 바벨로우', 'Underhand Barbell Row', 'Barbell', '바벨', 'Bent Over', '앞으로 구부려서', 'Pull', '풀', '손을 뒤집어 잡는 바벨로우 운동, 이두근과 등근육 발달에 효과적');

-- 생성된 ex_id 확인 (예: 101)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 101)
-- 아래 쿼리에서 101을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('언더그립 바벨로우', 101),
('언더그립 바벨 로우', 101),
('언더그립바벨로우', 101),
('Underhand Barbell Row', 101),
('Underhand Row', 101),
('리버스그립 바벨로우', 101),
('리버스 그립 바벨로우', 101),
('역그립 바벨로우', 101);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 101)
-- 바벨 로우(20)를 참고하되, 언더그립 특성상 이두근이 더 많이 작용
-- M1401: 광배근 상부 (Latissimus Dorsi Upper) - Primary
-- M1402: 광배근 하부 (Latissimus Dorsi Lower) - Primary
-- M1601: 이두근 장두 (Biceps Long Head) - Primary
-- M1602: 이두근 단두 (Biceps Short Head) - Primary
-- M1403: 대능형근 (Rhomboids) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(101, 'M1401', 1, 1.00),
(101, 'M1402', 1, 1.00),
(101, 'M1601', 1, 0.90),
(101, 'M1602', 1, 0.90),
(101, 'M1403', 2, 0.80);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 101)
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Primary
-- Z-BK-UP: 등 상부 (Upper Back/Traps) - Primary
-- Z-AR-BI: 팔 이두 (Biceps) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(101, 'Z-BK-MD', 1, 1.00),
(101, 'Z-BK-UP', 1, 1.00),
(101, 'Z-AR-BI', 1, 0.90);


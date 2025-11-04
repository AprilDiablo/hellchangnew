-- 비하인드 랫 풀 다운 운동 등록 (랫 풀 다운 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('비하인드 랫 풀 다운', 'Behind Neck Lat Pulldown', 'Machine', '머신', 'Seated', '앉아서', 'Pull', '풀', '목 뒤로 바를 내려서 수행하는 랫 풀 다운 운동');

-- 생성된 ex_id 확인 (예: 84)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 84)
-- 아래 쿼리에서 84를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('비하인드 랫 풀다운', 84),
('비하인드 랫풀다운', 84),
('비하인드 랫 풀 다운', 84),
('Behind Neck Lat Pulldown', 84),
('비하인드 넥 랫 풀다운', 84),
('비하인드 넥 풀다운', 84),
('넥 랫 풀다운', 84),
('목 뒤 랫 풀다운', 84);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 84)
-- 랫 풀 다운(58)과 동일한 근육 타겟 사용
-- M1203: 승모근 중부 (Trapezius Middle)
-- M1208: 승모근 하부 (Trapezius Lower)
-- M1402: 광배근 하부 (Latissimus Dorsi Lower)
-- M1403: 대능형근 (Rhomboids)
-- M1404: 광배근 상부 (Latissimus Dorsi Upper) - Primary
-- M1405: 능형근 (Rhomboids) - Primary
-- M1601: 이두근 장두 (Biceps Long Head)
-- M1602: 이두근 단두 (Biceps Short Head)
-- M1603: 상완근 (Brachialis)
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(84, 'M1203', 2, 0.40),
(84, 'M1208', 2, 0.30),
(84, 'M1402', 2, 0.60),
(84, 'M1403', 2, 0.50),
(84, 'M1404', 1, 1.00),
(84, 'M1405', 1, 0.80),
(84, 'M1601', 2, 0.30),
(84, 'M1602', 2, 0.30),
(84, 'M1603', 2, 0.20);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 84)
-- 랫 풀 다운과 유사한 타겟 사용 (등 운동)
-- Z-BK-UP: 등 상부 (Upper Back/Traps) - Primary
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(84, 'Z-BK-UP', 1, 1.00),
(84, 'Z-BK-MD', 1, 1.00);


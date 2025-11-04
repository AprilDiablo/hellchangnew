-- 하이 롱 풀 운동 등록 (케이블 로우, 시티드 로우 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('하이 롱 풀', 'High Row', 'Cable', '케이블', 'Standing', '서서', 'Pull', '풀', '높은 위치에서 당기는 로우 운동, 어깨 후면과 등 상부 발달에 효과적');

-- 생성된 ex_id 확인 (예: 96)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 96)
-- 아래 쿼리에서 96을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('하이 롱 풀', 96),
('하이롱풀', 96),
('High Row', 96),
('High Rows', 96),
('하이 로우', 96),
('케이블 하이 롱 풀', 96),
('케이블 하이로우', 96),
('하이 풀', 96);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 96)
-- 케이블 로우(22)와 시티드 로우(59)를 참고하여 작성
-- 하이 롱 풀은 높은 위치에서 당기므로 어깨 후면과 등 상부에 더 집중
-- M1203: 어깨 후면 (Posterior Deltoid) - Primary
-- M1401: 광배근 상부 (Latissimus Dorsi Upper) - Primary
-- M1402: 광배근 하부 (Latissimus Dorsi Lower) - Primary
-- M1403: 대능형근 (Rhomboids) - Primary
-- M1405: 능형근 (Rhomboids) - Primary
-- M1601: 이두근 장두 (Biceps Long Head) - Secondary
-- M1602: 이두근 단두 (Biceps Short Head) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(96, 'M1203', 1, 0.90),
(96, 'M1401', 1, 1.00),
(96, 'M1402', 1, 0.90),
(96, 'M1403', 1, 0.90),
(96, 'M1405', 1, 0.85),
(96, 'M1601', 2, 0.40),
(96, 'M1602', 2, 0.40);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 96)
-- Z-BK-UP: 등 상부 (Upper Back/Traps) - Primary
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Primary
-- Z-SH-PO: 어깨 후면 (Posterior Deltoid) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(96, 'Z-BK-UP', 1, 1.00),
(96, 'Z-BK-MD', 1, 0.90),
(96, 'Z-SH-PO', 1, 0.90);


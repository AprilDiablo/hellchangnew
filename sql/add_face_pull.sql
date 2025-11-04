-- 페이스풀 운동 등록 (리어 델트 플라이, 케이블 로우 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('페이스풀', 'Face Pull', 'Cable', '케이블', 'Standing', '서서', 'Pull', '풀', '케이블을 얼굴 쪽으로 당겨서 수행하는 어깨 후면과 상부 등근육 발달 운동');

-- 생성된 ex_id 확인 (예: 95)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 95)
-- 아래 쿼리에서 95를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('페이스풀', 95),
('페이스 풀', 95),
('Face Pull', 95),
('Face Pulls', 95),
('케이블 페이스풀', 95),
('케이블 페이스 풀', 95),
('얼굴 당기기', 95),
('페이스풀 운동', 95);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 95)
-- 리어 델트 플라이(32)와 케이블 로우(22)를 참고하여 작성
-- M1203: 어깨 후면 (Posterior Deltoid) - Primary
-- M1401: 광배근 상부 (Latissimus Dorsi Upper) - Primary
-- M1402: 광배근 하부 (Latissimus Dorsi Lower) - Primary
-- M1403: 대능형근 (Rhomboids) - Primary
-- M1202: 어깨 중부 (Middle Deltoid) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(95, 'M1203', 1, 1.00),
(95, 'M1401', 1, 0.80),
(95, 'M1402', 1, 0.80),
(95, 'M1403', 1, 0.90),
(95, 'M1202', 2, 0.40);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 95)
-- Z-SH-PO: 어깨 후면 (Posterior Deltoid) - Primary
-- Z-BK-UP: 등 상부 (Upper Back/Traps) - Primary
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(95, 'Z-SH-PO', 1, 1.00),
(95, 'Z-BK-UP', 1, 0.80),
(95, 'Z-BK-MD', 1, 0.90);


-- 루마니안 데드리프트 운동 등록 (바벨 데드리프트, 덤벨 데드리프트, 굿모닝 운동 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('루마니안 데드리프트', 'Romanian Deadlift', 'Barbell', '바벨', 'Standing', '서서', 'Pull', '풀', '무릎을 덜 구부리고 엉덩이를 뒤로 빼며 수행하는 데드리프트 변형, 햄스트링과 둔근 발달에 효과적');

-- 생성된 ex_id 확인 (예: 92)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 92)
-- 아래 쿼리에서 92를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('루마니안 데드리프트', 92),
('루마니안데드리프트', 92),
('Romanian Deadlift', 92),
('RDL', 92),
('루마니안 DL', 92),
('RDL 데드리프트', 92),
('루마니안 스트레이트 레그 데드리프트', 92),
('루마니안 스트레이트레그 데드리프트', 92);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 92)
-- 루마니안 데드리프트는 햄스트링과 둔근에 더 집중되는 운동
-- M2104: 햄스트링 (Hamstrings) - Primary
-- M1806: 대퇴이두근 장두 (Biceps Femoris Long Head) - Primary
-- M1808: 반건양근 (Semitendinosus) - Primary
-- M1809: 반막양근 (Semimembranosus) - Primary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Primary
-- M1401: 광배근 상부 (Latissimus Dorsi Upper) - Secondary
-- M1402: 광배근 하부 (Latissimus Dorsi Lower) - Secondary
-- M1403: 대능형근 (Rhomboids) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(92, 'M2104', 1, 1.00),
(92, 'M1806', 1, 0.95),
(92, 'M1808', 1, 0.95),
(92, 'M1809', 1, 0.95),
(92, 'M1801', 1, 0.85),
(92, 'M1802', 1, 0.75),
(92, 'M1401', 2, 0.60),
(92, 'M1402', 2, 0.60),
(92, 'M1403', 2, 0.50);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 92)
-- Z-LE-HA: 허벅지 후면 (Thigh Posterior/Hamstrings) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Primary
-- Z-BK-MD: 등 중부 (Mid Back/Rhomboids) - Secondary
-- Z-BK-UP: 등 상부 (Upper Back/Traps) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(92, 'Z-LE-HA', 1, 1.00),
(92, 'Z-LE-GL', 1, 0.85),
(92, 'Z-BK-MD', 2, 0.60),
(92, 'Z-BK-UP', 2, 0.60);


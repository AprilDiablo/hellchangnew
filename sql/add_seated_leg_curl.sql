-- 시티드 레그컬 운동 등록 (레그 컬, 라잉 레그컬 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('시티드 레그컬', 'Seated Leg Curl', 'Machine', '머신', 'Seated', '앉아서', 'Curl', '컬', '앉은 자세로 수행하는 햄스트링 컬 운동');

-- 생성된 ex_id 확인 (예: 98)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 98)
-- 아래 쿼리에서 98을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('시티드 레그컬', 98),
('시티드레그컬', 98),
('Seated Leg Curl', 98),
('Seated Leg Curl Machine', 98),
('앉은 레그컬', 98),
('앉은자세 레그컬', 98),
('시티드 햄스트링 컬', 98),
('시티드 레그 컬', 98);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 98)
-- 레그 컬(15)과 라잉 레그컬(76)을 참고하여 작성
-- M2104: 햄스트링 (Hamstrings) - Primary
-- M1806: 대퇴이두근 장두 (Biceps Femoris Long Head) - Primary
-- M1808: 반건양근 (Semitendinosus) - Primary
-- M1809: 반막양근 (Semimembranosus) - Primary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(98, 'M2104', 1, 1.00),
(98, 'M1806', 1, 0.95),
(98, 'M1808', 1, 0.95),
(98, 'M1809', 1, 0.95);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 98)
-- Z-LE-HA: 허벅지 후면 (Thigh Posterior/Hamstrings) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(98, 'Z-LE-HA', 1, 1.00);


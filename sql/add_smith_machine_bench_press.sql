-- 스미스 머신 벤치프레스 운동 등록 (벤치프레스, 헤머 벤치프레스, 체스트 프레스 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('스미스 머신 벤치프레스', 'Smith Machine Bench Press', 'Machine', '머신', 'Flat', '평평', 'Press', '프레스', '스미스 머신을 사용한 안전한 벤치프레스 운동');

-- 생성된 ex_id 확인 (예: 91)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 91)
-- 아래 쿼리에서 91을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('스미스 머신 벤치프레스', 91),
('스미스머신 벤치프레스', 91),
('스미스머신벤치프레스', 91),
('Smith Machine Bench Press', 91),
('Smith Bench Press', 91),
('스미스 벤치프레스', 91),
('스미스 벤치', 91),
('머신 벤치프레스', 91);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 91)
-- 벤치프레스(1)와 헤머 벤치프레스(80), 체스트 프레스(82)를 참고하여 작성
-- M1302: 가슴 중부 (Chest Middle) - Primary
-- M1301: 가슴 상부 (Chest Upper) - Secondary
-- M1604: 삼두근 (Triceps) - Secondary
-- M1201: 어깨 전면 (Anterior Deltoid) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(91, 'M1302', 1, 1.00),
(91, 'M1301', 2, 0.90),
(91, 'M1604', 2, 0.60),
(91, 'M1201', 2, 0.40);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 91)
-- Z-CH-MD: 가슴 중부 (Chest Middle) - Primary
-- Z-CH-UP: 가슴 상부 (Chest Upper) - Secondary
-- Z-AR-TR: 팔 삼두 (Triceps) - Secondary
-- Z-SH-AN: 어깨 전면 (Anterior Deltoid) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(91, 'Z-CH-MD', 1, 1.00),
(91, 'Z-CH-UP', 2, 0.80),
(91, 'Z-AR-TR', 2, 0.60),
(91, 'Z-SH-AN', 2, 0.40);


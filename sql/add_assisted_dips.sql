-- 어시스트 딥스 운동 등록 (딥스, 딥스(삼두), 어시스트 풀업 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('어시스트 딥스', 'Assisted Dips', 'Machine', '머신', 'Standing', '서서', 'Press', '프레스', '보조 머신 또는 밴드를 사용하여 수행하는 딥스 운동, 가슴과 삼두근 발달');

-- 생성된 ex_id 확인 (예: 97)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 97)
-- 아래 쿼리에서 97을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('어시스트 딥스', 97),
('어시스트딥스', 97),
('Assisted Dips', 97),
('Assisted Dips Machine', 97),
('보조 딥스', 97),
('머신 딥스', 97),
('딥스 머신', 97),
('딥스 보조', 97);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 97)
-- 딥스(9)와 딥스(삼두)(40)를 참고하여 작성
-- M1604: 삼두근 (Triceps) - Primary
-- M1302: 가슴 중부 (Chest Middle) - Primary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(97, 'M1604', 1, 1.00),
(97, 'M1302', 1, 0.70);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 97)
-- Z-AR-TR: 팔 삼두 (Triceps) - Primary
-- Z-CH-MD: 가슴 중부 (Chest Middle) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(97, 'Z-AR-TR', 1, 1.00),
(97, 'Z-CH-MD', 1, 0.70);


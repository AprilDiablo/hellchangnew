-- 리버스 플라이 운동 등록 (리어 델트 플라이 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('리버스 플라이', 'Reverse Fly', 'Dumbbell', '덤벨', 'Bent Over', '앞으로 구부려서', 'Fly', '플라이', '앞으로 구부려서 팔을 옆으로 펼치는 어깨 후면 운동');

-- 생성된 ex_id 확인 (예: 99)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 99)
-- 아래 쿼리에서 99를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('리버스 플라이', 99),
('리버스플라이', 99),
('Reverse Fly', 99),
('Reverse Flies', 99),
('벤트 오버 리버스 플라이', 99),
('Bent Over Reverse Fly', 99),
('리어 델트 플라이', 99),
('어깨 후면 플라이', 99);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 99)
-- 리어 델트 플라이(32)를 참고하여 작성
-- M1203: 어깨 후면 (Posterior Deltoid) - Primary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(99, 'M1203', 1, 1.00);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 99)
-- Z-SH-PO: 어깨 후면 (Posterior Deltoid) - Primary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(99, 'Z-SH-PO', 1, 1.00);


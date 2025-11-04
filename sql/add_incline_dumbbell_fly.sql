-- 인클라인 덤벨 플라이 운동 등록 (덤벨 플라이, 인클라인 덤벨 프레스 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('인클라인 덤벨 플라이', 'Incline Dumbbell Fly', 'Dumbbell', '덤벨', 'Incline', '경사 위', 'Fly', '플라이', '인클라인 각도로 수행하는 덤벨 플라이 운동, 가슴 상부 발달에 효과적');

-- 생성된 ex_id 확인 (예: 102)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 102)
-- 아래 쿼리에서 102를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('인클라인 덤벨 플라이', 102),
('인클라인덤벨플라이', 102),
('Incline Dumbbell Fly', 102),
('Incline Dumbbell Flies', 102),
('인클라인 덤벨 플라이', 102),
('인클라인 DB 플라이', 102),
('인클라인 플라이', 102),
('인클라인 덤벨 플라이 운동', 102);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 102)
-- 덤벨 플라이(7)와 인클라인 덤벨 프레스(2)를 참고하여 작성
-- M1301: 가슴 상부 (Chest Upper) - Primary
-- M1302: 가슴 중부 (Chest Middle) - Secondary
-- M1304: 가슴 내측 (Chest Inner) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(102, 'M1301', 1, 1.00),
(102, 'M1302', 2, 0.60),
(102, 'M1304', 2, 0.30);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 102)
-- Z-CH-UP: 가슴 상부 (Chest Upper) - Primary
-- Z-CH-MD: 가슴 중부 (Chest Middle) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(102, 'Z-CH-UP', 1, 1.00),
(102, 'Z-CH-MD', 2, 0.60);


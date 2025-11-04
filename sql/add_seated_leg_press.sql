-- 시티드 레그 프레스 운동 등록 (레그 프레스, 파워 레그프레스 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('시티드 레그 프레스', 'Seated Leg Press', 'Machine', '머신', 'Seated', '앉아서', 'Press', '프레스', '앉은 자세로 수행하는 레그 프레스 운동, 대퇴사두근과 둔근 발달');

-- 생성된 ex_id 확인 (예: 93)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 93)
-- 아래 쿼리에서 93을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('시티드 레그 프레스', 93),
('시티드레그프레스', 93),
('Seated Leg Press', 93),
('Seated Leg Press Machine', 93),
('앉은 레그 프레스', 93),
('앉은자세 레그프레스', 93),
('시티드 레그프레스', 93),
('시티드 레그 프레스 머신', 93);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 93)
-- 레그 프레스(13)와 파워 레그프레스(64)를 참고하여 작성
-- M2101: 대퇴사두근 (Quadriceps Femoris) - Primary
-- M2102: 대퇴사두근 전면 (Quadriceps Anterior) - Primary
-- M2103: 대퇴사두근 측면 (Quadriceps Lateral) - Secondary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(93, 'M2101', 1, 1.00),
(93, 'M2102', 1, 1.00),
(93, 'M2103', 2, 0.60),
(93, 'M1801', 1, 0.90),
(93, 'M1802', 2, 0.60);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 93)
-- Z-LE-QU: 허벅지 전면 (Thigh Anterior/Quadriceps) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(93, 'Z-LE-QU', 1, 1.00),
(93, 'Z-LE-GL', 2, 0.60);


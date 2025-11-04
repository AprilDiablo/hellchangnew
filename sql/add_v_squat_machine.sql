-- V스쿼트 머신 운동 등록 (스미스 스쿼트, 바벨 스쿼트 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('V스쿼트 머신', 'V-Squat Machine', 'Machine', '머신', 'Standing', '서서', 'Squat', '스쿼트', 'V자형 머신을 사용한 안정적인 스쿼트 운동');

-- 생성된 ex_id 확인 (예: 85)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 85)
-- 아래 쿼리에서 85를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('V스쿼트 머신', 85),
('V스쿼트머신', 85),
('V Squat Machine', 85),
('V-Squat Machine', 85),
('V스쿼트', 85),
('V 스쿼트', 85),
('V머신 스쿼트', 85),
('V 머신 스쿼트', 85);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 85)
-- 바벨 스쿼트(11)와 스미스 스쿼트(78)를 참고하여 작성
-- M2101: 대퇴사두근 전면 (Quadriceps Anterior) - Primary
-- M2102: 대퇴사두근 내측 (Quadriceps Medial) - Primary
-- M2103: 대퇴사두근 측면 (Quadriceps Lateral) - Secondary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Primary
-- M1901: 햄스트링 (Hamstrings) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(85, 'M2101', 1, 1.00),
(85, 'M2102', 1, 1.00),
(85, 'M2103', 2, 0.60),
(85, 'M1801', 1, 0.90),
(85, 'M1802', 1, 0.80),
(85, 'M1901', 2, 0.70);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 85)
-- Z-LE-QU: 허벅지 전면 (Thigh Anterior/Quadriceps) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Secondary
-- Z-LE-HA: 허벅지 후면 (Thigh Posterior/Hamstrings) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(85, 'Z-LE-QU', 1, 1.00),
(85, 'Z-LE-GL', 2, 0.60),
(85, 'Z-LE-HA', 2, 0.80);


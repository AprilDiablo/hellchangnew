-- 맨몸 스쿼트 운동 등록 (바벨 스쿼트, 덤벨 스쿼트 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('맨몸 스쿼트', 'Bodyweight Squat', 'Bodyweight', '맨몸', 'Standing', '서서', 'Squat', '스쿼트', '아무 장비 없이 체중만으로 수행하는 기본 스쿼트 운동');

-- 생성된 ex_id 확인 (예: 89)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 89)
-- 아래 쿼리에서 89를 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('맨몸 스쿼트', 89),
('맨몸스쿼트', 89),
('Bodyweight Squat', 89),
('Bodyweight Squats', 89),
('체중 스쿼트', 89),
('체중스쿼트', 89),
('에어 스쿼트', 89),
('Air Squat', 89);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 89)
-- 바벨 스쿼트(11)와 덤벨 스쿼트(12)를 참고하여 작성
-- M2101: 대퇴사두근 (Quadriceps Femoris) - Primary
-- M2102: 대퇴사두근 전면 (Quadriceps Anterior) - Primary
-- M2103: 대퇴사두근 측면 (Quadriceps Lateral) - Secondary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(89, 'M2101', 1, 1.00),
(89, 'M2102', 1, 1.00),
(89, 'M2103', 2, 0.60),
(89, 'M1801', 1, 0.90),
(89, 'M1802', 2, 0.60);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 89)
-- Z-LE-QU: 허벅지 전면 (Thigh Anterior/Quadriceps) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(89, 'Z-LE-QU', 1, 1.00),
(89, 'Z-LE-GL', 2, 0.60);


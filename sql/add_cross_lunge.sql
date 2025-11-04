-- 크로스 런지 운동 등록 (런지, 워킹 런지 참조)
-- 실행 순서: 1번 쿼리 먼저 실행 → 생성된 ex_id 확인 → 2~4번 쿼리에서 해당 ex_id 사용

-- 1. 운동 기본 정보 추가
INSERT INTO `m_exercise` (`name_kr`, `name_en`, `equipment`, `equipment_kr`, `angle`, `angle_kr`, `movement`, `movement_kr`, `note`) 
VALUES ('크로스 런지', 'Cross Lunge', 'Bodyweight', '맨몸', 'Standing', '서서', 'Lunge', '런지', '대각선으로 발을 내딛어 수행하는 런지 운동, 대퇴사두근과 둔근 발달 및 코어 안정성 향상');

-- 생성된 ex_id 확인 (예: 100)
-- SELECT LAST_INSERT_ID() AS ex_id;

-- 2. 유사어 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 100)
-- 아래 쿼리에서 100을 생성된 ex_id로 변경하세요
INSERT INTO `m_exercise_alias` (`alias`, `ex_id`) VALUES
('크로스 런지', 100),
('크로스런지', 100),
('Cross Lunge', 100),
('Cross Lunges', 100),
('대각선 런지', 100),
('사이드 크로스 런지', 100),
('Cross Step Lunge', 100),
('크로스 스텝 런지', 100);

-- 3. 근육 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 100)
-- 런지(16)와 워킹 런지(75)를 참고하여 작성
-- M2101: 대퇴사두근 (Quadriceps Femoris) - Primary
-- M2102: 대퇴사두근 전면 (Quadriceps Anterior) - Primary
-- M2103: 대퇴사두근 측면 (Quadriceps Lateral) - Secondary
-- M1801: 둔근 (Gluteus Maximus) - Primary
-- M1802: 둔근 중부 (Gluteus Medius) - Primary
-- M1901: 햄스트링 (Hamstrings) - Secondary
INSERT INTO `m_exercise_muscle_target` (`ex_id`, `muscle_code`, `priority`, `weight`) VALUES
(100, 'M2101', 1, 1.00),
(100, 'M2102', 1, 1.00),
(100, 'M2103', 2, 0.60),
(100, 'M1801', 1, 0.90),
(100, 'M1802', 1, 0.80),
(100, 'M1901', 2, 0.70);

-- 4. 세부존 타겟 추가 (ex_id는 위에서 생성된 값을 사용하세요, 예시: 100)
-- Z-LE-QU: 허벅지 전면 (Thigh Anterior/Quadriceps) - Primary
-- Z-LE-GL: 엉덩이 (Glute/Hip) - Primary
-- Z-LE-HA: 허벅지 후면 (Thigh Posterior/Hamstrings) - Secondary
INSERT INTO `m_exercise_zone_target` (`ex_id`, `zone_code`, `priority`, `weight`) VALUES
(100, 'Z-LE-QU', 1, 1.00),
(100, 'Z-LE-GL', 1, 0.90),
(100, 'Z-LE-HA', 2, 0.80);


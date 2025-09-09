-- "오늘의 운동" 텍스트를 빈 문자열로 업데이트
UPDATE m_workout_session 
SET note = '' 
WHERE note = '오늘의 운동';

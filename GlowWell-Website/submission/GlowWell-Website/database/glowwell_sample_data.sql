USE glowwell;

SET @assessment_password = '$2y$10$MSYalGIupyn4PnW8dtXgneWgIGoBHWxlOg4uLOsx1VIah78WV20uu';

INSERT INTO users (fullname, email, phone, password, role)
SELECT 'GlowWell Assessment Admin', 'glowwell.admin@glowwell.com', '07265987662', @assessment_password, 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'glowwell.admin@glowwell.com'
);

INSERT INTO users (fullname, email, phone, password, role)
SELECT 'Koketso Monageng', 'koketsobelsia@glowwell.com', '0796254679', @assessment_password, 'customer'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'koketsobelsia@glowwell.com'
);

INSERT INTO staff (fullname, phone, speciality, status)
SELECT 'Thabang Ndlovu (Assessment)', '0728846845', 'Skin Therapy', 'Active'
WHERE NOT EXISTS (
    SELECT 1 FROM staff WHERE fullname = 'Thabang Ndlovu (Assessment)'
);

INSERT INTO staff (fullname, phone, speciality, status)
SELECT 'Kabelo Molefe (Assessment)', '0714778300', 'Massage Therapy', 'Active'
WHERE NOT EXISTS (
    SELECT 1 FROM staff WHERE fullname = 'Kabelo Molefe (Assessment)'
);

INSERT INTO services (service_name, description, duration, price)
SELECT 'Assessment Signature Facial', 'A tailored facial treatment for healthy, radiant skin.', 60, 650.00
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE service_name = 'Assessment Signature Facial'
);

INSERT INTO services (service_name, description, duration, price)
SELECT 'Assessment Relaxation Massage', 'A restorative full-body massage to ease tension.', 60, 850.00
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE service_name = 'Assessment Relaxation Massage'
);

INSERT INTO services (service_name, description, duration, price)
SELECT 'Assessment Hair Renewal', 'A nourishing treatment to restore softness and shine.', 90, 950.00
WHERE NOT EXISTS (
    SELECT 1 FROM services WHERE service_name = 'Assessment Hair Renewal'
);

INSERT INTO staff_schedules (staff_id, weekday, start_time, end_time)
SELECT staff.staff_id, weekdays.weekday, '09:00:00', '18:00:00'
FROM staff
CROSS JOIN (
    SELECT 1 AS weekday UNION ALL SELECT 2 UNION ALL SELECT 3
    UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6
) AS weekdays
WHERE staff.fullname IN ('Thabang Ndlovu (Assessment)', 'Kabelo Molefe (Assessment)')
  AND NOT EXISTS (
      SELECT 1
      FROM staff_schedules AS schedule
      WHERE schedule.staff_id = staff.staff_id
        AND schedule.weekday = weekdays.weekday
  );

SET @assessment_customer_id = (
    SELECT user_id FROM users WHERE email = 'koketsobelsia@glowwell.com'
);
SET @assessment_facialist_id = (
    SELECT staff_id FROM staff WHERE fullname = 'Thabang Ndlovu (Assessment)'
);
SET @assessment_massage_id = (
    SELECT staff_id FROM staff WHERE fullname = 'Kabelo Molefe (Assessment)'
);
SET @assessment_facial_service_id = (
    SELECT service_id FROM services WHERE service_name = 'Assessment Signature Facial'
);
SET @assessment_massage_service_id = (
    SELECT service_id FROM services WHERE service_name = 'Assessment Relaxation Massage'
);
SET @assessment_hair_service_id = (
    SELECT service_id FROM services WHERE service_name = 'Assessment Hair Renewal'
);
SET @assessment_next_workday = DATE_ADD(
    CURDATE(),
    INTERVAL CASE WHEN DAYOFWEEK(CURDATE()) = 7 THEN 2 ELSE 1 END DAY
);
SET @assessment_following_workday = DATE_ADD(
    @assessment_next_workday,
    INTERVAL CASE WHEN DAYOFWEEK(@assessment_next_workday) = 7 THEN 2 ELSE 1 END DAY
);
SET @assessment_previous_workday = DATE_SUB(
    CURDATE(),
    INTERVAL CASE
        WHEN DAYOFWEEK(CURDATE()) = 1 THEN 2
        WHEN DAYOFWEEK(CURDATE()) = 2 THEN 3
        ELSE 1
    END DAY
);

INSERT INTO appointments (user_id, service_id, staff_id, appointment_date, appointment_time, status)
SELECT @assessment_customer_id, @assessment_facial_service_id, @assessment_facialist_id,
       @assessment_next_workday, '10:00:00', 'Confirmed'
WHERE NOT EXISTS (
    SELECT 1 FROM appointments
    WHERE user_id = @assessment_customer_id
      AND service_id = @assessment_facial_service_id
      AND staff_id = @assessment_facialist_id
      AND appointment_date = @assessment_next_workday
      AND appointment_time = '10:00:00'
);

INSERT INTO appointments (user_id, service_id, staff_id, appointment_date, appointment_time, status)
SELECT @assessment_customer_id, @assessment_massage_service_id, @assessment_massage_id,
       @assessment_following_workday, '14:00:00', 'Pending'
WHERE NOT EXISTS (
    SELECT 1 FROM appointments
    WHERE user_id = @assessment_customer_id
      AND service_id = @assessment_massage_service_id
      AND staff_id = @assessment_massage_id
      AND appointment_date = @assessment_following_workday
      AND appointment_time = '14:00:00'
);

INSERT INTO appointments (user_id, service_id, staff_id, appointment_date, appointment_time, status)
SELECT @assessment_customer_id, @assessment_hair_service_id, @assessment_facialist_id,
       @assessment_previous_workday, '11:00:00', 'Completed'
WHERE NOT EXISTS (
    SELECT 1 FROM appointments
    WHERE user_id = @assessment_customer_id
      AND service_id = @assessment_hair_service_id
      AND staff_id = @assessment_facialist_id
      AND appointment_date = @assessment_previous_workday
      AND appointment_time = '11:00:00'
);

INSERT INTO appointments (user_id, service_id, staff_id, appointment_date, appointment_time, status)
SELECT @assessment_customer_id, @assessment_facial_service_id, @assessment_facialist_id,
       @assessment_next_workday, '10:30:00', 'Cancelled'
WHERE NOT EXISTS (
    SELECT 1 FROM appointments
    WHERE user_id = @assessment_customer_id
      AND service_id = @assessment_facial_service_id
      AND staff_id = @assessment_facialist_id
      AND appointment_date = @assessment_next_workday
      AND appointment_time = '10:30:00'
      AND status = 'Cancelled'
);

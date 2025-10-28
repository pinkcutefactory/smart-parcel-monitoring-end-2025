-- สร้างฐานข้อมูลสำหรับ Smart Parcel Box
CREATE DATABASE smart_parcel_box;
USE smart_parcel_box;

-- ตารางข้อมูลเซ็นเซอร์ (Sensor Data)
CREATE TABLE sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    distance_cm FLOAT NOT NULL COMMENT 'ระยะทางจากเซ็นเซอร์ Ultrasonic (ซม.)',
    pir_motion BOOLEAN NOT NULL COMMENT 'การตรวจจับการเคลื่อนไหว (0=ไม่พบ, 1=ตรวจพบ)',
    box_status ENUM('OPEN', 'CLOSED') NOT NULL COMMENT 'สถานะกล่องพัสดุ',
    servo_position INT DEFAULT 90 COMMENT 'ตำแหน่ง Servo Motor (0=เปิด, 90=หยุด, 180=ปิด)',
    
    -- สถานะ Relay (Pilot Lamps)
    relay_red BOOLEAN DEFAULT FALSE COMMENT 'ไฟแดง - พัสดุเต็มแล้ว',
    relay_yellow BOOLEAN DEFAULT FALSE COMMENT 'ไฟเหลือง - พัสดุปานกลาง',
    relay_green BOOLEAN DEFAULT FALSE COMMENT 'ไฟเขียว - ส่งพัสดุได้เลย',
    
    -- ข้อมูลเพิ่มเติม
    lamp_status VARCHAR(50) COMMENT 'สถานะหลอดไฟปัจจุบัน',
    device_id VARCHAR(20) DEFAULT 'ESP32_BOX_01' COMMENT 'รหัสอุปกรณ์'
);

-- ตารางบันทึกการใช้งาน (Operation History)
CREATE TABLE operation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    operation_type ENUM('OPEN_ONLINE', 'OPEN_OFFLINE', 'AUTO_CLOSE', 'MANUAL_CLOSE') NOT NULL,
    trigger_method ENUM('GREEN_SWITCH', 'RED_SWITCH', 'AUTO_TIMER', 'MANUAL') NOT NULL,
    
    -- ข้อมูล ณ เวลาที่เกิดเหตุการณ์
    distance_at_operation FLOAT COMMENT 'ระยะทาง ณ เวลาที่เปิด/ปิด',
    motion_detected BOOLEAN COMMENT 'ตรวจพบการเคลื่อนไหวหรือไม่',
    lamp_status_at_operation VARCHAR(50) COMMENT 'สถานะไฟ ณ เวลาที่เกิดเหตุการณ์',
    
    -- เวลาการใช้งาน
    open_duration_seconds INT COMMENT 'ระยะเวลาที่เปิดกล่อง (วินาที)',
    
    -- ข้อมูลผู้ใช้
    user_present BOOLEAN COMMENT 'มีผู้ใช้อยู่หรือไม่',
    internet_mode BOOLEAN COMMENT 'โหมดออนไลน์ (TRUE) หรือออฟไลน์ (FALSE)',
    
    notes TEXT COMMENT 'หมายเหตุเพิ่มเติม'
);

-- ตารางสถิติรายวัน (Daily Statistics)
CREATE TABLE daily_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_recorded DATE NOT NULL UNIQUE,
    
    -- สถิติการใช้งาน
    total_operations INT DEFAULT 0 COMMENT 'จำนวนการเปิด-ปิดทั้งหมด',
    online_operations INT DEFAULT 0 COMMENT 'จำนวนการใช้งานโหมดออนไลน์',
    offline_operations INT DEFAULT 0 COMMENT 'จำนวนการใช้งานโหมดออฟไลน์',
    auto_closes INT DEFAULT 0 COMMENT 'จำนวนการปิดอัตโนมัติ',
    
    -- สถิติเซ็นเซอร์
    avg_distance FLOAT COMMENT 'ระยะทางเฉลี่ย (ซม.)',
    min_distance FLOAT COMMENT 'ระยะทางต่ำสุด (ซม.)',
    max_distance FLOAT COMMENT 'ระยะทางสูงสุด (ซม.)',
    motion_detections INT DEFAULT 0 COMMENT 'จำนวนครั้งที่ตรวจพบการเคลื่อนไหว',
    
    -- เวลาการใช้งาน
    total_open_time_minutes INT DEFAULT 0 COMMENT 'เวลารวมที่เปิดกล่อง (นาที)',
    avg_open_duration_seconds FLOAT COMMENT 'เวลาเฉลี่ยที่เปิดกล่อง (วินาที)',
    
    -- สถานะไฟ
    red_lamp_duration_minutes INT DEFAULT 0 COMMENT 'ระยะเวลาไฟแดงติด (นาที)',
    yellow_lamp_duration_minutes INT DEFAULT 0 COMMENT 'ระยะเวลาไฟเหลืองติด (นาที)',
    green_lamp_duration_minutes INT DEFAULT 0 COMMENT 'ระยะเวลาไฟเขียวติด (นาที)'
);

-- ตารางการแจ้งเตือน (Alerts)
CREATE TABLE alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    alert_type ENUM('BOX_FULL', 'LONG_OPEN', 'SENSOR_ERROR', 'MAINTENANCE') NOT NULL,
    alert_level ENUM('INFO', 'WARNING', 'CRITICAL') NOT NULL,
    message TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at DATETIME NULL,
    auto_generated BOOLEAN DEFAULT TRUE
);

-- สร้าง Index สำหรับการค้นหาที่เร็วขึ้น
CREATE INDEX idx_sensor_timestamp ON sensor_data(timestamp);
CREATE INDEX idx_operation_timestamp ON operation_history(timestamp);
CREATE INDEX idx_daily_date ON daily_statistics(date_recorded);
CREATE INDEX idx_alerts_timestamp ON alerts(timestamp);
CREATE INDEX idx_alerts_resolved ON alerts(is_resolved);

-- สร้าง View สำหรับข้อมูลล่าสุด
CREATE VIEW latest_sensor_data AS
SELECT * FROM sensor_data 
ORDER BY timestamp DESC 
LIMIT 1;

-- สร้าง View สำหรับสถิติ 7 วันล่าสุด
CREATE VIEW weekly_statistics AS
SELECT 
    DATE(timestamp) as date,
    COUNT(*) as total_readings,
    AVG(distance_cm) as avg_distance,
    MIN(distance_cm) as min_distance,
    MAX(distance_cm) as max_distance,
    SUM(CASE WHEN pir_motion = 1 THEN 1 ELSE 0 END) as motion_count,
    SUM(CASE WHEN box_status = 'OPEN' THEN 1 ELSE 0 END) as open_readings
FROM sensor_data 
WHERE timestamp >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
GROUP BY DATE(timestamp)
ORDER BY date DESC;

-- Stored Procedure สำหรับบันทึกข้อมูลเซ็นเซอร์
DELIMITER //
CREATE PROCEDURE RecordSensorData(
    IN p_distance FLOAT,
    IN p_pir_motion BOOLEAN,
    IN p_box_status VARCHAR(10),
    IN p_servo_position INT,
    IN p_relay_red BOOLEAN,
    IN p_relay_yellow BOOLEAN,
    IN p_relay_green BOOLEAN,
    IN p_lamp_status VARCHAR(50)
)
BEGIN
    INSERT INTO sensor_data 
    (distance_cm, pir_motion, box_status, servo_position, 
     relay_red, relay_yellow, relay_green, lamp_status)
    VALUES 
    (p_distance, p_pir_motion, p_box_status, p_servo_position,
     p_relay_red, p_relay_yellow, p_relay_green, p_lamp_status);
     
    -- สร้างแจ้งเตือนอัตโนมัติ
    IF p_distance <= 10 THEN
        INSERT INTO alerts (alert_type, alert_level, message)
        VALUES ('BOX_FULL', 'WARNING', 'กล่องพัสดุเต็มแล้ว - ระยะทาง: ' + CAST(p_distance AS CHAR) + ' ซม.');
    END IF;
END //
DELIMITER ;

-- Stored Procedure สำหรับบันทึกการใช้งาน
DELIMITER //
CREATE PROCEDURE RecordOperation(
    IN p_operation_type VARCHAR(20),
    IN p_trigger_method VARCHAR(20),
    IN p_distance FLOAT,
    IN p_motion_detected BOOLEAN,
    IN p_lamp_status VARCHAR(50),
    IN p_open_duration INT,
    IN p_internet_mode BOOLEAN,
    IN p_notes TEXT
)
BEGIN
    INSERT INTO operation_history 
    (operation_type, trigger_method, distance_at_operation, 
     motion_detected, lamp_status_at_operation, open_duration_seconds,
     user_present, internet_mode, notes)
    VALUES 
    (p_operation_type, p_trigger_method, p_distance,
     p_motion_detected, p_lamp_status, p_open_duration,
     p_motion_detected, p_internet_mode, p_notes);
END //
DELIMITER ;

-- Event สำหรับสรุปข้อมูลรายวัน (รันอัตโนมัติทุกวันเวลาเที่ยงคืน)
DELIMITER //
CREATE EVENT DailyStatisticsUpdate
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY)
DO
BEGIN
    INSERT INTO daily_statistics 
    (date_recorded, total_operations, online_operations, offline_operations,
     auto_closes, avg_distance, min_distance, max_distance, motion_detections)
    SELECT 
        DATE(CURRENT_DATE - INTERVAL 1 DAY),
        COUNT(DISTINCT oh.id),
        SUM(CASE WHEN oh.internet_mode = 1 THEN 1 ELSE 0 END),
        SUM(CASE WHEN oh.internet_mode = 0 THEN 1 ELSE 0 END),
        SUM(CASE WHEN oh.operation_type = 'AUTO_CLOSE' THEN 1 ELSE 0 END),
        AVG(sd.distance_cm),
        MIN(sd.distance_cm),
        MAX(sd.distance_cm),
        SUM(CASE WHEN sd.pir_motion = 1 THEN 1 ELSE 0 END)
    FROM sensor_data sd
    LEFT JOIN operation_history oh ON DATE(sd.timestamp) = DATE(oh.timestamp)
    WHERE DATE(sd.timestamp) = DATE(CURRENT_DATE - INTERVAL 1 DAY)
    ON DUPLICATE KEY UPDATE
        total_operations = VALUES(total_operations),
        online_operations = VALUES(online_operations),
        offline_operations = VALUES(offline_operations),
        auto_closes = VALUES(auto_closes),
        avg_distance = VALUES(avg_distance),
        min_distance = VALUES(min_distance),
        max_distance = VALUES(max_distance),
        motion_detections = VALUES(motion_detections);
END //
DELIMITER ;

-- เปิดใช้งาน Event Scheduler
SET GLOBAL event_scheduler = ON;

-- Insert ข้อมูลตัวอย่าง
INSERT INTO sensor_data (distance_cm, pir_motion, box_status, lamp_status) VALUES
(25.5, FALSE, 'CLOSED', 'GREEN ON - Ready to Send'),
(15.2, TRUE, 'CLOSED', 'YELLOW ON - Medium Space'),
(8.7, TRUE, 'OPEN', 'RED ON - Parcel Full');

INSERT INTO operation_history (operation_type, trigger_method, distance_at_operation, motion_detected, internet_mode) VALUES
('OPEN_ONLINE', 'GREEN_SWITCH', 25.5, TRUE, TRUE),
('OPEN_OFFLINE', 'RED_SWITCH', 15.2, TRUE, FALSE),
('AUTO_CLOSE', 'AUTO_TIMER', 8.7, FALSE, TRUE);
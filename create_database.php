<?php
// create_database.php - สร้างฐานข้อมูลและตารางอัตโนมัติ
error_reporting(E_ALL);
ini_set('display_errors', 1);

// การตั้งค่าฐานข้อมูล - แก้ไขตรงนี้ตามการตั้งค่าจริง
$host = "localhost";
$username = "root";
$password = "pinkcuteroot";
$database_name = "smart_parcel_box";

echo "<html><head><title>Smart Parcel Box - Database Setup</title></head><body>";
echo "<h1>🚀 Smart Parcel Box - Database Setup</h1>";
echo "<hr>";

try {
    // เชื่อมต่อ MySQL โดยไม่ระบุฐานข้อมูล
    echo "<h2>1. เชื่อมต่อ MySQL Server...</h2>";
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ เชื่อมต่อ MySQL Server สำเร็จ</p>";

    // สร้างฐานข้อมูล
    echo "<h2>2. สร้างฐานข้อมูล '$database_name'...</h2>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database_name");
    echo "<p>✅ สร้างฐานข้อมูล '$database_name' สำเร็จ</p>";

    // เชื่อมต่อฐานข้อมูลที่สร้างใหม่
    $pdo->exec("USE $database_name");
    echo "<p>✅ เลือกใช้ฐานข้อมูล '$database_name'</p>";

    // สร้างตาราง sensor_data
    echo "<h2>3. สร้างตารางข้อมูลเซ็นเซอร์...</h2>";
    $sensor_table_sql = "
    CREATE TABLE IF NOT EXISTS sensor_data (
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
    )";
    $pdo->exec($sensor_table_sql);
    echo "<p>✅ สร้างตาราง 'sensor_data' สำเร็จ</p>";

    // สร้างตาราง operation_history
    echo "<h2>4. สร้างตารางประวัติการใช้งาน...</h2>";
    $operation_table_sql = "
    CREATE TABLE IF NOT EXISTS operation_history (
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
    )";
    $pdo->exec($operation_table_sql);
    echo "<p>✅ สร้างตาราง 'operation_history' สำเร็จ</p>";

    // สร้างตาราง daily_statistics
    echo "<h2>5. สร้างตารางสถิติรายวัน...</h2>";
    $daily_stats_sql = "
    CREATE TABLE IF NOT EXISTS daily_statistics (
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
    )";
    $pdo->exec($daily_stats_sql);
    echo "<p>✅ สร้างตาราง 'daily_statistics' สำเร็จ</p>";

    // สร้างตาราง alerts
    echo "<h2>6. สร้างตารางการแจ้งเตือน...</h2>";
    $alerts_sql = "
    CREATE TABLE IF NOT EXISTS alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        alert_type ENUM('BOX_FULL', 'LONG_OPEN', 'SENSOR_ERROR', 'MAINTENANCE') NOT NULL,
        alert_level ENUM('INFO', 'WARNING', 'CRITICAL') NOT NULL,
        message TEXT NOT NULL,
        is_resolved BOOLEAN DEFAULT FALSE,
        resolved_at DATETIME NULL,
        auto_generated BOOLEAN DEFAULT TRUE
    )";
    $pdo->exec($alerts_sql);
    echo "<p>✅ สร้างตาราง 'alerts' สำเร็จ</p>";

    // สร้าง Indexes
    echo "<h2>7. สร้าง Database Indexes...</h2>";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_sensor_timestamp ON sensor_data(timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_operation_timestamp ON operation_history(timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_daily_date ON daily_statistics(date_recorded)",
        "CREATE INDEX IF NOT EXISTS idx_alerts_timestamp ON alerts(timestamp)",
        "CREATE INDEX IF NOT EXISTS idx_alerts_resolved ON alerts(is_resolved)"
    ];
    
    foreach ($indexes as $index_sql) {
        $pdo->exec($index_sql);
    }
    echo "<p>✅ สร้าง Database Indexes สำเร็จ</p>";

    // สร้าง Views
    echo "<h2>8. สร้าง Database Views...</h2>";
    $latest_view_sql = "
    CREATE OR REPLACE VIEW latest_sensor_data AS
    SELECT * FROM sensor_data 
    ORDER BY timestamp DESC 
    LIMIT 1";
    $pdo->exec($latest_view_sql);
    echo "<p>✅ สร้าง View 'latest_sensor_data' สำเร็จ</p>";

    $weekly_view_sql = "
    CREATE OR REPLACE VIEW weekly_statistics AS
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
    ORDER BY date DESC";
    $pdo->exec($weekly_view_sql);
    echo "<p>✅ สร้าง View 'weekly_statistics' สำเร็จ</p>";

    // เปิดใช้งาน Event Scheduler
    echo "<h2>9. เปิดใช้งาน Event Scheduler...</h2>";
    try {
        $pdo->exec("SET GLOBAL event_scheduler = ON");
        echo "<p>✅ เปิดใช้งาน Event Scheduler สำเร็จ</p>";
    } catch (PDOException $e) {
        echo "<p>⚠️ ไม่สามารถเปิด Event Scheduler ได้: " . $e->getMessage() . "</p>";
        echo "<p>หมายเหตุ: อาจต้องให้สิทธิ์ SUPER ในการเปิดใช้งาน Event Scheduler</p>";
    }

    // Insert ข้อมูลตัวอย่าง
    echo "<h2>10. เพิ่มข้อมูลตัวอย่าง...</h2>";
    
    // ตรวจสอบว่ามีข้อมูลในตารางแล้วหรือไม่
    $stmt = $pdo->query("SELECT COUNT(*) FROM sensor_data");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sample_data = [
            "INSERT INTO sensor_data (distance_cm, pir_motion, box_status, lamp_status) VALUES 
             (25.5, FALSE, 'CLOSED', 'GREEN ON - Ready to Send'),
             (15.2, TRUE, 'CLOSED', 'YELLOW ON - Medium Space'),
             (8.7, TRUE, 'OPEN', 'RED ON - Parcel Full')",
            
            "INSERT INTO operation_history (operation_type, trigger_method, distance_at_operation, motion_detected, internet_mode) VALUES 
             ('OPEN_ONLINE', 'GREEN_SWITCH', 25.5, TRUE, TRUE),
             ('OPEN_OFFLINE', 'RED_SWITCH', 15.2, TRUE, FALSE),
             ('AUTO_CLOSE', 'AUTO_TIMER', 8.7, FALSE, TRUE)"
        ];
        
        foreach ($sample_data as $sql) {
            $pdo->exec($sql);
        }
        echo "<p>✅ เพิ่มข้อมูลตัวอย่าง 6 รายการ สำเร็จ</p>";
    } else {
        echo "<p>ℹ️ มีข้อมูลในตารางอยู่แล้ว ($count รายการ) - ข้ามการเพิ่มข้อมูลตัวอย่าง</p>";
    }

    // แสดงสรุปผลลัพธ์
    echo "<hr>";
    echo "<h2>🎉 การติดตั้งเสร็จสมบูรณ์!</h2>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3>📊 สรุปการติดตั้ง:</h3>";
    echo "<ul>";
    echo "<li>✅ ฐานข้อมูล: <strong>$database_name</strong></li>";
    echo "<li>✅ ตาราง: <strong>sensor_data, operation_history, daily_statistics, alerts</strong></li>";
    echo "<li>✅ Indexes: <strong>5 indexes สำหรับการค้นหาที่เร็วขึ้น</strong></li>";
    echo "<li>✅ Views: <strong>latest_sensor_data, weekly_statistics</strong></li>";
    echo "<li>✅ ข้อมูลตัวอย่าง: <strong>6 รายการ</strong></li>";
    echo "</ul>";
    echo "</div>";

    // แสดงข้อมูลสำหรับไฟล์ config.php
    echo "<h3>⚙️ การตั้งค่าสำหรับ config.php:</h3>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo "private \$host = \"$host\";\n";
    echo "private \$db_name = \"$database_name\";\n";
    echo "private \$username = \"$username\";\n";
    echo "private \$password = \"$password\"; // แก้ไขถ้ามี password";
    echo "</pre>";

    // ลิงค์ไปยังไฟล์อื่น
    echo "<h3>🔗 ขั้นตอนต่อไป:</h3>";
    echo "<ol>";
    echo "<li><a href='test_connection.php'>ทดสอบการเชื่อมต่อฐานข้อมูล</a></li>";
    echo "<li><a href='upload_data.php'>ทดสอบ API สำหรับ ESP32</a></li>";
    echo "<li><a href='dashboard_api.php?action=latest_data'>ทดสอบ Dashboard API</a></li>";
    echo "<li><a href='dashboard.html'>เปิด Dashboard</a></li>";
    echo "</ol>";

    // แสดงตารางข้อมูล
    echo "<h3>📋 ข้อมูลตัวอย่างที่เพิ่มแล้ว:</h3>";
    $stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 3");
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>Timestamp</th><th>Distance</th><th>PIR</th><th>Box Status</th><th>Lamp Status</th>";
    echo "</tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['timestamp'] . "</td>";
        echo "<td>" . $row['distance_cm'] . " cm</td>";
        echo "<td>" . ($row['pir_motion'] ? 'ตรวจพบ' : 'ไม่พบ') . "</td>";
        echo "<td>" . $row['box_status'] . "</td>";
        echo "<td>" . $row['lamp_status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<h2>❌ เกิดข้อผิดพลาด!</h2>";
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>🔧 วิธีแก้ไข:</h3>";
    echo "<ol>";
    echo "<li>ตรวจสอบว่า XAMPP/WAMP เปิดอยู่</li>";
    echo "<li>ตรวจสอบว่า MySQL Service ทำงานอยู่</li>";
    echo "<li>ตรวจสอบ username และ password ด้านบนของไฟล์นี้</li>";
    echo "<li>ลองรีสตาร์ท Apache และ MySQL</li>";
    echo "</ol>";
}

echo "</body></html>";
?>
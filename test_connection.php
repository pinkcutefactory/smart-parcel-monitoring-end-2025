<?php
// test_connection.php - ทดสอบการเชื่อมต่อฐานข้อมูล
require_once 'config.php';

echo "<html><head><title>Database Connection Test</title></head><body>";
echo "<h1>🔧 Smart Parcel Box - Database Connection Test</h1>";

$database = new DatabaseConfig();
$db = $database->getConnection();

if ($db) {
    echo "<h2>✅ Database Connection Successful!</h2>";
    echo "<p><strong>Server:</strong> localhost</p>";
    echo "<p><strong>Database:</strong> smart_parcel_box</p>";
    
    // ทดสอบการสร้างตาราง
    try {
        echo "<h3>📊 Testing Database Tables...</h3>";
        
        // ตรวจสอบตารางที่มีอยู่
        $tables = ['sensor_data', 'operation_history', 'daily_statistics', 'alerts'];
        
        foreach ($tables as $table) {
            $checkQuery = "SELECT COUNT(*) as count FROM $table";
            $stmt = $db->prepare($checkQuery);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p>✅ Table '$table': " . $result['count'] . " records</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Table Error: " . $e->getMessage() . "</p>";
        echo "<p><strong>หมายเหตุ:</strong> กรุณารัน Database Schema ก่อนใช้งาน</p>";
    }
    
    // ทดสอบการ Insert ข้อมูลตัวอย่าง
    try {
        echo "<h3>📝 Testing Data Insertion...</h3>";
        
        $testQuery = "INSERT INTO sensor_data 
                     (distance_cm, pir_motion, box_status, lamp_status) 
                     VALUES (25.5, 0, 'CLOSED', 'GREEN ON - Ready to Send')";
        $db->exec($testQuery);
        echo "<p>✅ Test data inserted successfully</p>";
        
        // ทดสอบการ Select ข้อมูล
        $selectQuery = "SELECT COUNT(*) as total FROM sensor_data";
        $stmt = $db->prepare($selectQuery);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>📊 Total sensor records: " . $result['total'] . "</p>";
        
        // แสดงข้อมูลล่าสุด
        $latestQuery = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
        $stmt = $db->prepare($latestQuery);
        $stmt->execute();
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($latest) {
            echo "<h3>📋 Latest Data:</h3>";
            echo "<ul>";
            echo "<li><strong>Distance:</strong> " . $latest['distance_cm'] . " cm</li>";
            echo "<li><strong>PIR Motion:</strong> " . ($latest['pir_motion'] ? 'Detected' : 'Not Detected') . "</li>";
            echo "<li><strong>Box Status:</strong> " . $latest['box_status'] . "</li>";
            echo "<li><strong>Lamp Status:</strong> " . $latest['lamp_status'] . "</li>";
            echo "<li><strong>Timestamp:</strong> " . $latest['timestamp'] . "</li>";
            echo "</ul>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Data Error: " . $e->getMessage() . "</p>";
    }
    
    // ทดสอบ API Endpoints
    echo "<h3>🌐 API Endpoints Test:</h3>";
    echo "<ul>";
    echo "<li><a href='upload_data.php' target='_blank'>upload_data.php</a> - รับ/ส่งข้อมูลเซ็นเซอร์</li>";
    echo "<li><a href='dashboard_api.php?action=latest_data' target='_blank'>dashboard_api.php?action=latest_data</a> - ข้อมูลล่าสุด</li>";
    echo "<li><a href='dashboard_api.php?action=operation_history' target='_blank'>dashboard_api.php?action=operation_history</a> - ประวัติการใช้งาน</li>";
    echo "<li><a href='dashboard_api.php?action=system_status' target='_blank'>dashboard_api.php?action=system_status</a> - สถานะระบบ</li>";
    echo "</ul>";
    
    echo "<h3>📱 Dashboard:</h3>";
    echo "<p><a href='dashboard.html' target='_blank' style='background: #4299e1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>เปิด Dashboard</a></p>";
    
    echo "<hr>";
    echo "<h3>⚙️ Configuration Info:</h3>";
    echo "<pre>";
    echo "Database Host: localhost\n";
    echo "Database Name: smart_parcel_box\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "PDO Available: " . (class_exists('PDO') ? 'Yes' : 'No') . "\n";
    echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
    echo "</pre>";
    
} else {
    echo "<h2>❌ Database Connection Failed!</h2>";
    echo "<p>กรุณาตรวจสอบ:</p>";
    echo "<ul>";
    echo "<li>XAMPP/WAMP Server เปิดอยู่หรือไม่</li>";
    echo "<li>MySQL Service ทำงานหรือไม่</li>";
    echo "<li>Username และ Password ใน config.php ถูกต้องหรือไม่</li>";
    echo "<li>Database 'smart_parcel_box' ถูกสร้างแล้วหรือไม่</li>";
    echo "</ul>";
}

echo "</body></html>";
?>
<?php
// daily_report.php - ส่งรายงานประจำวันผ่าน Telegram
require_once 'config.php';
require_once 'telegram_notifications.php';

// สร้าง instances
$database = new DatabaseConfig();
$db = $database->getConnection();
$telegram = new TelegramNotifications();

try {
    // ดึงสถิติของวันเมื่อวาน
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // สถิติการใช้งาน
    $operationQuery = "SELECT 
                        COUNT(*) as total_operations,
                        SUM(CASE WHEN internet_mode = 1 THEN 1 ELSE 0 END) as online_operations,
                        SUM(CASE WHEN internet_mode = 0 THEN 1 ELSE 0 END) as offline_operations,
                        SUM(CASE WHEN operation_type = 'AUTO_CLOSE' THEN 1 ELSE 0 END) as auto_closes,
                        AVG(open_duration_seconds) as avg_open_duration
                      FROM operation_history 
                      WHERE DATE(timestamp) = :yesterday";
    
    $operationStmt = $db->prepare($operationQuery);
    $operationStmt->bindParam(':yesterday', $yesterday);
    $operationStmt->execute();
    $operationStats = $operationStmt->fetch(PDO::FETCH_ASSOC);
    
    // สถิติเซ็นเซอร์
    $sensorQuery = "SELECT 
                      COUNT(*) as total_readings,
                      AVG(distance_cm) as avg_distance,
                      MIN(distance_cm) as min_distance,
                      MAX(distance_cm) as max_distance,
                      SUM(CASE WHEN pir_motion = 1 THEN 1 ELSE 0 END) as motion_detections
                    FROM sensor_data 
                    WHERE DATE(timestamp) = :yesterday";
    
    $sensorStmt = $db->prepare($sensorQuery);
    $sensorStmt->bindParam(':yesterday', $yesterday);
    $sensorStmt->execute();
    $sensorStats = $sensorStmt->fetch(PDO::FETCH_ASSOC);
    
    // รวมสถิติ
    $combinedStats = array_merge($operationStats, $sensorStats);
    
    // ส่งรายงานผ่าน Telegram
    $result = $telegram->sendDailyReport($combinedStats);
    
    // บันทึกผลลัพธ์
    if ($result) {
        echo "Daily report sent successfully for $yesterday\n";
        
        // บันทึกลง log
        $logQuery = "INSERT INTO operation_history 
                    (operation_type, trigger_method, notes) 
                    VALUES ('DAILY_REPORT', 'AUTO_SCHEDULE', :notes)";
        $logStmt = $db->prepare($logQuery);
        $notes = "Daily report sent for $yesterday - Total operations: " . $operationStats['total_operations'];
        $logStmt->bindParam(':notes', $notes);
        $logStmt->execute();
        
    } else {
        echo "Failed to send daily report for $yesterday\n";
    }
    
} catch (Exception $e) {
    echo "Error generating daily report: " . $e->getMessage() . "\n";
    
    // แจ้งเตือนข้อผิดพลาด
    $telegram->sendNotification(
        'system_error', 
        "เกิดข้อผิดพลาดในการสร้างรายงานประจำวัน: " . $e->getMessage(),
        'high'
    );
}
?>
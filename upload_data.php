<?php
// upload_data.php - รับข้อมูลจาก ESP32 และส่งข้อมูลให้ Dashboard + Telegram notifications
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'telegram_notifications.php';

$database = new DatabaseConfig();
$db = $database->getConnection();

// สร้าง instance ของ Telegram notifications
$telegram = new TelegramNotifications();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจาก ESP32
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data) {
        try {
            // บันทึกข้อมูลเซ็นเซอร์
            $query = "INSERT INTO sensor_data 
                     (distance_cm, pir_motion, box_status, servo_position, 
                      relay_red, relay_yellow, relay_green, lamp_status) 
                     VALUES 
                     (:distance, :pir_motion, :box_status, :servo_position,
                      :relay_red, :relay_yellow, :relay_green, :lamp_status)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':distance', $data['distance_cm']);
            $stmt->bindParam(':pir_motion', $data['pir_motion'], PDO::PARAM_BOOL);
            $stmt->bindParam(':box_status', $data['box_status']);
            $stmt->bindParam(':servo_position', $data['servo_position']);
            $stmt->bindParam(':relay_red', $data['relay_red'], PDO::PARAM_BOOL);
            $stmt->bindParam(':relay_yellow', $data['relay_yellow'], PDO::PARAM_BOOL);
            $stmt->bindParam(':relay_green', $data['relay_green'], PDO::PARAM_BOOL);
            $stmt->bindParam(':lamp_status', $data['lamp_status']);
            
            if ($stmt->execute()) {
                
                // === Telegram Notifications ===
                
                // 1. แจ้งเตือนเมื่อกล่องเต็ม (ระยะทาง <= 10 ซม.)
                if ($data['distance_cm'] <= 10) {
                    $telegram->notifyBoxFull($data['distance_cm'], $data['lamp_status']);
                    
                    // สร้างแจ้งเตือนในฐานข้อมูล
                    $alertQuery = "INSERT INTO alerts (alert_type, alert_level, message) 
                                  VALUES ('BOX_FULL', 'WARNING', :message)";
                    $alertStmt = $db->prepare($alertQuery);
                    $alertMessage = 'กล่องพัสดุเต็มแล้ว - ระยะทาง: ' . $data['distance_cm'] . ' ซม.';
                    $alertStmt->bindParam(':message', $alertMessage);
                    $alertStmt->execute();
                }
                
                // 2. แจ้งเตือนเมื่อตรวจพบการเคลื่อนไหว (เฉพาะครั้งแรก)
                if ($data['pir_motion']) {
                    // ตรวจสอบว่าเป็นการตรวจจับครั้งแรกหรือไม่ (ใน 5 นาทีที่ผ่านมา)
                    $motionCheckQuery = "SELECT COUNT(*) as count FROM sensor_data 
                                       WHERE pir_motion = 1 
                                       AND timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                    $motionStmt = $db->prepare($motionCheckQuery);
                    $motionStmt->execute();
                    $motionResult = $motionStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // ส่งแจ้งเตือนเฉพาะเมื่อเป็นการตรวจจับครั้งแรก
                    if ($motionResult['count'] <= 1) {
                        $telegram->notifyMotionDetected($data['distance_cm'], $data['lamp_status']);
                    }
                }
                
                // 3. ตรวจสอบสถานะระบบ (ส่งทุก 30 นาที)
                $lastSystemCheck = "SELECT MAX(timestamp) as last_check FROM sensor_data";
                $systemStmt = $db->prepare($lastSystemCheck);
                $systemStmt->execute();
                $systemResult = $systemStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($systemResult && $systemResult['last_check']) {
                    $lastCheck = strtotime($systemResult['last_check']);
                    $now = time();
                    
                    // ถ้าไม่มีข้อมูลมานานกว่า 30 นาที แจ้งเตือนว่าระบบออนไลน์แล้ว
                    if (($now - $lastCheck) > 1800) { // 30 นาที
                        $telegram->notifySystemStatus('online');
                    }
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data saved successfully',
                    'telegram_sent' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
            } else {
                throw new Exception('Failed to save data');
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON data'
        ]);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ส่งข้อมูลล่าสุดไปยัง Dashboard
    try {
        $query = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // เพิ่มข้อมูลสถิติรายวัน
            $statsQuery = "SELECT 
                          COUNT(*) as total_readings,
                          AVG(distance_cm) as avg_distance,
                          MIN(distance_cm) as min_distance,
                          MAX(distance_cm) as max_distance,
                          SUM(CASE WHEN pir_motion = 1 THEN 1 ELSE 0 END) as motion_count
                          FROM sensor_data 
                          WHERE DATE(timestamp) = CURDATE()";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->execute();
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            $result['daily_stats'] = $stats;
            
            echo json_encode($result);
        } else {
            echo json_encode([
                'status' => 'no_data',
                'message' => 'No sensor data found'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>
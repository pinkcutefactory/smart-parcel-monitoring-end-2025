<?php
// error_report.php - รับรายงานข้อผิดพลาดจาก ESP32
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'telegram_notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new DatabaseConfig();
    $db = $database->getConnection();
    $telegram = new TelegramNotifications();
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data && isset($data['error_type'])) {
        try {
            // บันทึกข้อผิดพลาดลงฐานข้อมูล
            $alertQuery = "INSERT INTO alerts 
                          (alert_type, alert_level, message) 
                          VALUES ('SENSOR_ERROR', 'CRITICAL', :message)";
            
            $alertStmt = $db->prepare($alertQuery);
            $alertMessage = "เซ็นเซอร์มีปัญหา: {$data['error_type']} - {$data['error_message']} (จำนวนข้อผิดพลาด: {$data['error_count']})";
            $alertStmt->bindParam(':message', $alertMessage);
            $alertStmt->execute();
            
            // ส่งแจ้งเตือนผ่าน Telegram
            $result = $telegram->notifySensorError(
                $data['error_count'], 
                $data['error_type']
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Error report received and notification sent',
                'alert_id' => $db->lastInsertId(),
                'telegram_sent' => $result ? true : false,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
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
            'message' => 'Invalid error report data'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Use POST only.'
    ]);
}
?>
<?php
// operation_log.php - บันทึกการเปิด-ปิดกล่อง + Telegram notifications
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'telegram_notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new DatabaseConfig();
    $db = $database->getConnection();
    
    // สร้าง instance ของ Telegram notifications
    $telegram = new TelegramNotifications();
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data) {
        try {
            $query = "INSERT INTO operation_history 
                     (operation_type, trigger_method, distance_at_operation, 
                      motion_detected, lamp_status_at_operation, open_duration_seconds,
                      user_present, internet_mode, notes) 
                     VALUES 
                     (:operation_type, :trigger_method, :distance_at_operation,
                      :motion_detected, :lamp_status_at_operation, :open_duration_seconds,
                      :user_present, :internet_mode, :notes)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':operation_type', $data['operation_type']);
            $stmt->bindParam(':trigger_method', $data['trigger_method']);
            $stmt->bindParam(':distance_at_operation', $data['distance_at_operation']);
            $stmt->bindParam(':motion_detected', $data['motion_detected'], PDO::PARAM_BOOL);
            $stmt->bindParam(':lamp_status_at_operation', $data['lamp_status_at_operation']);
            $stmt->bindParam(':open_duration_seconds', $data['open_duration_seconds']);
            $stmt->bindParam(':user_present', $data['user_present'], PDO::PARAM_BOOL);
            $stmt->bindParam(':internet_mode', $data['internet_mode'], PDO::PARAM_BOOL);
            $stmt->bindParam(':notes', $data['notes']);
            
            if ($stmt->execute()) {
                
                // === Telegram Notifications ===
                
                // แจ้งเตือนตามประเภทการทำงาน
                switch ($data['operation_type']) {
                    case 'OPEN_ONLINE':
                        $telegram->notifyBoxOpened(
                            'online', 
                            $data['trigger_method'], 
                            $data['distance_at_operation'], 
                            $data['motion_detected']
                        );
                        break;
                        
                    case 'OPEN_OFFLINE':
                        $telegram->notifyBoxOpened(
                            'offline', 
                            $data['trigger_method'], 
                            $data['distance_at_operation'], 
                            $data['motion_detected']
                        );
                        break;
                        
                    case 'AUTO_CLOSE':
                        $telegram->notifyBoxClosed(
                            $data['open_duration_seconds'], 
                            $data['distance_at_operation']
                        );
                        break;
                }
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Operation logged successfully',
                    'operation_id' => $db->lastInsertId(),
                    'telegram_sent' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                throw new Exception('Failed to log operation');
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
            'message' => 'Invalid JSON data - Missing required fields'
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
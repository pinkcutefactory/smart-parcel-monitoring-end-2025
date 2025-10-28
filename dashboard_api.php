<?php
// dashboard_api.php - API สำหรับ Dashboard (ข้อมูลล่าสุด, กราฟ, ประวัติ)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$database = new DatabaseConfig();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'latest_data':
        // ข้อมูลเซ็นเซอร์ล่าสุด
        try {
            $query = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode([
                    'status' => 'no_data',
                    'message' => 'No sensor data available'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'hourly_data':
        // ข้อมูลรายชั่วโมงใน 24 ชั่วโมงล่าสุด
        try {
            $query = "SELECT 
                        HOUR(timestamp) as hour,
                        AVG(distance_cm) as avg_distance,
                        SUM(CASE WHEN pir_motion = 1 THEN 1 ELSE 0 END) as motion_count,
                        COUNT(*) as readings,
                        MIN(distance_cm) as min_distance,
                        MAX(distance_cm) as max_distance
                      FROM sensor_data 
                      WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      GROUP BY HOUR(timestamp)
                      ORDER BY hour";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'daily_stats':
        // สถิติ 7 วันล่าสุด
        try {
            $query = "SELECT 
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
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'operation_history':
        // ประวัติการใช้งาน 10 ครั้งล่าสุด
        try {
            $query = "SELECT 
                        id,
                        operation_type,
                        trigger_method,
                        distance_at_operation,
                        motion_detected,
                        internet_mode,
                        timestamp,
                        open_duration_seconds,
                        lamp_status_at_operation,
                        user_present
                      FROM operation_history 
                      ORDER BY timestamp DESC 
                      LIMIT 10";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'alerts':
        // การแจ้งเตือนที่ยังไม่ได้แก้ไข
        try {
            $query = "SELECT 
                        id,
                        timestamp,
                        alert_type,
                        alert_level,
                        message,
                        is_resolved
                      FROM alerts 
                      WHERE is_resolved = FALSE 
                      ORDER BY timestamp DESC
                      LIMIT 20";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'system_status':
        // สถานะระบบโดยรวม
        try {
            // ข้อมูลล่าสุด
            $latestQuery = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
            $latestStmt = $db->prepare($latestQuery);
            $latestStmt->execute();
            $latest = $latestStmt->fetch(PDO::FETCH_ASSOC);
            
            // นับจำนวนการใช้งานวันนี้
            $todayQuery = "SELECT 
                            COUNT(DISTINCT oh.id) as today_operations,
                            COUNT(DISTINCT sd.id) as today_readings,
                            AVG(sd.distance_cm) as avg_distance_today
                          FROM sensor_data sd
                          LEFT JOIN operation_history oh ON DATE(sd.timestamp) = DATE(oh.timestamp)
                          WHERE DATE(sd.timestamp) = CURDATE()";
            $todayStmt = $db->prepare($todayQuery);
            $todayStmt->execute();
            $today = $todayStmt->fetch(PDO::FETCH_ASSOC);
            
            // การแจ้งเตือนที่ยังไม่ได้แก้ไข
            $alertQuery = "SELECT COUNT(*) as unresolved_alerts FROM alerts WHERE is_resolved = FALSE";
            $alertStmt = $db->prepare($alertQuery);
            $alertStmt->execute();
            $alerts = $alertStmt->fetch(PDO::FETCH_ASSOC);
            
            $systemStatus = [
                'latest_data' => $latest,
                'today_stats' => $today,
                'unresolved_alerts' => $alerts['unresolved_alerts'],
                'system_uptime' => $latest ? $latest['timestamp'] : null,
                'database_status' => 'online'
            ];
            
            echo json_encode($systemStatus);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'recent_data':
        // ข้อมูล 20 รายการล่าสุดสำหรับกราฟ
        $limit = $_GET['limit'] ?? 20;
        try {
            $query = "SELECT 
                        distance_cm,
                        pir_motion,
                        timestamp,
                        lamp_status
                      FROM sensor_data 
                      ORDER BY timestamp DESC 
                      LIMIT :limit";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Reverse order เพื่อให้เรียงตามเวลา
            $result = array_reverse($result);
            
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action parameter',
            'available_actions' => [
                'latest_data', 
                'hourly_data', 
                'daily_stats', 
                'operation_history', 
                'alerts',
                'system_status',
                'recent_data'
            ]
        ]);
}
?>
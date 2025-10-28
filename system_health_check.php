<?php
// system_health_check.php - ตรวจสอบสุขภาพระบบและแจ้งเตือนปัญหา
require_once 'config.php';
require_once 'telegram_notifications.php';

$database = new DatabaseConfig();
$db = $database->getConnection();
$telegram = new TelegramNotifications();

$issues_found = [];
$current_time = date('Y-m-d H:i:s');

// 1. ตรวจสอบการเชื่อมต่อฐานข้อมูล
try {
    $db_test = $db->query("SELECT 1")->fetch();
    if (!$db_test) {
        $issues_found[] = "Database connection failed";
    }
} catch (Exception $e) {
    $issues_found[] = "Database error: " . $e->getMessage();
}

// 2. ตรวจสอบข้อมูลเซ็นเซอร์ล่าสุด
try {
    $sensor_query = "SELECT timestamp, distance_cm FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
    $sensor_stmt = $db->prepare($sensor_query);
    $sensor_stmt->execute();
    $latest_sensor = $sensor_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$latest_sensor) {
        $issues_found[] = "No sensor data found";
    } else {
        $last_update = strtotime($latest_sensor['timestamp']);
        $time_diff = time() - $last_update;
        
        // ถ้าไม่มีข้อมูลมานานกว่า 10 นาที
        if ($time_diff > 600) {
            $minutes_ago = floor($time_diff / 60);
            $issues_found[] = "No sensor data for {$minutes_ago} minutes";
        }
        
        // ตรวจสอบค่าเซ็นเซอร์ที่ผิดปกติ
        if ($latest_sensor['distance_cm'] <= 0 || $latest_sensor['distance_cm'] > 400) {
            $issues_found[] = "Abnormal sensor reading: {$latest_sensor['distance_cm']} cm";
        }
    }
} catch (Exception $e) {
    $issues_found[] = "Sensor data check failed: " . $e->getMessage();
}

// 3. ตรวจสอบการแจ้งเตือน Telegram
try {
    $telegram_test = $telegram->testNotification();
    if (!$telegram_test) {
        $issues_found[] = "Telegram notification test failed";
    }
} catch (Exception $e) {
    $issues_found[] = "Telegram error: " . $e->getMessage();
}

// 4. ตรวจสอบพื้นที่ดิสก์
$disk_free = disk_free_space(__DIR__);
$disk_total = disk_total_space(__DIR__);
$disk_usage_percent = (($disk_total - $disk_free) / $disk_total) * 100;

if ($disk_usage_percent > 90) {
    $issues_found[] = "Disk usage high: " . number_format($disk_usage_percent, 1) . "%";
} elseif ($disk_usage_percent > 80) {
    $issues_found[] = "Disk usage warning: " . number_format($disk_usage_percent, 1) . "%";
}

// 5. ตรวจสอบการใช้หน่วยความจำ
$memory_usage = memory_get_usage(true);
$memory_limit = ini_get('memory_limit');

if ($memory_limit !== '-1') {
    $memory_limit_bytes = return_bytes($memory_limit);
    $memory_percent = ($memory_usage / $memory_limit_bytes) * 100;
    
    if ($memory_percent > 80) {
        $issues_found[] = "High memory usage: " . number_format($memory_percent, 1) . "%";
    }
}

// 6. ตรวจสอบข้อมูลที่เก่าเกินไป
try {
    $old_data_query = "SELECT COUNT(*) as count FROM sensor_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $old_data_stmt = $db->prepare($old_data_query);
    $old_data_stmt->execute();
    $old_data = $old_data_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($old_data['count'] > 10000) {
        $issues_found[] = "Too much old data: {$old_data['count']} records older than 30 days";
    }
} catch (Exception $e) {
    $issues_found[] = "Old data check failed: " . $e->getMessage();
}

// 7. ตรวจสอบการทำงานของ Alerts ที่ยังไม่ได้แก้ไข
try {
    $unresolved_query = "SELECT COUNT(*) as count FROM alerts WHERE is_resolved = FALSE AND timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $unresolved_stmt = $db->prepare($unresolved_query);
    $unresolved_stmt->execute();
    $unresolved = $unresolved_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($unresolved['count'] > 5) {
        $issues_found[] = "Many unresolved alerts: {$unresolved['count']} alerts";
    }
} catch (Exception $e) {
    $issues_found[] = "Alert check failed: " . $e->getMessage();
}

// 8. บันทึกผลการตรวจสอบ
$log_message = "[{$current_time}] Health check completed. ";
if (empty($issues_found)) {
    $log_message .= "All systems OK.\n";
    $status = 'healthy';
} else {
    $log_message .= "Issues found: " . count($issues_found) . " - " . implode(', ', $issues_found) . "\n";
    $status = 'issues';
}

// เขียน log file
file_put_contents('health_check.log', $log_message, FILE_APPEND | LOCK_EX);

// ส่งแจ้งเตือนถ้ามีปัญหา
if (!empty($issues_found)) {
    $alert_message = "⚠️ <b>ตรวจพบปัญหาระบบ</b>\n\n";
    $alert_message .= "🔍 <b>ปัญหาที่พบ:</b>\n";
    
    foreach ($issues_found as $issue) {
        $alert_message .= "• {$issue}\n";
    }
    
    $alert_message .= "\n⏰ เวลาตรวจสอบ: <code>{$current_time}</code>\n";
    $alert_message .= "🛠️ กรุณาตรวจสอบและแก้ไขปัญหา";
    
    $telegram->sendNotification('system_health', $alert_message, 'high');
}

// ส่งรายงานสถานะปกติทุก 6 ชั่วโมง
$last_ok_report_file = 'last_ok_report.txt';
$should_send_ok_report = false;

if (file_exists($last_ok_report_file)) {
    $last_ok_time = filemtime($last_ok_report_file);
    if ((time() - $last_ok_time) > 21600) { // 6 ชั่วโมง
        $should_send_ok_report = true;
    }
} else {
    $should_send_ok_report = true;
}

if (empty($issues_found) && $should_send_ok_report) {
    $ok_message = "✅ <b>ระบบทำงานปกติ</b>\n\n";
    $ok_message .= "🔋 <b>สถานะระบบ:</b>\n";
    $ok_message .= "• ฐานข้อมูล: ✅ ปกติ\n";
    $ok_message .= "• เซ็นเซอร์: ✅ ทำงานปกติ\n";
    $ok_message .= "• Telegram: ✅ เชื่อมต่อปกติ\n";
    $ok_message .= "• พื้นที่ดิสก์: " . number_format($disk_usage_percent, 1) . "% ใช้งาน\n";
    $ok_message .= "\n⏰ เวลาตรวจสอบ: <code>{$current_time}</code>";
    
    $telegram->sendNotification('system_ok', $ok_message, 'low');
    touch($last_ok_report_file);
}

// Output สำหรับ command line
if (php_sapi_name() === 'cli') {
    echo "=== System Health Check ===\n";
    echo "Time: {$current_time}\n";
    echo "Status: " . ($status === 'healthy' ? 'HEALTHY' : 'ISSUES FOUND') . "\n";
    
    if (!empty($issues_found)) {
        echo "Issues:\n";
        foreach ($issues_found as $issue) {
            echo "  - {$issue}\n";
        }
    }
    echo "===========================\n";
}

// Helper function
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}
?>
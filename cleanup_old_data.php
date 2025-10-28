<?php
// cleanup_old_data.php - ทำความสะอาดข้อมูลเก่าและเพิ่มประสิทธิภาพฐานข้อมูล
require_once 'config.php';
require_once 'telegram_notifications.php';

$database = new DatabaseConfig();
$db = $database->getConnection();
$telegram = new TelegramNotifications();

$cleanup_results = [];
$current_time = date('Y-m-d H:i:s');

try {
    echo "Starting database cleanup at {$current_time}\n";
    
    // 1. ลบข้อมูลเซ็นเซอร์เก่ากว่า 60 วัน (เก็บแค่สถิติ)
    $sensor_cleanup_query = "DELETE FROM sensor_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 60 DAY)";
    $sensor_result = $db->exec($sensor_cleanup_query);
    $cleanup_results['old_sensor_data'] = $sensor_result;
    echo "Deleted {$sensor_result} old sensor records\n";
    
    // 2. ลบประวัติการใช้งานเก่ากว่า 90 วัน
    $operation_cleanup_query = "DELETE FROM operation_history WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $operation_result = $db->exec($operation_cleanup_query);
    $cleanup_results['old_operations'] = $operation_result;
    echo "Deleted {$operation_result} old operation records\n";
    
    // 3. ลบการแจ้งเตือนที่แก้ไขแล้วและเก่ากว่า 30 วัน
    $alerts_cleanup_query = "DELETE FROM alerts WHERE is_resolved = TRUE AND timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $alerts_result = $db->exec($alerts_cleanup_query);
    $cleanup_results['old_alerts'] = $alerts_result;
    echo "Deleted {$alerts_result} resolved alert records\n";
    
    // 4. ลบ Telegram notifications เก่ากว่า 14 วัน
    $telegram_cleanup_query = "DELETE FROM telegram_notifications WHERE timestamp < DATE_SUB(NOW(), INTERVAL 14 DAY)";
    $telegram_result = $db->exec($telegram_cleanup_query);
    $cleanup_results['old_telegram_logs'] = $telegram_result;
    echo "Deleted {$telegram_result} old telegram notification logs\n";
    
    // 5. อัปเดตสถิติรายวันจากข้อมูลที่เหลืออยู่
    $stats_query = "INSERT IGNORE INTO daily_statistics 
                    (date_recorded, total_operations, online_operations, offline_operations, 
                     avg_distance, min_distance, max_distance, motion_detections)
                    SELECT 
                        DATE(sd.timestamp) as date,
                        COUNT(DISTINCT oh.id) as total_ops,
                        SUM(CASE WHEN oh.internet_mode = 1 THEN 1 ELSE 0 END) as online_ops,
                        SUM(CASE WHEN oh.internet_mode = 0 THEN 1 ELSE 0 END) as offline_ops,
                        AVG(sd.distance_cm) as avg_dist,
                        MIN(sd.distance_cm) as min_dist,
                        MAX(sd.distance_cm) as max_dist,
                        SUM(CASE WHEN sd.pir_motion = 1 THEN 1 ELSE 0 END) as motions
                    FROM sensor_data sd
                    LEFT JOIN operation_history oh ON DATE(sd.timestamp) = DATE(oh.timestamp)
                    WHERE sd.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND DATE(sd.timestamp) NOT IN (SELECT date_recorded FROM daily_statistics)
                    GROUP BY DATE(sd.timestamp)";
    
    $stats_result = $db->exec($stats_query);
    $cleanup_results['stats_updated'] = $stats_result;
    echo "Updated {$stats_result} daily statistics records\n";
    
    // 6. เพิ่มประสิทธิภาพตาราง (OPTIMIZE)
    $tables = ['sensor_data', 'operation_history', 'alerts', 'telegram_notifications', 'daily_statistics'];
    $optimized_tables = 0;
    
    foreach ($tables as $table) {
        try {
            $db->exec("OPTIMIZE TABLE {$table}");
            $optimized_tables++;
            echo "Optimized table: {$table}\n";
        } catch (Exception $e) {
            echo "Warning: Could not optimize {$table} - " . $e->getMessage() . "\n";
        }
    }
    
    $cleanup_results['optimized_tables'] = $optimized_tables;
    
    // 7. ทำความสะอาดไฟล์ log เก่า
    $log_files_cleaned = 0;
    $log_files = glob('*.log');
    
    foreach ($log_files as $log_file) {
        if (filemtime($log_file) < strtotime('-30 days')) {
            if (unlink($log_file)) {
                $log_files_cleaned++;
                echo "Deleted old log file: {$log_file}\n";
            }
        }
    }
    
    $cleanup_results['log_files_cleaned'] = $log_files_cleaned;
    
    // 8. ตรวจสอบขนาดฐานข้อมูลหลังทำความสะอาด
    $size_query = "SELECT 
                    table_name AS 'Table',
                    round(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
                   FROM information_schema.TABLES 
                   WHERE table_schema = DATABASE()
                   ORDER BY (data_length + index_length) DESC";
    
    $size_stmt = $db->prepare($size_query);
    $size_stmt->execute();
    $table_sizes = $size_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_size = array_sum(array_column($table_sizes, 'Size (MB)'));
    
    echo "Database size after cleanup: " . number_format($total_size, 2) . " MB\n";
    
    // สร้างรายงานสรุป
    $summary = [
        'cleanup_time' => $current_time,
        'records_deleted' => array_sum([
            $cleanup_results['old_sensor_data'],
            $cleanup_results['old_operations'], 
            $cleanup_results['old_alerts'],
            $cleanup_results['old_telegram_logs']
        ]),
        'tables_optimized' => $cleanup_results['optimized_tables'],
        'stats_updated' => $cleanup_results['stats_updated'],
        'log_files_cleaned' => $cleanup_results['log_files_cleaned'],
        'database_size_mb' => $total_size
    ];
    
    // เขียน log การทำความสะอาด
    $log_entry = "[{$current_time}] Database cleanup completed: " . 
                 "{$summary['records_deleted']} records deleted, " .
                 "{$summary['tables_optimized']} tables optimized, " .
                 "DB size: {$summary['database_size_mb']} MB\n";
    
    file_put_contents('cleanup.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    // ส่งรายงานผ่าน Telegram (รายสัปดาห์)
    if (date('w') === '0') { // วันอาทิตย์
        $telegram_message = "🧹 <b>รายงานการทำความสะอาดฐานข้อมูล</b>\n\n";
        $telegram_message .= "📅 วันที่: <code>{$current_time}</code>\n\n";
        $telegram_message .= "🗑️ <b>ข้อมูลที่ลบ:</b>\n";
        $telegram_message .= "• เซ็นเซอร์เก่า: <code>{$cleanup_results['old_sensor_data']}</code> รายการ\n";
        $telegram_message .= "• การดำเนินการเก่า: <code>{$cleanup_results['old_operations']}</code> รายการ\n";
        $telegram_message .= "• การแจ้งเตือนเก่า: <code>{$cleanup_results['old_alerts']}</code> รายการ\n";
        $telegram_message .= "• Telegram logs: <code>{$cleanup_results['old_telegram_logs']}</code> รายการ\n\n";
        $telegram_message .= "⚡ <b>การเพิ่มประสิทธิภาพ:</b>\n";
        $telegram_message .= "• ตารางที่ optimize: <code>{$cleanup_results['optimized_tables']}</code> ตาราง\n";
        $telegram_message .= "• สถิติที่อัปเดต: <code>{$cleanup_results['stats_updated']}</code> วัน\n";
        $telegram_message .= "• Log files ลบ: <code>{$cleanup_results['log_files_cleaned']}</code> ไฟล์\n\n";
        $telegram_message .= "💾 ขนาดฐานข้อมูลปัจจุบัน: <code>" . number_format($total_size, 2) . " MB</code>";
        
        $telegram->sendNotification('database_cleanup', $telegram_message, 'low');
    }
    
    // Output สำหรับ command line
    echo "\n=== Cleanup Summary ===\n";
    echo "Total records deleted: {$summary['records_deleted']}\n";
    echo "Tables optimized: {$summary['tables_optimized']}\n";
    echo "Log files cleaned: {$summary['log_files_cleaned']}\n";
    echo "Database size: {$summary['database_size_mb']} MB\n";
    echo "Cleanup completed successfully!\n";
    echo "=======================\n";
    
} catch (Exception $e) {
    $error_message = "Database cleanup failed: " . $e->getMessage();
    echo $error_message . "\n";
    
    // เขียน error log
    file_put_contents('cleanup_errors.log', "[{$current_time}] {$error_message}\n", FILE_APPEND | LOCK_EX);
    
    // แจ้งเตือน error ผ่าน Telegram
    $telegram->sendNotification(
        'cleanup_error',
        "❌ <b>การทำความสะอาดฐานข้อมูลล้มเหลว</b>\n\nError: <code>{$e->getMessage()}</code>\n\n⏰ เวลา: <code>{$current_time}</code>",
        'high'
    );
}
?>
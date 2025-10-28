<?php
// setup_cron.php - ตั้งค่า Cron Job และการทำงานอัตโนมัติ
echo "<html><head><title>Cron Job Setup - Smart Parcel Box</title></head><body>";
echo "<h1>⏰ ตั้งค่า Cron Job และการทำงานอัตโนมัติ</h1>";
echo "<hr>";

// 1. แสดงคำแนะนำสำหรับ Linux/Unix
echo "<h2>1. 🐧 สำหรับ Linux/Unix Server</h2>";
echo "<p>เพิ่ม Cron Jobs ด้านล่างนี้ในไฟล์ crontab:</p>";
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
echo "# เปิด crontab\n";
echo "crontab -e\n\n";
echo "# เพิ่มบรรทัดเหล่านี้:\n\n";
echo "# ส่งรายงานประจำวันทุกวันเวลา 08:00\n";
echo "0 8 * * * /usr/bin/php " . __DIR__ . "/daily_report.php\n\n";
echo "# ตรวจสอบสถานะระบบทุก 30 นาที\n";
echo "*/30 * * * * /usr/bin/php " . __DIR__ . "/system_health_check.php\n\n";
echo "# ทำความสะอาดข้อมูลเก่าทุกวันอาทิตย์ เวลา 02:00\n";
echo "0 2 * * 0 /usr/bin/php " . __DIR__ . "/cleanup_old_data.php\n";
echo "</pre>";

// 2. แสดงคำแนะนำสำหรับ Windows
echo "<h2>2. 🪟 สำหรับ Windows Server</h2>";
echo "<p>สร้าง Batch Files และตั้งค่าใน Task Scheduler:</p>";

echo "<h3>📄 สร้างไฟล์ daily_report.bat:</h3>";
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
echo "@echo off\n";
echo "cd /d \"" . __DIR__ . "\"\n";
echo "php daily_report.php\n";
echo "echo Daily report completed at %date% %time% >> daily_report.log";
echo "</pre>";

echo "<h3>📄 สร้างไฟล์ system_check.bat:</h3>";
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
echo "@echo off\n";
echo "cd /d \"" . __DIR__ . "\"\n";
echo "php system_health_check.php\n";
echo "echo Health check completed at %date% %time% >> health_check.log";
echo "</pre>";

echo "<p><strong>แล้วตั้งค่าใน Task Scheduler:</strong></p>";
echo "<ol>";
echo "<li>เปิด Task Scheduler</li>";
echo "<li>Create Basic Task</li>";
echo "<li>ตั้งชื่อและกำหนดเวลา</li>";
echo "<li>Action: Start a program</li>";
echo "<li>Program: ไฟล์ .bat ที่สร้าง</li>";
echo "</ol>";

// 3. สำหรับ Shared Hosting
echo "<h2>3. 🌐 สำหรับ Shared Hosting</h2>";
echo "<p>ใช้ Web Cron Service หรือ cPanel Cron Jobs:</p>";
echo "<div style='background: #e8f4f8; padding: 15px; border-radius: 5px;'>";
echo "<h4>URLs สำหรับ Web Cron:</h4>";
echo "<ul>";
echo "<li><code>" . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') . dirname($_SERVER['REQUEST_URI']) . "/daily_report.php</code></li>";
echo "<li><code>" . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') . dirname($_SERVER['REQUEST_URI']) . "/system_health_check.php</code></li>";
echo "</ul>";
echo "</div>";

// 4. ตรวจสอบการทำงานของ PHP CLI
echo "<h2>4. 🔍 ตรวจสอบ PHP CLI</h2>";

$php_path = exec('which php 2>/dev/null');
if (empty($php_path)) {
    $php_path = exec('where php 2>nul');
}

if (!empty($php_path)) {
    echo "<p>✅ พบ PHP CLI ที่: <code>$php_path</code></p>";
} else {
    echo "<p>⚠️ ไม่พบ PHP CLI หรือไม่สามารถเข้าถึงได้</p>";
    echo "<p>ลองใช้ path เหล่านี้:</p>";
    echo "<ul>";
    echo "<li>/usr/bin/php</li>";
    echo "<li>/usr/local/bin/php</li>";
    echo "<li>/opt/lampp/bin/php</li>";
    echo "<li>C:\\php\\php.exe (Windows)</li>";
    echo "</ul>";
}

// ทดสอบการรันไฟล์
echo "<h3>🧪 ทดสอบการรันไฟล์:</h3>";
if (isset($_GET['test']) && $_GET['test'] === 'daily_report') {
    echo "<div style='background: #f0f8ff; padding: 10px; border-radius: 5px;'>";
    echo "<h4>ผลการทดสอบ daily_report.php:</h4>";
    
    ob_start();
    include 'daily_report.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "<pre>$output</pre>";
    } else {
        echo "<p>ไฟล์ทำงานเสร็จแล้ว (ไม่มี output)</p>";
    }
    echo "</div>";
}

echo "<p><a href='?test=daily_report' style='background: #4CAF50; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>ทดสอบ Daily Report</a></p>";

// 5. แสดง Log Files
echo "<h2>5. 📋 Log Files และการติดตาม</h2>";
echo "<p>ไฟล์ Log ที่ควรติดตาม:</p>";
echo "<ul>";
echo "<li><strong>daily_report.log</strong> - บันทึกการส่งรายงานประจำวัน</li>";
echo "<li><strong>health_check.log</strong> - บันทึกการตรวจสอบระบบ</li>";
echo "<li><strong>telegram_errors.log</strong> - บันทึกข้อผิดพลาด Telegram</li>";
echo "</ul>";

// ตรวจสอบว่ามีไฟล์ log หรือไม่
$log_files = ['daily_report.log', 'health_check.log', 'telegram_errors.log'];
foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        $size = filesize($log_file);
        $modified = date('Y-m-d H:i:s', filemtime($log_file));
        echo "<p>✅ <strong>$log_file</strong>: " . number_format($size) . " bytes, แก้ไขล่าสุด: $modified</p>";
    } else {
        echo "<p>⚪ <strong>$log_file</strong>: ยังไม่มีไฟล์</p>";
    }
}

echo "<hr>";
echo "<h2>6. 📊 การตรวจสอบสถานะปัจจุบัน</h2>";

// ตรวจสอบการทำงานล่าสุด
require_once 'config.php';

try {
    $database = new DatabaseConfig();
    $db = $database->getConnection();
    
    // ข้อมูลล่าสุด
    $latest_query = "SELECT timestamp FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
    $latest_stmt = $db->prepare($latest_query);
    $latest_stmt->execute();
    $latest_data = $latest_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latest_data) {
        $last_update = strtotime($latest_data['timestamp']);
        $time_diff = time() - $last_update;
        
        echo "<p><strong>ข้อมูลล่าสุด:</strong> " . $latest_data['timestamp'];
        if ($time_diff < 300) {
            echo " <span style='color: green;'>✅ (อัปเดตล่าสุด " . $time_diff . " วินาทีที่แล้ว)</span>";
        } else {
            echo " <span style='color: red;'>⚠️ (ไม่มีข้อมูลมา " . floor($time_diff/60) . " นาทีแล้ว)</span>";
        }
        echo "</p>";
    }
    
    // Telegram notifications ล่าสุด
    $telegram_query = "SELECT COUNT(*) as count, MAX(timestamp) as last_sent 
                      FROM telegram_notifications 
                      WHERE DATE(timestamp) = CURDATE()";
    $telegram_stmt = $db->prepare($telegram_query);
    $telegram_stmt->execute();
    $telegram_data = $telegram_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>การแจ้งเตือนวันนี้:</strong> " . $telegram_data['count'] . " ครั้ง";
    if ($telegram_data['last_sent']) {
        echo " (ล่าสุด: " . $telegram_data['last_sent'] . ")";
    }
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ไม่สามารถตรวจสอบฐานข้อมูลได้: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h3>🎯 สรุปการตั้งค่า</h3>";
echo "<ol>";
echo "<li><strong>ตั้งค่า Cron Jobs</strong> ตามระบบปฏิบัติการที่ใช้</li>";
echo "<li><strong>ทดสอบการทำงาน</strong> ด้วยการรันไฟล์ด้วยตนเอง</li>";
echo "<li><strong>ติดตาม Log Files</strong> เพื่อตรวจสอบการทำงาน</li>";
echo "<li><strong>ตรวจสอบการแจ้งเตือน</strong> ใน Telegram</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
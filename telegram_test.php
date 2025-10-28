<?php
// telegram_test.php - ทดสอบการทำงานของระบบ Telegram
require_once 'telegram_config.php';
require_once 'telegram_notifications.php';

echo "<html><head><title>Telegram Bot Test</title></head><body>";
echo "<h1>🤖 Smart Parcel Box - Telegram Bot Test</h1>";
echo "<hr>";

// สร้าง instance
$telegram_config = new TelegramConfig();
$telegram_notifications = new TelegramNotifications();

// 1. ตรวจสอบการตั้งค่า
echo "<h2>1. 🔧 การตรวจสอบการตั้งค่า</h2>";
$config_validation = $telegram_config->validateConfig();

if ($config_validation['valid']) {
    echo "<p>✅ การตั้งค่าถูกต้อง</p>";
} else {
    echo "<p>❌ การตั้งค่าไม่ถูกต้อง:</p>";
    echo "<ul>";
    foreach ($config_validation['errors'] as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
    echo "<p><strong>แนะนำ:</strong> แก้ไขไฟล์ telegram_config.php</p>";
}

// 2. ทดสอบการเชื่อมต่อ Bot
echo "<h2>2. 🔗 การทดสอบการเชื่อมต่อ Bot</h2>";
$connection_test = $telegram_config->testConnection();

if ($connection_test['success']) {
    echo "<p>✅ เชื่อมต่อ Bot สำเร็จ</p>";
    echo "<p><strong>ข้อมูล Bot:</strong></p>";
    echo "<ul>";
    echo "<li>ชื่อ Bot: " . $connection_test['bot_info']['first_name'] . "</li>";
    echo "<li>Username: @" . $connection_test['bot_info']['username'] . "</li>";
    echo "<li>ID: " . $connection_test['bot_info']['id'] . "</li>";
    echo "</ul>";
} else {
    echo "<p>❌ ไม่สามารถเชื่อมต่อ Bot ได้</p>";
    echo "<p style='color: red;'>Error: " . $connection_test['error'] . "</p>";
    echo "<p><strong>แนะนำ:</strong></p>";
    echo "<ul>";
    echo "<li>ตรวจสอบ Bot Token</li>";
    echo "<li>ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</li>";
    echo "</ul>";
}

// 3. ทดสอบการส่งข้อความ
echo "<h2>3. 💬 การทดสอบการส่งข้อความ</h2>";

if ($config_validation['valid'] && $connection_test['success']) {
    echo "<p>กำลังส่งข้อความทดสอบ...</p>";
    
    $test_result = $telegram_notifications->testNotification();
    
    if ($test_result) {
        $success_count = 0;
        foreach ($test_result as $chat_type => $result) {
            if ($result && isset($result['ok']) && $result['ok']) {
                $success_count++;
                echo "<p>✅ ส่งไปยัง $chat_type สำเร็จ (Message ID: " . $result['result']['message_id'] . ")</p>";
            } else {
                echo "<p>❌ ส่งไปยัง $chat_type ไม่สำเร็จ: " . ($result['description'] ?? 'Unknown error') . "</p>";
            }
        }
        
        if ($success_count > 0) {
            echo "<p style='color: green;'><strong>🎉 การส่งข้อความสำเร็จ!</strong></p>";
        }
    } else {
        echo "<p>❌ ไม่สามารถส่งข้อความได้</p>";
    }
} else {
    echo "<p>⚠️ ข้ามการทดสอบเนื่องจากการตั้งค่าไม่ถูกต้อง</p>";
}

// 4. ทดสอบการแจ้งเตือนแต่ละประเภท
if ($config_validation['valid'] && $connection_test['success']) {
    echo "<h2>4. 🔔 การทดสอบการแจ้งเตือนแต่ละประเภท</h2>";
    
    $test_cases = [
        'Box Full' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifyBoxFull(8.5, 'RED ON - Parcel Full');
        },
        'Box Opened (Online)' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifyBoxOpened('online', 'GREEN_SWITCH', 25.3, true);
        },
        'Box Closed' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifyBoxClosed(35, 22.1);
        },
        'Motion Detected' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifyMotionDetected(18.7, 'YELLOW ON - Medium Space');
        },
        'Sensor Error' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifySensorError(5, 'Ultrasonic Timeout');
        },
        'System Status' => function() use ($telegram_notifications) {
            return $telegram_notifications->notifySystemStatus('online', 3600, -45);
        }
    ];
    
    echo "<p><strong>🚨 คำเตือน:</strong> การทดสอบนี้จะส่งข้อความจริงไปยัง Telegram</p>";
    echo "<p><a href='?run_tests=1' style='background: #ff6b6b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 เริ่มทดสอบการแจ้งเตือน</a></p>";
    
    if (isset($_GET['run_tests']) && $_GET['run_tests'] == '1') {
        echo "<h3>ผลการทดสอบ:</h3>";
        
        foreach ($test_cases as $test_name => $test_function) {
            echo "<p><strong>$test_name:</strong> ";
            
            $result = $test_function();
            
            if ($result && is_array($result)) {
                $success = false;
                foreach ($result as $res) {
                    if ($res && isset($res['ok']) && $res['ok']) {
                        $success = true;
                        break;
                    }
                }
                echo $success ? "✅ สำเร็จ" : "❌ ไม่สำเร็จ";
            } else {
                echo "❌ ไม่สำเร็จ";
            }
            echo "</p>";
            
            // รอ 2 วินาที ระหว่างการทดสอบ
            sleep(2);
        }
    }
}

// 5. สถิติการแจ้งเตือน
echo "<h2>5. 📊 สถิติการแจ้งเตือน (7 วันที่ผ่านมา)</h2>";
$stats = $telegram_notifications->getNotificationStats(7);

if (!empty($stats)) {
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ประเภทการแจ้งเตือน</th><th>สถานะ</th><th>จำนวน</th><th>ครั้งล่าสุด</th>";
    echo "</tr>";
    
    foreach ($stats as $stat) {
        $status_color = $stat['status'] === 'sent' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $stat['notification_type'] . "</td>";
        echo "<td style='color: $status_color;'>" . strtoupper($stat['status']) . "</td>";
        echo "<td>" . $stat['count'] . "</td>";
        echo "<td>" . $stat['last_sent'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>ไม่มีสถิติการแจ้งเตือน</p>";
}

// 6. คำแนะนำในการใช้งาน
echo "<hr>";
echo "<h2>6. 📋 คำแนะนำในการใช้งาน</h2>";
echo "<div style='background: #e8f4f8; padding: 15px; border-radius: 5px;'>";
echo "<h3>🚀 ขั้นตอนการติดตั้ง:</h3>";
echo "<ol>";
echo "<li><strong>สร้าง Bot:</strong> ไปที่ @BotFather บน Telegram และสร้าง Bot ใหม่</li>";
echo "<li><strong>ได้ Token:</strong> คัดลอก Bot Token มาใส่ในไฟล์ telegram_config.php</li>";
echo "<li><strong>หา Chat ID:</strong> ส่งข้อความให้ Bot แล้วใช้ URL: <code>https://api.telegram.org/bot[TOKEN]/getUpdates</code></li>";
echo "<li><strong>ตั้งค่า Chat ID:</strong> ใส่ Chat ID ในไฟล์ telegram_config.php</li>";
echo "<li><strong>ทดสอบ:</strong> รันไฟล์นี้เพื่อทดสอบการทำงาน</li>";
echo "</ol>";

echo "<h3>⚙️ การปรับแต่ง:</h3>";
echo "<ul>";
echo "<li><strong>Cooldown:</strong> ปรับระยะเวลาระหว่างการแจ้งเตือนใน telegram_notifications.php</li>";
echo "<li><strong>ข้อความ:</strong> แก้ไขรูปแบบข้อความในฟังก์ชัน notify*</li>";
echo "<li><strong>เงื่อนไข:</strong> ปรับเงื่อนไขการแจ้งเตือนใน upload_data.php</li>";
echo "</ul>";
echo "</div>";

// 7. ลิงค์เพิ่มเติม
echo "<h2>7. 🔗 ลิงค์เพิ่มเติม</h2>";
echo "<ul>";
echo "<li><a href='dashboard_api.php?action=latest_data'>ข้อมูลล่าสุด</a></li>";
echo "<li><a href='index.php'>Dashboard</a></li>";
echo "<li><a href='test_connection.php'>ทดสอบฐานข้อมูล</a></li>";
echo "</ul>";

echo "</body></html>";
?>
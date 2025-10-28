<?php
// telegram_notifications.php - ระบบแจ้งเตือน Telegram
require_once 'telegram_config.php';
require_once 'config.php';

class TelegramNotifications {
    private $telegram;
    private $db;
    private $notifications_enabled = true;
    private $last_notification_time = [];
    private $notification_cooldown = 300; // 5 นาที
    
    public function __construct() {
        $this->telegram = new TelegramConfig();
        
        $database = new DatabaseConfig();
        $this->db = $database->getConnection();
        
        // สร้างตารางการแจ้งเตือน Telegram (ถ้ายังไม่มี)
        $this->createTelegramTable();
    }
    
    // สร้างตารางสำหรับ Telegram notifications
    private function createTelegramTable() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS telegram_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                notification_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                chat_id VARCHAR(50),
                status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                telegram_message_id INT NULL,
                retry_count INT DEFAULT 0,
                last_retry DATETIME NULL,
                error_message TEXT NULL
            )";
            
            $this->db->exec($query);
        } catch (Exception $e) {
            error_log("Error creating Telegram table: " . $e->getMessage());
        }
    }
    
    // ส่งการแจ้งเตือนทั่วไป
    public function sendNotification($type, $message, $priority = 'normal') {
        if (!$this->notifications_enabled) {
            return false;
        }
        
        // ตรวจสอบ Cooldown
        if ($this->isInCooldown($type)) {
            return false;
        }
        
        // เพิ่ม Emoji และ formatting ตามประเภท
        $formatted_message = $this->formatMessage($type, $message, $priority);
        
        // ส่งข้อความ
        $result = $this->telegram->sendToMultiple($formatted_message);
        
        // บันทึกลงฐานข้อมูล
        $this->logNotification($type, $message, $result);
        
        // อัปเดตเวลา Cooldown
        $this->last_notification_time[$type] = time();
        
        return $result;
    }
    
    // แจ้งเตือนเมื่อกล่องเต็ม
    public function notifyBoxFull($distance, $lamp_status) {
        $message = "🚨 <b>กล่องพัสดุเต็มแล้ว!</b>\n\n";
        $message .= "📏 ระยะทาง: <code>{$distance} ซม.</code>\n";
        $message .= "💡 สถานะไฟ: <code>{$lamp_status}</code>\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>\n\n";
        $message .= "📦 กรุณานำพัสดุออกจากกล่อง";
        
        return $this->sendNotification('box_full', $message, 'high');
    }
    
    // แจ้งเตือนเมื่อมีการเปิดกล่อง
    public function notifyBoxOpened($mode, $trigger, $distance, $motion_detected) {
        $mode_text = $mode === 'online' ? '🌐 ออนไลน์' : '📱 ออฟไลน์';
        $trigger_text = $trigger === 'GREEN_SWITCH' ? '🟢 ปุ่มเขียว' : '🔴 ปุ่มแดง';
        $motion_text = $motion_detected ? '✅ ตรวจพบ' : '❌ ไม่พบ';
        
        $message = "🔓 <b>กล่องพัสดุถูกเปิด</b>\n\n";
        $message .= "🎯 โหมด: {$mode_text}\n";
        $message .= "🎛️ วิธีเปิด: {$trigger_text}\n";
        $message .= "📏 ระยะทาง: <code>{$distance} ซม.</code>\n";
        $message .= "🚶 การเคลื่อนไหว: {$motion_text}\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>";
        
        return $this->sendNotification('box_opened', $message, 'normal');
    }
    
    // แจ้งเตือนเมื่อกล่องปิดอัตโนมัติ
    public function notifyBoxClosed($duration, $distance) {
        $message = "🔒 <b>กล่องพัสดุปิดอัตโนมัติ</b>\n\n";
        $message .= "⏱️ เปิดค้าง: <code>{$duration} วินาที</code>\n";
        $message .= "📏 ระยะทางปัจจุบัน: <code>{$distance} ซม.</code>\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>";
        
        return $this->sendNotification('box_closed', $message, 'low');
    }
    
    // แจ้งเตือนเมื่อตรวจพบการเคลื่อนไหว
    public function notifyMotionDetected($distance, $lamp_status) {
        $message = "🚶 <b>ตรวจพบการเคลื่อนไหว</b>\n\n";
        $message .= "📏 ระยะทางปัจจุบัน: <code>{$distance} ซม.</code>\n";
        $message .= "💡 สถานะไฟ: <code>{$lamp_status}</code>\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>";
        
        return $this->sendNotification('motion_detected', $message, 'low');
    }
    
    // แจ้งเตือนข้อผิดพลาดของเซ็นเซอร์
    public function notifySensorError($error_count, $error_type) {
        $message = "⚠️ <b>ข้อผิดพลาดเซ็นเซอร์</b>\n\n";
        $message .= "🔧 ประเภทข้อผิดพลาด: <code>{$error_type}</code>\n";
        $message .= "🔢 จำนวนครั้ง: <code>{$error_count}</code>\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>\n\n";
        $message .= "🛠️ กรุณาตรวจสอบการเชื่อมต่อเซ็นเซอร์";
        
        return $this->sendNotification('sensor_error', $message, 'high');
    }
    
    // แจ้งเตือนสถานะระบบออนไลน์/ออฟไลน์
    public function notifySystemStatus($status, $uptime = null, $wifi_signal = null) {
        $status_icon = $status === 'online' ? '🟢' : '🔴';
        $status_text = $status === 'online' ? 'ออนไลน์' : 'ออฟไลน์';
        
        $message = "{$status_icon} <b>ระบบ: {$status_text}</b>\n\n";
        $message .= "⏰ เวลา: <code>" . date('Y-m-d H:i:s') . "</code>\n";
        
        if ($uptime) {
            $message .= "🕐 Uptime: <code>{$uptime} วินาที</code>\n";
        }
        
        if ($wifi_signal) {
            $message .= "📶 สัญญาณ WiFi: <code>{$wifi_signal} dBm</code>\n";
        }
        
        $priority = $status === 'online' ? 'normal' : 'high';
        
        return $this->sendNotification('system_status', $message, $priority);
    }
    
    // ส่งสถิติรายวัน
    public function sendDailyReport($stats) {
        $message = "📊 <b>รายงานประจำวัน</b>\n";
        $message .= "📅 วันที่: <code>" . date('Y-m-d') . "</code>\n\n";
        
        $message .= "📦 <b>การใช้งาน:</b>\n";
        $message .= "• เปิดกล่องทั้งหมด: <code>{$stats['total_operations']} ครั้ง</code>\n";
        $message .= "• โหมดออนไลน์: <code>{$stats['online_operations']} ครั้ง</code>\n";
        $message .= "• โหมดออฟไลน์: <code>{$stats['offline_operations']} ครั้ง</code>\n";
        $message .= "• ปิดอัตโนมัติ: <code>{$stats['auto_closes']} ครั้ง</code>\n\n";
        
        if (isset($stats['avg_distance'])) {
            $message .= "📏 <b>เซ็นเซอร์:</b>\n";
            $message .= "• ระยะทางเฉลี่ย: <code>" . number_format($stats['avg_distance'], 1) . " ซม.</code>\n";
            $message .= "• ระยะทางต่ำสุด: <code>" . number_format($stats['min_distance'], 1) . " ซม.</code>\n";
            $message .= "• ระยะทางสูงสุด: <code>" . number_format($stats['max_distance'], 1) . " ซม.</code>\n";
            $message .= "• ตรวจจับการเคลื่อนไหว: <code>{$stats['motion_detections']} ครั้ง</code>\n";
        }
        
        return $this->sendNotification('daily_report', $message, 'low');
    }
    
    // จัดรูปแบบข้อความตามประเภท
    private function formatMessage($type, $message, $priority) {
        $priority_icons = [
            'low' => '💙',
            'normal' => '💛', 
            'high' => '❤️',
            'critical' => '🚨'
        ];
        
        $icon = $priority_icons[$priority] ?? '📋';
        
        $header = "🏠 <b>Smart Parcel Box</b> {$icon}\n";
        $header .= "━━━━━━━━━━━━━━━━━\n";
        
        $footer = "\n━━━━━━━━━━━━━━━━━\n";
        $footer .= "🤖 ระบบแจ้งเตือนอัตโนมัติ";
        
        return $header . $message . $footer;
    }
    
    // ตรวจสอบ Cooldown
    private function isInCooldown($type) {
        if (!isset($this->last_notification_time[$type])) {
            return false;
        }
        
        $time_diff = time() - $this->last_notification_time[$type];
        return $time_diff < $this->notification_cooldown;
    }
    
    // บันทึกการแจ้งเตือนลงฐานข้อมูล
    private function logNotification($type, $message, $result) {
        try {
            $status = 'failed';
            $telegram_message_id = null;
            $error_message = null;
            
            if ($result && is_array($result)) {
                foreach ($result as $chat_result) {
                    if ($chat_result && isset($chat_result['ok']) && $chat_result['ok']) {
                        $status = 'sent';
                        $telegram_message_id = $chat_result['result']['message_id'] ?? null;
                        break;
                    } else {
                        $error_message = $chat_result['description'] ?? 'Unknown error';
                    }
                }
            }
            
            $query = "INSERT INTO telegram_notifications 
                     (notification_type, message, status, telegram_message_id, error_message) 
                     VALUES (:type, :message, :status, :msg_id, :error)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':msg_id', $telegram_message_id);
            $stmt->bindParam(':error', $error_message);
            
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Error logging Telegram notification: " . $e->getMessage());
        }
    }
    
    // เปิด/ปิดการแจ้งเตือน
    public function enableNotifications($enable = true) {
        $this->notifications_enabled = $enable;
    }
    
    // ตั้งค่า Cooldown
    public function setCooldown($seconds) {
        $this->notification_cooldown = $seconds;
    }
    
    // ทดสอบการส่งข้อความ
    public function testNotification() {
        $message = "🧪 <b>ทดสอบระบบแจ้งเตือน</b>\n\n";
        $message .= "✅ ระบบ Telegram ทำงานปกติ\n";
        $message .= "⏰ เวลาทดสอบ: <code>" . date('Y-m-d H:i:s') . "</code>";
        
        return $this->telegram->sendToMultiple($message);
    }
    
    // ดึงสถิติการแจ้งเตือน
    public function getNotificationStats($days = 7) {
        try {
            $query = "SELECT 
                        notification_type,
                        status,
                        COUNT(*) as count,
                        MAX(timestamp) as last_sent
                      FROM telegram_notifications 
                      WHERE timestamp >= DATE_SUB(NOW(), INTERVAL :days DAY)
                      GROUP BY notification_type, status
                      ORDER BY notification_type, status";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting notification stats: " . $e->getMessage());
            return [];
        }
    }
}
?>
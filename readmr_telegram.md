# 📱 Smart Parcel Box - Telegram Notification System

## 🚀 คุณสมบัติใหม่ที่เพิ่มเข้ามา

### การแจ้งเตือนอัตโนมัติ
- 🚨 กล่องเต็ม (ระยะทาง ≤ 10 ซม.)
- 🚶 ตรวจพบการเคลื่อนไหว
- 🔓 เปิด-ปิดกล่อง (ทั้งโหมดออนไลน์และออฟไลน์)
- ⚠️ ข้อผิดพลาดเซ็นเซอร์
- 🌐 สถานะระบบออนไลน์/ออฟไลน์
- 📊 รายงานประจำวัน

### ระบบความปลอดภัย
- ⏰ Cooldown 5 นาที (ป้องกันการแจ้งเตือนซ้ำ)
- 🛡️ การจัดการข้อผิดพลาด
- 📝 บันทึก log ครบถ้วน
- 🔍 ตรวจสอบสุขภาพระบบอัตโนมัติ

---

## 🔧 การติดตั้ง

### ขั้นตอนที่ 1: สร้าง Telegram Bot

1. **เปิด Telegram** และค้นหา `@BotFather`
2. **ส่งคำสั่ง** `/newbot`
3. **ตั้งชื่อ Bot** เช่น "Smart Parcel Box Bot"
4. **ตั้ง Username** เช่น "smart_parcel_box_bot"
5. **คัดลอก Bot Token** ที่ได้รับ

### ขั้นตอนที่ 2: หา Chat ID

1. **ส่งข้อความ** ให้ Bot ที่สร้างใหม่
2. **เปิด URL** ในเบราว์เซอร์:
   ```
   https://api.telegram.org/bot[YOUR_BOT_TOKEN]/getUpdates
   ```
3. **คัดลอก Chat ID** จากผลลัพธ์ (ตัวเลขในช่อง "id")

### ขั้นตอนที่ 3: ติดตั้งไฟล์ใหม่

คัดลอกไฟล์ต่อไปนี้ไปยังโฟลเดอร์เดียวกับระบบเดิม:

```
telegram_config.php
telegram_notifications.php
error_report.php
daily_report.php
system_health_check.php
cleanup_old_data.php
telegram_dashboard.php
setup_cron.php
telegram_test.php
```

### ขั้นตอนที่ 4: แก้ไขไฟล์เดิม

**อัปเดตไฟล์เหล่านี้:**
- `upload_data.php` (เวอร์ชันใหม่)
- `operation_log.php` (เวอร์ชันใหม่)

### ขั้นตอนที่ 5: ตั้งค่า Telegram

1. **เปิดไฟล์** `telegram_config.php`
2. **แก้ไขบรรทัดเหล่านี้:**
   ```php
   private $bot_token = 'YOUR_BOT_TOKEN_HERE'; // ใส่ Bot Token
   private $chat_id = 'YOUR_CHAT_ID_HERE';     // ใส่ Chat ID
   ```
3. **บันทึกไฟล์**

### ขั้นตอนที่ 6: ทดสอบระบบ

1. **เปิดเบราว์เซอร์** ไปที่ `telegram_test.php`
2. **ตรวจสอบการตั้งค่า** ให้แสดงเครื่องหมายถูกสีเขียว
3. **ทดสอบการส่งข้อความ** โดยคลิก "ทดสอบ"

---

## ⚙️ การตั้งค่า Cron Jobs (สำคัญ!)

### สำหรับ Linux/Unix Server

เปิด crontab:
```bash
crontab -e
```

เพิ่มบรรทัดเหล่านี้:
```bash
# รายงานประจำวัน เวลา 08:00
0 8 * * * /usr/bin/php /path/to/your/project/daily_report.php

# ตรวจสอบสุขภาพระบบ ทุก 30 นาที
*/30 * * * * /usr/bin/php /path/to/your/project/system_health_check.php

# ทำความสะอาดข้อมูลเก่า ทุกวันอาทิตย์ เวลา 02:00
0 2 * * 0 /usr/bin/php /path/to/your/project/cleanup_old_data.php
```

### สำหรับ Windows Server

1. สร้างไฟล์ `.bat`
2. ตั้งค่าใน Task Scheduler
3. ดูรายละเอียดใน `setup_cron.php`

### สำหรับ Shared Hosting

ใช้ cPanel Cron Jobs หรือ Web Cron Service กับ URLs:
- `your-domain.com/path/daily_report.php`
- `your-domain.com/path/system_health_check.php`

---

## 📊 การใช้งาน

### Dashboard หลัก
- **URL:** `telegram_dashboard.php`
- **คุณสมบัติ:** จัดการการแจ้งเตือน, ดูสถิติ, ทดสอบระบบ

### การแจ้งเตือนที่จะได้รับ

#### 🚨 กล่องเต็ม
```
🚨 กล่องพัสดุเต็มแล้ว!

📏 ระยะทาง: 8.5 ซม.
💡 สถานะไฟ: RED ON - Parcel Full
⏰ เวลา: 2025-01-15 14:30:25

📦 กรุณานำพัสดุออกจากกล่อง
```

#### 🔓 เปิดกล่อง
```
🔓 กล่องพัสดุถูกเปิด

🎯 โหมด: 🌐 ออนไลน์
🎛️ วิธีเปิด: 🟢 ปุ่มเขียว
📏 ระยะทาง: 25.3 ซม.
🚶 การเคลื่อนไหว: ✅ ตรวจพบ
⏰ เวลา: 2025-01-15 14:35:10
```

#### 📊 รายงานประจำวัน
```
📊 รายงานประจำวัน
📅 วันที่: 2025-01-15

📦 การใช้งาน:
• เปิดกล่องทั้งหมด: 12 ครั้ง
• โหมดออนไลน์: 8 ครั้ง
• โหมดออฟไลน์: 4 ครั้ง
• ปิดอัตโนมัติ: 12 ครั้ง

📏 เซ็นเซอร์:
• ระยะทางเฉลี่ย: 22.5 ซม.
• ระยะทางต่ำสุด: 8.2 ซม.
• ระยะทางสูงสุด: 38.7 ซม.
• ตรวจจับการเคลื่อนไหว: 45 ครั้ง
```

---

## 🛠️ การแก้ไขปัญหา

### ปัญหาที่พบบ่อย

#### 1. Bot ไม่ตอบสนอง
**สาเหตุ:**
- Bot Token ไม่ถูกต้อง
- Chat ID ไม่ถูกต้อง
- Bot ถูกบล็อค

**วิธีแก้:**
```php
// ตรวจสอบใน telegram_config.php
private $bot_token = 'ใส่ Token ที่ถูกต้อง';
private $chat_id = 'ใส่ Chat ID ที่ถูกต้อง';
```

#### 2. การแจ้งเตือนซ้ำเยอะ
**สาเหตุ:** Cooldown น้อยเกินไป

**วิธีแก้:**
```php
// ใน telegram_notifications.php
private $notification_cooldown = 600; // เพิ่มเป็น 10 นาที
```

#### 3. ไม่ได้รับรายงานประจำวัน
**สาเหตุ:** Cron Job ไม่ทำงาน

**วิธีแก้:**
- ตรวจสอบ crontab: `crontab -l`
- ทดสอบ manual: `php daily_report.php`
- ตรวจสอบ log: `tail -f /var/log/cron.log`

#### 4. ข้อผิดพลาดฐานข้อมูล
**สาเหตุ:** ตารางใหม่ยังไม่ถูกสร้าง

**วิธีแก้:**
1. เรียกใช้ `create_database.php` ใหม่
2. หรือรัน SQL ด้วยตัวเอง:
```sql
CREATE TABLE IF NOT EXISTS telegram_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    chat_id VARCHAR(50),
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    telegram_message_id INT NULL,
    retry_count INT DEFAULT 0,
    error_message TEXT NULL
);
```

---

## 📈 การปรับแต่งขั้นสูง

### 1. เพิ่ม Chat ID หลายตัว

```php
// ใน telegram_config.php
private $chat_ids = [
    'admin' => 'ADMIN_CHAT_ID',
    'group' => 'GROUP_CHAT_ID',
    'backup' => 'BACKUP_CHAT_ID'
];

public function sendToAll($message) {
    foreach($this->chat_ids as $name => $chat_id) {
        $this->sendMessage($message, $chat_id);
    }
}
```

### 2. การแจ้งเตือนตามระดับความสำคัญ

```php
// ปรับแต่งใน telegram_notifications.php
private function getRecipients($priority) {
    switch($priority) {
        case 'critical':
            return ['admin', 'group', 'backup']; // ส่งทุกที่
        case 'high':
            return ['admin', 'group']; // ส่งแอดมินและกลุ่ม
        case 'normal':
            return ['admin']; // ส่งแค่แอดมิน
        default:
            return ['admin'];
    }
}
```

### 3. การตั้งเวลาแจ้งเตือนเฉพาะช่วงเวลา

```php
private function isQuietHours() {
    $hour = date('H');
    return ($hour >= 22 || $hour <= 6); // เวลา 22:00 - 06:00
}

public function sendNotification($type, $message, $priority = 'normal') {
    if ($priority !== 'critical' && $this->isQuietHours()) {
        return false; // ไม่ส่งในเวลาเงียบ
    }
    // ... ส่วนที่เหลือ
}
```

---

## 🔒 ความปลอดภัย

### การป้องกันข้อมูล
- **Bot Token:** ไม่แชร์กับคนอื่น
- **Chat ID:** เป็นข้อมูลส่วนตัว
- **File Permissions:** ตั้งค่า 600 สำหรับ config files

### การสำรองข้อมูล
```bash
# Backup ฐานข้อมูล
mysqldump -u root -p smart_parcel_box > backup_$(date +%Y%m%d).sql

# Backup โค้ด
tar -czf code_backup_$(date +%Y%m%d).tar.gz *.php
```

---

## 📞 การติดต่อสำหรับความช่วยเหลือ

### เว็บไฟล์สำคัญ
- `telegram_test.php` - ทดสอบการทำงาน
- `telegram_dashboard.php` - จัดการระบบ
- `setup_cron.php` - ตั้งค่า Cron Jobs

### Log Files ที่ควรตรวจสอบ
- `health_check.log` - สุขภาพระบบ
- `daily_report.log` - รายงานประจำวัน  
- `cleanup.log` - การทำความสะอาด
- `telegram_errors.log` - ข้อผิดพลาด Telegram

### การติดตาม
- ตรวจสอบ Dashboard ทุกวัน
- อ่าน Log files เป็นประจำ
- ทดสอบการแจ้งเตือนสัปดาห์ละครั้ง

---

## ✅ Checklist การติดตั้ง

- [ ] สร้าง Telegram Bot แล้ว
- [ ] ได้ Bot Token และ Chat ID แล้ว  
- [ ] คัดลอกไฟล์ใหม่แล้ว
- [ ] แก้ไข telegram_config.php แล้ว
- [ ] ทดสอบใน telegram_test.php ผ่าน
- [ ] ตั้งค่า Cron Jobs แล้ว
- [ ] ได้รับข้อความทดสอบใน Telegram แล้ว
- [ ] ระบบส่งแจ้งเตือนอัตโนมัติแล้ว

**🎉 เมื่อทำครบแล้ว ระบบแจ้งเตือน Telegram พร้อมใช้งาน!**
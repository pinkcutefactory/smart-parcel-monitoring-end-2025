<?php
// telegram_dashboard.php - หน้าจัดการระบบแจ้งเตือน Telegram
require_once 'config.php';
require_once 'telegram_notifications.php';

$database = new DatabaseConfig();
$db = $database->getConnection();
$telegram = new TelegramNotifications();

// จัดการ Actions
$message = '';
if ($_POST) {
    try {
        if (isset($_POST['test_notification'])) {
            $result = $telegram->testNotification();
            $message = $result ? 'ส่งข้อความทดสอบสำเร็จ' : 'ส่งข้อความทดสอบไม่สำเร็จ';
        }
        
        if (isset($_POST['send_manual_alert'])) {
            $alert_text = $_POST['alert_message'] ?? '';
            if (!empty($alert_text)) {
                $result = $telegram->sendNotification('manual_alert', $alert_text, 'normal');
                $message = $result ? 'ส่งการแจ้งเตือนสำเร็จ' : 'ส่งการแจ้งเตือนไม่สำเร็จ';
            }
        }
        
        if (isset($_POST['enable_notifications'])) {
            $enable = $_POST['notification_status'] === 'enabled';
            $telegram->enableNotifications($enable);
            $message = $enable ? 'เปิดใช้งานการแจ้งเตือน' : 'ปิดใช้งานการแจ้งเตือน';
        }
        
        if (isset($_POST['set_cooldown'])) {
            $cooldown = intval($_POST['cooldown_seconds']);
            if ($cooldown >= 60 && $cooldown <= 3600) {
                $telegram->setCooldown($cooldown);
                $message = "ตั้งค่า Cooldown เป็น {$cooldown} วินาที";
            }
        }
        
    } catch (Exception $e) {
        $message = 'ข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ดึงสถิติการแจ้งเตือน
$stats = $telegram->getNotificationStats(7);
$stats_today = $telegram->getNotificationStats(1);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Notification Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans Thai', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2d3748;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #48bb78, #38a169);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #f56565, #e53e3e);
        }
        
        .btn-warning {
            background: linear-gradient(45deg, #ed8936, #d69e2e);
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            background: rgba(247, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th,
        .table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        
        .table th {
            background: rgba(237, 242, 247, 0.8);
            font-weight: 600;
            color: #2d3748;
        }
        
        .status-sent {
            color: #38a169;
            font-weight: 600;
        }
        
        .status-failed {
            color: #e53e3e;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Telegram Notification Dashboard</h1>
            <p>จัดการระบบแจ้งเตือน Smart Parcel Box</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'ข้อผิดพลาด') !== false ? 'alert-error' : 'alert-success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <!-- Test & Manual Control -->
            <div class="card">
                <div class="card-title">🧪 ทดสอบและควบคุม</div>
                
                <form method="POST">
                    <div class="form-group">
                        <button type="submit" name="test_notification" class="btn btn-success">
                            ทดสอบการแจ้งเตือน
                        </button>
                    </div>
                </form>
                
                <form method="POST">
                    <div class="form-group">
                        <label>ส่งการแจ้งเตือนด้วยตนเอง:</label>
                        <textarea name="alert_message" placeholder="ข้อความที่ต้องการส่ง..."></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="send_manual_alert" class="btn btn-warning">
                            ส่งการแจ้งเตือน
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Settings -->
            <div class="card">
                <div class="card-title">⚙️ การตั้งค่า</div>
                
                <form method="POST">
                    <div class="form-group">
                        <label>สถานะการแจ้งเตือน:</label>
                        <select name="notification_status">
                            <option value="enabled">เปิดใช้งาน</option>
                            <option value="disabled">ปิดใช้งาน</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="enable_notifications" class="btn">
                            บันทึกการตั้งค่า
                        </button>
                    </div>
                </form>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Cooldown (วินาที):</label>
                        <input type="number" name="cooldown_seconds" min="60" max="3600" value="300" placeholder="300">
                        <small>เวลาระหว่างการแจ้งเตือนแต่ละครั้ง</small>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="set_cooldown" class="btn">
                            ตั้งค่า Cooldown
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Today -->
            <div class="card">
                <div class="card-title">📊 สถิติวันนี้</div>
                <div class="stats-grid">
                    <?php
                    $today_stats = [];
                    foreach ($stats_today as $stat) {
                        $today_stats[$stat['status']] = ($today_stats[$stat['status']] ?? 0) + $stat['count'];
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-value"><?= $today_stats['sent'] ?? 0 ?></div>
                        <div class="stat-label">ส่งสำเร็จ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $today_stats['failed'] ?? 0 ?></div>
                        <div class="stat-label">ส่งไม่สำเร็จ</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= array_sum($today_stats) ?></div>
                        <div class="stat-label">รวมทั้งหมด</div>
                    </div>
                </div>
            </div>
            
            <!-- Bot Info -->
            <div class="card">
                <div class="card-title">🤖 ข้อมูล Bot</div>
                <?php
                $telegram_config = new TelegramConfig();
                $bot_test = $telegram_config->testConnection();
                ?>
                
                <?php if ($bot_test['success']): ?>
                    <p><strong>ชื่อ Bot:</strong> <?= htmlspecialchars($bot_test['bot_info']['first_name']) ?></p>
                    <p><strong>Username:</strong> @<?= htmlspecialchars($bot_test['bot_info']['username']) ?></p>
                    <p><strong>Bot ID:</strong> <?= htmlspecialchars($bot_test['bot_info']['id']) ?></p>
                    <p><span style="color: green;">✅ เชื่อมต่อปกติ</span></p>
                <?php else: ?>
                    <p><span style="color: red;">❌ ไม่สามารถเชื่อมต่อได้</span></p>
                    <p><strong>Error:</strong> <?= htmlspecialchars($bot_test['error'] ?? 'Unknown error') ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notification History -->
        <div class="card">
            <div class="card-title">📋 ประวัติการแจ้งเตือน (7 วันที่ผ่านมา)</div>
            
            <?php if (!empty($stats)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ประเภท</th>
                            <th>สถานะ</th>
                            <th>จำนวน</th>
                            <th>ครั้งล่าสุด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><?= htmlspecialchars($stat['notification_type']) ?></td>
                                <td>
                                    <span class="status-<?= $stat['status'] ?>">
                                        <?= strtoupper($stat['status']) ?>
                                    </span>
                                </td>
                                <td><?= $stat['count'] ?></td>
                                <td><?= $stat['last_sent'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #718096; padding: 20px;">
                    ไม่มีประวัติการแจ้งเตือน
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Quick Links -->
        <div class="card">
            <div class="card-title">🔗 ลิงค์เพิ่มเติม</div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <a href="index.php" class="btn">📊 Dashboard หลัก</a>
                <a href="telegram_test.php" class="btn">🧪 ทดสอบ Telegram</a>
                <a href="setup_cron.php" class="btn">⏰ ตั้งค่า Cron Job</a>
                <a href="test_connection.php" class="btn">🔧 ทดสอบฐานข้อมูล</a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = e.target.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.textContent = 'กำลังดำเนินการ...';
                }
            });
        });
    </script>
</body>
</html>
<?php
// telegram_config.php - การตั้งค่า Telegram Bot
class TelegramConfig {
    // ใส่ Token ของ Bot ที่ได้จาก @BotFather
    private $bot_token = '8059110334:AAFVe2EHW9prJjOD2BGzaFy2cTNUfB4EbgY'; // เปลี่ยนตรงนี้
    
    // ใส่ Chat ID ของผู้ที่จะรับการแจ้งเตือน
    private $chat_id = '7664878209'; // เปลี่ยนตรงนี้
    
    // Chat ID สำหรับกลุ่ม (ถ้ามี)
    private $group_chat_id = 'YOUR_GROUP_CHAT_ID_HERE'; // เปลี่ยนตรงนี้ (optional)
    
    private $api_url;
    
    public function __construct() {
        $this->api_url = "https://api.telegram.org/bot{$this->bot_token}";
    }
    
    public function getBotToken() {
        return $this->bot_token;
    }
    
    public function getChatId() {
        return $this->chat_id;
    }
    
    public function getGroupChatId() {
        return $this->group_chat_id;
    }
    
    public function getApiUrl() {
        return $this->api_url;
    }
    
    // ส่งข้อความ Telegram
    public function sendMessage($message, $chat_id = null, $parse_mode = 'HTML') {
        $target_chat_id = $chat_id ?: $this->chat_id;
        
        $data = [
            'chat_id' => $target_chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    // ส่งข้อความไปหลาย Chat
    public function sendToMultiple($message, $parse_mode = 'HTML') {
        $results = [];
        
        // ส่งไปยัง Chat ID หลัก
        if (!empty($this->chat_id)) {
            $results['main'] = $this->sendMessage($message, $this->chat_id, $parse_mode);
        }
        
        // ส่งไปยัง Group (ถ้ามี)
        if (!empty($this->group_chat_id) && $this->group_chat_id !== $this->chat_id) {
            $results['group'] = $this->sendMessage($message, $this->group_chat_id, $parse_mode);
        }
        
        return $results;
    }
    
    // ส่งข้อความพร้อมปุ่ม Inline Keyboard
    public function sendMessageWithButtons($message, $buttons, $chat_id = null) {
        $target_chat_id = $chat_id ?: $this->chat_id;
        
        $keyboard = ['inline_keyboard' => $buttons];
        
        $data = [
            'chat_id' => $target_chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ];
        
        return $this->makeRequest('sendMessage', $data);
    }
    
    // ส่งรูปภาพ
    public function sendPhoto($photo_url, $caption = '', $chat_id = null) {
        $target_chat_id = $chat_id ?: $this->chat_id;
        
        $data = [
            'chat_id' => $target_chat_id,
            'photo' => $photo_url,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        return $this->makeRequest('sendPhoto', $data);
    }
    
    // ทดสอบการเชื่อมต่อ
    public function testConnection() {
        $response = $this->makeRequest('getMe');
        
        if ($response && $response['ok']) {
            return [
                'success' => true,
                'bot_info' => $response['result']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['description'] ?? 'Unknown error'
        ];
    }
    
    // ทำ HTTP Request ไปยัง Telegram API
    private function makeRequest($method, $data = []) {
        $url = $this->api_url . '/' . $method;
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            error_log("Telegram API Error: Unable to connect");
            return false;
        }
        
        return json_decode($result, true);
    }
    
    // ตรวจสอบการตั้งค่า
    public function validateConfig() {
        $errors = [];
        
        if (empty($this->bot_token) || $this->bot_token === 'YOUR_BOT_TOKEN_HERE') {
            $errors[] = 'Bot Token is not configured';
        }
        
        if (empty($this->chat_id) || $this->chat_id === 'YOUR_CHAT_ID_HERE') {
            $errors[] = 'Chat ID is not configured';
        }
        
        return empty($errors) ? ['valid' => true] : ['valid' => false, 'errors' => $errors];
    }
}
?>
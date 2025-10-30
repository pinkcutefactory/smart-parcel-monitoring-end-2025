/*
 * ═══════════════════════════════════════════════════════════
 * ESP32-CAM Smart Parcel Camera System
 * ระบบกล้องแจ้งเตือนพัสดุอัจฉริยะ - Telegram Integration
 * 
 * Version: 2.0 Optimized & Clean
 * Author: Final Day Project Team
 * ═══════════════════════════════════════════════════════════
 */

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include "esp_camera.h"
#include "time.h"
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - Network
// ═══════════════════════════════════════════════════════════
const char* WIFI_SSID = "pinkcute";
const char* WIFI_PASSWORD = "101245170946";

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - Telegram Bot
// ═══════════════════════════════════════════════════════════
const char* TELEGRAM_BOT_TOKEN = "8059110334:AAFVe2EHW9prJjOD2BGzaFy2cTNUfB4EbgY";
const char* TELEGRAM_CHAT_ID = "7664878209";

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - Camera Pins (AI-Thinker Model)
// ═══════════════════════════════════════════════════════════
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22
#define LED_PIN            4

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - System Parameters
// ═══════════════════════════════════════════════════════════
const int TELEGRAM_MAX_RETRY = 3;
const int TELEGRAM_TIMEOUT_MS = 20000;
const int WIFI_RECONNECT_DELAY = 30000;
const int HEARTBEAT_INTERVAL = 60000;
const int UPLOAD_CHUNK_SIZE = 1024;

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - Time Settings
// ═══════════════════════════════════════════════════════════
const char* NTP_SERVER = "pool.ntp.org";
const long GMT_OFFSET_SEC = 25200;  // GMT+7 Thailand
const int DAYLIGHT_OFFSET_SEC = 0;

// ═══════════════════════════════════════════════════════════
// GLOBAL OBJECTS
// ═══════════════════════════════════════════════════════════
WiFiClientSecure telegramClient;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES
// ═══════════════════════════════════════════════════════════
struct SystemState {
  bool wifiConnected = false;
  bool cameraReady = false;
  int photoCount = 0;
  int failureCount = 0;
  unsigned long lastHeartbeat = 0;
  unsigned long lastReconnectAttempt = 0;
} state;

// ═══════════════════════════════════════════════════════════
// FUNCTION DECLARATIONS
// ═══════════════════════════════════════════════════════════
void setupSystem();
void setupWiFi();
void setupCamera();
void setupTime();
void loopSystem();
void processCommand();
void sendResponse(const String& msg);
void checkHeartbeat();
void checkWiFiStatus();

bool captureAndSendPhoto(const String& caption);
bool sendPhotoToTelegram(const uint8_t* data, size_t size, const String& caption);
String formatSensorData(const String& raw);
String getCurrentTime();
void indicateLED(int blinks);

// ═══════════════════════════════════════════════════════════
// SETUP
// ═══════════════════════════════════════════════════════════
void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  delay(2000);
  
  Serial.begin(115200);
  delay(500);
  
  Serial.println("\n╔═══════════════════════════════════════════╗");
  Serial.println("║   ESP32-CAM v2.0 - Clean & Optimized     ║");
  Serial.println("║   Smart Parcel Camera System             ║");
  Serial.println("╚═══════════════════════════════════════════╝\n");
  
  setupSystem();
}

// ═══════════════════════════════════════════════════════════
// MAIN LOOP
// ═══════════════════════════════════════════════════════════
void loop() {
  loopSystem();
}

// ═══════════════════════════════════════════════════════════
// SETUP FUNCTIONS
// ═══════════════════════════════════════════════════════════

void setupSystem() {
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);
  
  indicateLED(2);
  setupWiFi();
  setupCamera();
  
  if (state.wifiConnected) setupTime();
  
  Serial.println("\n╔═══════════════════════════════════════════╗");
  Serial.println("║            SYSTEM READY                   ║");
  Serial.println("╚═══════════════════════════════════════════╝");
  Serial.printf("WiFi: %s | Camera: %s\n", 
    state.wifiConnected ? "✓" : "✗",
    state.cameraReady ? "✓" : "✗");
  Serial.println("\nCommands: CAPTURE_ONLINE|data, CAPTURE_OFFLINE, PING, STATUS\n");
  
  indicateLED(state.wifiConnected && state.cameraReady ? 3 : 5);
  
  String status = state.wifiConnected && state.cameraReady ? "READY" : "ERROR";
  sendResponse(status + "|WiFi:" + (state.wifiConnected ? "OK" : "FAIL") + 
               "|Cam:" + (state.cameraReady ? "OK" : "FAIL"));
}

void setupWiFi() {
  Serial.printf("⚙️  WiFi: %s\n", WIFI_SSID);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts++ < 20) {
    delay(500);
    Serial.print(".");
    digitalWrite(LED_PIN, attempts % 2);
  }
  
  digitalWrite(LED_PIN, LOW);
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    state.wifiConnected = true;
    Serial.printf("   ✓ Connected | IP: %s | RSSI: %d dBm\n", 
      WiFi.localIP().toString().c_str(), WiFi.RSSI());
    telegramClient.setInsecure();
  } else {
    Serial.println("   ✗ Connection failed");
  }
}

void setupCamera() {
  Serial.println("⚙️  Initializing camera...");
  
  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sscb_sda = SIOD_GPIO_NUM;
  config.pin_sscb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;
  
  bool hasPSRAM = psramFound();
  config.frame_size = hasPSRAM ? FRAMESIZE_SVGA : FRAMESIZE_VGA;
  config.jpeg_quality = hasPSRAM ? 10 : 12;
  config.fb_count = hasPSRAM ? 2 : 1;
  
  Serial.printf("   PSRAM: %s\n", hasPSRAM ? "Yes (High Quality)" : "No (Standard)");
  
  if (esp_camera_init(&config) != ESP_OK) {
    Serial.println("   ✗ Camera init failed");
    return;
  }
  
  state.cameraReady = true;
  Serial.println("   ✓ Camera ready");
  
  // Optimize sensor settings
  sensor_t* s = esp_camera_sensor_get();
  if (s) {
    s->set_whitebal(s, 1);
    s->set_awb_gain(s, 1);
    s->set_exposure_ctrl(s, 1);
    s->set_gain_ctrl(s, 1);
    s->set_lenc(s, 1);
  }
}

void setupTime() {
  Serial.println("⚙️  Syncing time...");
  configTime(GMT_OFFSET_SEC, DAYLIGHT_OFFSET_SEC, NTP_SERVER);
  
  struct tm timeinfo;
  if (getLocalTime(&timeinfo)) {
    Serial.println(&timeinfo, "   ✓ %Y-%m-%d %H:%M:%S");
  } else {
    Serial.println("   ⚠️  Time sync failed");
  }
}

// ═══════════════════════════════════════════════════════════
// LOOP FUNCTIONS
// ═══════════════════════════════════════════════════════════

void loopSystem() {
  if (Serial.available()) processCommand();
  checkHeartbeat();
  checkWiFiStatus();
  delay(100);
}

void checkHeartbeat() {
  if (millis() - state.lastHeartbeat < HEARTBEAT_INTERVAL) return;
  
  state.lastHeartbeat = millis();
  Serial.printf("💓 Heartbeat | WiFi: %d dBm | Photos: %d | Fails: %d\n", 
    WiFi.RSSI(), state.photoCount, state.failureCount);
  sendResponse("HEARTBEAT|OK");
}

void checkWiFiStatus() {
  if (state.wifiConnected || WiFi.status() == WL_CONNECTED) return;
  if (millis() - state.lastReconnectAttempt < WIFI_RECONNECT_DELAY) return;
  
  state.lastReconnectAttempt = millis();
  Serial.println("🔄 WiFi reconnecting...");
  
  WiFi.disconnect();
  delay(1000);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts++ < 10) {
    delay(500);
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    state.wifiConnected = true;
    Serial.println("✓ WiFi reconnected");
    sendResponse("WIFI_RECONNECTED");
  }
}

// ═══════════════════════════════════════════════════════════
// COMMAND PROCESSING
// ═══════════════════════════════════════════════════════════

void processCommand() {
  String cmd = Serial.readStringUntil('\n');
  cmd.trim();
  if (cmd.isEmpty()) return;
  
  Serial.println("\n╔═══════════════════════════════════════════╗");
  Serial.printf("║ CMD: %-37s║\n", cmd.c_str());
  Serial.println("╚═══════════════════════════════════════════╝");
  
  if (cmd.startsWith("CAPTURE_ONLINE|")) {
    handleCaptureOnline(cmd.substring(15));
  } 
  else if (cmd == "CAPTURE_OFFLINE") {
    handleCaptureOffline();
  } 
  else if (cmd == "PING") {
    handlePing();
  } 
  else if (cmd == "STATUS") {
    handleStatus();
  } 
  else {
    Serial.println("✗ Unknown command");
    sendResponse("ERROR|UnknownCommand");
  }
}

void handleCaptureOnline(const String& sensorData) {
  Serial.println("🌐 Mode: ONLINE");
  
  String formatted = formatSensorData(sensorData);
  String caption = "📦 แจ้งเตือน: มีพัสดุมาส่ง!\n\n";
  caption += formatted;
  caption += "\n📅 " + getCurrentTime();
  caption += "\n🆔 #" + String(state.photoCount + 1);
  
  indicateLED(1);
  
  bool success = false;
  for (int i = 0; i < TELEGRAM_MAX_RETRY && !success; i++) {
    if (i > 0) {
      Serial.printf("🔄 Retry %d/%d\n", i, TELEGRAM_MAX_RETRY);
      delay(2000);
    }
    success = captureAndSendPhoto(caption);
  }
  
  if (success) {
    state.failureCount = 0;
    sendResponse("TELEGRAM_OK|Photo:" + String(state.photoCount));
  } else {
    state.failureCount++;
    sendResponse("TELEGRAM_FAIL|Retries:" + String(TELEGRAM_MAX_RETRY));
  }
}

void handleCaptureOffline() {
  Serial.println("📴 Mode: OFFLINE");
  
  String caption = "📦 แจ้งเตือน: มีพัสดุมาส่ง!\n\n";
  caption += "📅 " + getCurrentTime();
  caption += "\n⚠️ โหมดออฟไลน์";
  caption += "\n🆔 #" + String(state.photoCount + 1);
  
  indicateLED(1);
  
  bool success = captureAndSendPhoto(caption);
  sendResponse(success ? "TELEGRAM_OK|OFFLINE" : "TELEGRAM_FAIL|OFFLINE");
}

void handlePing() {
  Serial.println("🏓 PONG");
  String resp = "PONG|WiFi:" + String(state.wifiConnected ? "OK" : "FAIL");
  resp += "|Cam:" + String(state.cameraReady ? "OK" : "FAIL");
  resp += "|Photos:" + String(state.photoCount);
  sendResponse(resp);
  indicateLED(2);
}

void handleStatus() {
  Serial.println("📊 STATUS:");
  Serial.printf("   WiFi: %s (%d dBm)\n", state.wifiConnected ? "ON" : "OFF", WiFi.RSSI());
  Serial.printf("   Camera: %s\n", state.cameraReady ? "Ready" : "Error");
  Serial.printf("   Photos: %d | Fails: %d\n", state.photoCount, state.failureCount);
  Serial.printf("   Uptime: %lu s\n", millis() / 1000);
  sendResponse("STATUS_OK");
}

// ═══════════════════════════════════════════════════════════
// CAMERA & TELEGRAM FUNCTIONS
// ═══════════════════════════════════════════════════════════

bool captureAndSendPhoto(const String& caption) {
  if (!state.cameraReady) {
    Serial.println("✗ Camera not ready");
    return false;
  }
  
  if (!state.wifiConnected) {
    Serial.println("✗ No WiFi");
    return false;
  }
  
  Serial.println("📸 Capturing...");
  digitalWrite(LED_PIN, HIGH);
  
  camera_fb_t* fb = esp_camera_fb_get();
  if (!fb) {
    Serial.println("✗ Capture failed");
    digitalWrite(LED_PIN, LOW);
    return false;
  }
  
  Serial.printf("✓ Captured: %.1f KB\n", fb->len / 1024.0);
  state.photoCount++;
  
  bool success = sendPhotoToTelegram(fb->buf, fb->len, caption);
  
  esp_camera_fb_return(fb);
  digitalWrite(LED_PIN, LOW);
  
  return success;
}

bool sendPhotoToTelegram(const uint8_t* data, size_t size, const String& caption) {
  const char* server = "api.telegram.org";
  
  Serial.println("📤 Sending...");
  
  if (!telegramClient.connect(server, 443)) {
    Serial.println("✗ Connection failed");
    return false;
  }
  
  String boundary = "----ESP32CAM" + String(millis());
  
  String header = "--" + boundary + "\r\n";
  header += "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n";
  header += String(TELEGRAM_CHAT_ID) + "\r\n";
  header += "--" + boundary + "\r\n";
  header += "Content-Disposition: form-data; name=\"caption\"\r\n\r\n";
  header += caption + "\r\n";
  
  String photoHeader = "--" + boundary + "\r\n";
  photoHeader += "Content-Disposition: form-data; name=\"photo\"; filename=\"photo.jpg\"\r\n";
  photoHeader += "Content-Type: image/jpeg\r\n\r\n";
  
  String footer = "\r\n--" + boundary + "--\r\n";
  
  uint32_t contentLength = header.length() + photoHeader.length() + size + footer.length();
  
  telegramClient.println("POST /bot" + String(TELEGRAM_BOT_TOKEN) + "/sendPhoto HTTP/1.1");
  telegramClient.println("Host: " + String(server));
  telegramClient.println("Content-Type: multipart/form-data; boundary=" + boundary);
  telegramClient.println("Content-Length: " + String(contentLength));
  telegramClient.println();
  telegramClient.print(header);
  telegramClient.print(photoHeader);
  
  Serial.print("📊 Upload: ");
  size_t sent = 0;
  int lastPct = 0;
  
  for (size_t i = 0; i < size; i += UPLOAD_CHUNK_SIZE) {
    size_t chunk = min((size_t)UPLOAD_CHUNK_SIZE, size - i);
    telegramClient.write(&data[i], chunk);
    sent += chunk;
    
    int pct = (sent * 100) / size;
    if (pct >= lastPct + 20) {
      Serial.printf("%d%% ", pct);
      lastPct = pct;
    }
    yield();
  }
  Serial.println("✓");
  
  telegramClient.print(footer);
  
  Serial.print("⏳ Response: ");
  unsigned long timeout = millis();
  bool success = false;
  
  while (telegramClient.connected() && millis() - timeout < TELEGRAM_TIMEOUT_MS) {
    if (telegramClient.available()) {
      String line = telegramClient.readStringUntil('\n');
      if (line.indexOf("\"ok\":true") > 0) {
        Serial.println("✓ OK");
        success = true;
        break;
      }
      if (line.indexOf("\"ok\":false") > 0) {
        Serial.println("✗ API Error");
        break;
      }
    }
    yield();
  }
  
  if (!success && millis() - timeout >= TELEGRAM_TIMEOUT_MS) {
    Serial.println("✗ Timeout");
  }
  
  telegramClient.stop();
  return success;
}

// ═══════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ═══════════════════════════════════════════════════════════

String formatSensorData(const String& raw) {
  String result = "";
  
  if (raw.indexOf("PIR: YES") >= 0) {
    result += "👤 มีผู้ใช้งาน\n";
  } else if (raw.indexOf("PIR: NO") >= 0) {
    result += "🚫 ไม่มีผู้ใช้งาน\n";
  }
  
  int distPos = raw.indexOf("Distance: ");
  if (distPos >= 0) {
    int endPos = raw.indexOf("cm", distPos);
    if (endPos >= 0) {
      int dist = raw.substring(distPos + 10, endPos).toInt();
      result += "📏 ระยะ: " + String(dist) + " cm\n";
      
      if (dist <= 10) result += "🔴 สถานะ: เต็ม";
      else if (dist <= 20) result += "🟡 สถานะ: กลาง";
      else if (dist <= 40) result += "🟢 สถานะ: พร้อม";
      else result += "⚪ สถานะ: ว่าง";
    }
  }
  
  return result;
}

String getCurrentTime() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) return "N/A";
  
  char buf[25];
  strftime(buf, sizeof(buf), "%d/%m/%Y %H:%M:%S", &timeinfo);
  return String(buf);
}

void sendResponse(const String& msg) {
  Serial.println("📤 " + msg);
  Serial.flush();
}

void indicateLED(int blinks) {
  for (int i = 0; i < blinks; i++) {
    digitalWrite(LED_PIN, HIGH);
    delay(150);
    digitalWrite(LED_PIN, LOW);
    delay(150);
  }
}
/*
 * ═══════════════════════════════════════════════════════════
 * ESP32 Smart Parcel Box - Enhanced Single MG995 Servo
 * ระบบแจ้งเตือนการรับ-ส่งพัสดุอัจฉริยะ (IoT)
 * 
 * Version: 2.0 Clean Code
 * Author: Final Day Project Team
 * ═══════════════════════════════════════════════════════════
 */

#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - WiFi
// ═══════════════════════════════════════════════════════════
const char* WIFI_SSID = "pinkcute";
const char* WIFI_PASSWORD = "101245170946";

// ═══════════════════════════════════════════════════════════
// CONFIGURATION - Server
// ═══════════════════════════════════════════════════════════
const char* SERVER_DATA_URL = "http://10.82.9.214/parcel_system/upload_data.php";
const char* SERVER_LOG_URL = "http://10.82.9.214/parcel_system/operation_log.php";

// ═══════════════════════════════════════════════════════════
// HARDWARE PINS - Relays (Pilot Lamps)
// ═══════════════════════════════════════════════════════════
#define PIN_RELAY_RED     32
#define PIN_RELAY_YELLOW  33
#define PIN_RELAY_GREEN   25

// ═══════════════════════════════════════════════════════════
// HARDWARE PINS - Sensors
// ═══════════════════════════════════════════════════════════
#define PIN_ULTRASONIC_TRIG  15
#define PIN_ULTRASONIC_ECHO  2
#define PIN_PIR              5

// ═══════════════════════════════════════════════════════════
// HARDWARE PINS - Switches
// ═══════════════════════════════════════════════════════════
#define PIN_SWITCH_GREEN  4   // Online Mode
#define PIN_SWITCH_RED    16  // Offline Mode

// ═══════════════════════════════════════════════════════════
// HARDWARE PINS - Servo & ESP32-CAM
// ═══════════════════════════════════════════════════════════
#define PIN_SERVO         17  // MG995 Servo
#define PIN_CAM_RX        19  // ESP32-CAM Communication
#define PIN_CAM_TX        18

// ═══════════════════════════════════════════════════════════
// SERVO CONFIGURATION
// ═══════════════════════════════════════════════════════════
const int SERVO_ANGLE_CLOSED = 0;     // ปิดฝา (ปรับได้)
const int SERVO_ANGLE_OPEN = 90;      // เปิดฝา (ปรับได้)
const int SERVO_PWM_FREQ = 60;        // PWM Frequency (Hz)
const int SERVO_PULSE_MIN = 500;      // Min Pulse Width (μs)
const int SERVO_PULSE_MAX = 2600;     // Max Pulse Width (μs)
const int SERVO_MOVE_STEP = 2;        // Movement Step (degrees)
const int SERVO_MOVE_DELAY = 30;      // Delay between steps (ms)
const int SERVO_HOLD_DELAY = 500;     // Hold position delay (ms)

// ═══════════════════════════════════════════════════════════
// TIMING CONFIGURATION
// ═══════════════════════════════════════════════════════════
const unsigned long BOX_OPEN_DURATION = 5000;      // 5 seconds
const unsigned long PIR_MOTION_TIMEOUT = 30000;    // 30 seconds
const unsigned long SWITCH_DEBOUNCE = 500;         // 500ms
const unsigned long DATA_SEND_INTERVAL = 2000;     // 2 seconds
const unsigned long DEBUG_PRINT_INTERVAL = 3000;   // 3 seconds

// ═══════════════════════════════════════════════════════════
// ULTRASONIC CONFIGURATION
// ═══════════════════════════════════════════════════════════
const int ULTRASONIC_MAX_ERRORS = 5;
const int DISTANCE_RED = 10;      // 0-10 cm = RED (Full)
const int DISTANCE_YELLOW = 20;   // 10-20 cm = YELLOW (Medium)
const int DISTANCE_GREEN = 40;    // 20-40 cm = GREEN (Ready)

// ═══════════════════════════════════════════════════════════
// SWITCH CONFIGURATION
// ═══════════════════════════════════════════════════════════
const int SWITCH_CONFIRM_COUNT = 5;  // กดติดกี่ครั้งจึงยืนยัน

// ═══════════════════════════════════════════════════════════
// GLOBAL OBJECTS
// ═══════════════════════════════════════════════════════════
Servo boxServo;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - Servo & Box
// ═══════════════════════════════════════════════════════════
int servoPosition = SERVO_ANGLE_CLOSED;
bool boxIsOpen = false;
unsigned long boxOpenTime = 0;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - PIR Sensor
// ═══════════════════════════════════════════════════════════
bool pirMotionDetected = false;
unsigned long pirMotionTime = 0;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - Ultrasonic Sensor
// ═══════════════════════════════════════════════════════════
long ultrasonicDistance = 0;
int ultrasonicErrors = 0;
String currentLampStatus = "All OFF";

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - Switches
// ═══════════════════════════════════════════════════════════
unsigned long lastGreenPress = 0;
unsigned long lastRedPress = 0;
int greenSwitchCount = 0;
int redSwitchCount = 0;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - Network
// ═══════════════════════════════════════════════════════════
bool wifiConnected = false;
unsigned long lastDataSend = 0;
unsigned long lastDebugPrint = 0;

// ═══════════════════════════════════════════════════════════
// STATE VARIABLES - ESP32-CAM
// ═══════════════════════════════════════════════════════════
String cameraResponse = "";

// ═══════════════════════════════════════════════════════════
// FUNCTION DECLARATIONS
// ═══════════════════════════════════════════════════════════
void setupHardware();
void setupServo();
void setupWiFi();
void setupCamera();
void readUltrasonicSensor();
void controlPilotLamps();
void checkPIRMotion();
void checkSwitches();
void checkAutoClose();
void sendDataToServer();
void handleSerialCommands();
void printDebugInfo();

void moveServo(int targetAngle);
void openBox(String mode);
void closeBox();
void testServoAngles();

void handleGreenSwitch();
void handleRedSwitch();
void sendCameraCommand(String cmd);
void checkCameraResponse();
bool sendHTTPRequest(String url, String jsonData);
void logOperation(String type, String trigger, int duration, bool online);
String getSensorDataString();
bool isUserPresent();

// ═══════════════════════════════════════════════════════════
// SETUP
// ═══════════════════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  delay(1000);
  
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║   Smart Parcel Box v2.0 - Clean Code      ║");
  Serial.println("║   ระบบแจ้งเตือนพัสดุอัจฉริยะ (IoT)         ║");
  Serial.println("╚════════════════════════════════════════════╝\n");
  
  setupHardware();
  setupServo();
  setupCamera();
  setupWiFi();
  
  Serial.println("\n✅ System Ready!\n");
  Serial.println("Serial Commands: o=Open | c=Close | t=Test | s=Status | d=Debug\n");
}

// ═══════════════════════════════════════════════════════════
// MAIN LOOP
// ═══════════════════════════════════════════════════════════
void loop() {
  readUltrasonicSensor();
  controlPilotLamps();
  checkPIRMotion();
  checkCameraResponse();
  checkSwitches();
  checkAutoClose();
  handleSerialCommands();
  
  if (wifiConnected && millis() - lastDataSend >= DATA_SEND_INTERVAL) {
    sendDataToServer();
    lastDataSend = millis();
  }
  
  if (millis() - lastDebugPrint >= DEBUG_PRINT_INTERVAL) {
    printDebugInfo();
    lastDebugPrint = millis();
  }
  
  delay(100);
}

// ═══════════════════════════════════════════════════════════
// SETUP FUNCTIONS
// ═══════════════════════════════════════════════════════════

void setupHardware() {
  Serial.println("⚙️  Initializing hardware...");
  
  pinMode(PIN_RELAY_RED, OUTPUT);
  pinMode(PIN_RELAY_YELLOW, OUTPUT);
  pinMode(PIN_RELAY_GREEN, OUTPUT);
  pinMode(PIN_ULTRASONIC_TRIG, OUTPUT);
  pinMode(PIN_ULTRASONIC_ECHO, INPUT);
  pinMode(PIN_PIR, INPUT);
  pinMode(PIN_SWITCH_GREEN, INPUT_PULLUP);
  pinMode(PIN_SWITCH_RED, INPUT_PULLUP);
  
  digitalWrite(PIN_RELAY_RED, LOW);
  digitalWrite(PIN_RELAY_YELLOW, LOW);
  digitalWrite(PIN_RELAY_GREEN, LOW);
  
  Serial.println("   ✓ GPIO pins configured");
  Serial.println("   ✓ All relays OFF");
}

void setupServo() {
  Serial.println("⚙️  Initializing MG995 servo...");
  
  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  ESP32PWM::allocateTimer(2);
  ESP32PWM::allocateTimer(3);
  
  boxServo.setPeriodHertz(SERVO_PWM_FREQ);
  boxServo.attach(PIN_SERVO, SERVO_PULSE_MIN, SERVO_PULSE_MAX);
  boxServo.write(SERVO_ANGLE_CLOSED);
  servoPosition = SERVO_ANGLE_CLOSED;
  
  delay(1000);
  
  Serial.print("   ✓ Servo ready (GPIO");
  Serial.print(PIN_SERVO);
  Serial.println(")");
  Serial.print("   ✓ Pulse: ");
  Serial.print(SERVO_PULSE_MIN);
  Serial.print("-");
  Serial.print(SERVO_PULSE_MAX);
  Serial.println("μs");
}

void setupCamera() {
  Serial.println("⚙️  Initializing ESP32-CAM communication...");
  
  Serial2.begin(115200, SERIAL_8N1, PIN_CAM_RX, PIN_CAM_TX);
  delay(1000);
  
  Serial.print("   ✓ Serial2 ready (RX=GPIO");
  Serial.print(PIN_CAM_RX);
  Serial.print(", TX=GPIO");
  Serial.print(PIN_CAM_TX);
  Serial.println(")");
}

void setupWiFi() {
  Serial.println("⚙️  Connecting to WiFi...");
  Serial.print("   SSID: ");
  Serial.println(WIFI_SSID);
  
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println("   ✓ WiFi connected!");
    Serial.print("   ✓ IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("   ✓ Signal: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    wifiConnected = false;
    Serial.println("   ✗ WiFi failed - Running OFFLINE");
  }
}

// ═══════════════════════════════════════════════════════════
// SENSOR FUNCTIONS
// ═══════════════════════════════════════════════════════════

void readUltrasonicSensor() {
  digitalWrite(PIN_ULTRASONIC_TRIG, LOW);
  delayMicroseconds(5);
  digitalWrite(PIN_ULTRASONIC_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_ULTRASONIC_TRIG, LOW);
  
  long duration = pulseIn(PIN_ULTRASONIC_ECHO, HIGH, 30000);
  
  if (duration == 0) {
    ultrasonicErrors++;
    if (ultrasonicErrors >= ULTRASONIC_MAX_ERRORS) {
      ultrasonicDistance = 999;
      currentLampStatus = "SENSOR ERROR";
      digitalWrite(PIN_RELAY_RED, HIGH);
      digitalWrite(PIN_RELAY_YELLOW, HIGH);
      digitalWrite(PIN_RELAY_GREEN, HIGH);
      delay(1000);
      ultrasonicErrors = 0;
    }
    return;
  }
  
  ultrasonicDistance = duration * 0.034 / 2;
  ultrasonicErrors = 0;
}

void controlPilotLamps() {
  if (ultrasonicDistance < 2 || ultrasonicDistance > 400) return;
  
  if (ultrasonicDistance <= DISTANCE_RED) {
    digitalWrite(PIN_RELAY_RED, HIGH);
    digitalWrite(PIN_RELAY_YELLOW, LOW);
    digitalWrite(PIN_RELAY_GREEN, LOW);
    currentLampStatus = "RED - Full";
  } 
  else if (ultrasonicDistance <= DISTANCE_YELLOW) {
    digitalWrite(PIN_RELAY_RED, LOW);
    digitalWrite(PIN_RELAY_YELLOW, HIGH);
    digitalWrite(PIN_RELAY_GREEN, LOW);
    currentLampStatus = "YELLOW - Medium";
  } 
  else if (ultrasonicDistance <= DISTANCE_GREEN) {
    digitalWrite(PIN_RELAY_RED, LOW);
    digitalWrite(PIN_RELAY_YELLOW, LOW);
    digitalWrite(PIN_RELAY_GREEN, HIGH);
    currentLampStatus = "GREEN - Ready";
  } 
  else {
    digitalWrite(PIN_RELAY_RED, LOW);
    digitalWrite(PIN_RELAY_YELLOW, LOW);
    digitalWrite(PIN_RELAY_GREEN, LOW);
    currentLampStatus = "All OFF";
  }
}

void checkPIRMotion() {
  bool currentState = digitalRead(PIN_PIR);
  
  if (currentState == HIGH && !pirMotionDetected) {
    pirMotionDetected = true;
    pirMotionTime = millis();
    Serial.println("🚶 Motion detected!");
  } 
  else if (currentState == LOW && pirMotionDetected) {
    if (millis() - pirMotionTime > PIR_MOTION_TIMEOUT) {
      pirMotionDetected = false;
      Serial.println("🚶 Motion timeout");
    }
  }
}

bool isUserPresent() {
  return pirMotionDetected && (millis() - pirMotionTime < PIR_MOTION_TIMEOUT);
}

// ═══════════════════════════════════════════════════════════
// SERVO CONTROL FUNCTIONS
// ═══════════════════════════════════════════════════════════

void moveServo(int targetAngle) {
  Serial.print("   Moving: ");
  Serial.print(servoPosition);
  Serial.print("° → ");
  Serial.print(targetAngle);
  Serial.println("°");
  
  if (servoPosition < targetAngle) {
    for (int pos = servoPosition; pos <= targetAngle; pos += SERVO_MOVE_STEP) {
      boxServo.write(pos);
      delay(SERVO_MOVE_DELAY);
    }
  } else {
    for (int pos = servoPosition; pos >= targetAngle; pos -= SERVO_MOVE_STEP) {
      boxServo.write(pos);
      delay(SERVO_MOVE_DELAY);
    }
  }
  
  boxServo.write(targetAngle);
  delay(SERVO_HOLD_DELAY);
  servoPosition = targetAngle;
  
  Serial.print("   ✓ Reached ");
  Serial.print(targetAngle);
  Serial.println("°");
}

void openBox(String mode) {
  if (boxIsOpen) {
    Serial.println("⚠️  Box already OPEN");
    return;
  }
  
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║            OPENING BOX                     ║");
  Serial.println("╚════════════════════════════════════════════╝");
  Serial.print("Mode: ");
  Serial.println(mode);
  Serial.print("Distance: ");
  Serial.print(ultrasonicDistance);
  Serial.println(" cm\n");
  
  moveServo(SERVO_ANGLE_OPEN);
  
  boxIsOpen = true;
  boxOpenTime = millis();
  
  Serial.println("✅ Box opened!");
  Serial.print("Auto-close in ");
  Serial.print(BOX_OPEN_DURATION / 1000);
  Serial.println(" seconds\n");
}

void closeBox() {
  if (!boxIsOpen) {
    Serial.println("⚠️  Box already CLOSED");
    return;
  }
  
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║            CLOSING BOX                     ║");
  Serial.println("╚════════════════════════════════════════════╝");
  
  int openDuration = (millis() - boxOpenTime) / 1000;
  Serial.print("Was open for ");
  Serial.print(openDuration);
  Serial.println(" seconds\n");
  
  moveServo(SERVO_ANGLE_CLOSED);
  
  boxIsOpen = false;
  
  if (wifiConnected) {
    logOperation("AUTO_CLOSE", "TIMER", openDuration, wifiConnected);
  }
  
  Serial.println("✅ Box closed!\n");
}

void testServoAngles() {
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║         SERVO ANGLE TEST MODE              ║");
  Serial.println("╚════════════════════════════════════════════╝");
  Serial.println("Testing 0° to 180° in 10° steps\n");
  
  for (int angle = 0; angle <= 180; angle += 10) {
    Serial.print("Testing ");
    Serial.print(angle);
    Serial.println("°...");
    boxServo.write(angle);
    delay(1000);
  }
  
  Serial.println("\nReturning to closed position...");
  boxServo.write(SERVO_ANGLE_CLOSED);
  servoPosition = SERVO_ANGLE_CLOSED;
  delay(1000);
  
  Serial.println("✅ Test complete!\n");
}

void checkAutoClose() {
  if (boxIsOpen && millis() - boxOpenTime >= BOX_OPEN_DURATION) {
    Serial.println("⏰ Auto-close timer expired!");
    closeBox();
  }
}

// ═══════════════════════════════════════════════════════════
// SWITCH HANDLING
// ═══════════════════════════════════════════════════════════

void checkSwitches() {
  // Green Switch (Online Mode)
  if (digitalRead(PIN_SWITCH_GREEN) == LOW) {
    greenSwitchCount++;
    if (greenSwitchCount >= SWITCH_CONFIRM_COUNT && 
        millis() - lastGreenPress > SWITCH_DEBOUNCE) {
      lastGreenPress = millis();
      greenSwitchCount = 0;
      handleGreenSwitch();
    }
  } else {
    greenSwitchCount = 0;
  }
  
  // Red Switch (Offline Mode)
  if (digitalRead(PIN_SWITCH_RED) == LOW) {
    redSwitchCount++;
    if (redSwitchCount >= SWITCH_CONFIRM_COUNT && 
        millis() - lastRedPress > SWITCH_DEBOUNCE) {
      lastRedPress = millis();
      redSwitchCount = 0;
      handleRedSwitch();
    }
  } else {
    redSwitchCount = 0;
  }
}

void handleGreenSwitch() {
  Serial.println("🟢 GREEN SWITCH - Online Mode");
  
  if (!boxIsOpen) {
    openBox("ONLINE");
    sendCameraCommand("CAPTURE_ONLINE|" + getSensorDataString());
    
    if (wifiConnected) {
      logOperation("OPEN_ONLINE", "GREEN_SWITCH", 0, true);
    }
    
    Serial.println("📸 Camera: Sending to Telegram...");
  }
}

void handleRedSwitch() {
  Serial.println("🔴 RED SWITCH - Offline Mode");
  
  if (!boxIsOpen) {
    openBox("OFFLINE");
    sendCameraCommand("CAPTURE_OFFLINE");
    
    if (wifiConnected) {
      logOperation("OPEN_OFFLINE", "RED_SWITCH", 0, false);
    }
    
    Serial.println("💾 Camera: Saving to SD card...");
  }
}

// ═══════════════════════════════════════════════════════════
// SERIAL COMMAND HANDLING
// ═══════════════════════════════════════════════════════════

void handleSerialCommands() {
  if (!Serial.available()) return;
  
  char cmd = Serial.read();
  
  switch (cmd) {
    case 'o':
    case 'O':
      if (!boxIsOpen) {
        openBox("MANUAL");
        if (wifiConnected) {
          logOperation("OPEN_MANUAL", "SERIAL", 0, false);
        }
      }
      break;
      
    case 'c':
    case 'C':
      if (boxIsOpen) {
        closeBox();
      }
      break;
      
    case 't':
    case 'T':
      testServoAngles();
      break;
      
    case 's':
    case 'S':
      printDebugInfo();
      break;
      
    case 'd':
    case 'D':
      Serial.println("\n=== DETAILED DEBUG ===");
      printDebugInfo();
      Serial.print("Servo Step: ");
      Serial.print(SERVO_MOVE_STEP);
      Serial.println("°");
      Serial.print("Servo Delay: ");
      Serial.print(SERVO_MOVE_DELAY);
      Serial.println("ms");
      Serial.print("Pulse: ");
      Serial.print(SERVO_PULSE_MIN);
      Serial.print("-");
      Serial.print(SERVO_PULSE_MAX);
      Serial.println("μs");
      Serial.println("======================\n");
      break;
  }
}

// ═══════════════════════════════════════════════════════════
// ESP32-CAM COMMUNICATION
// ═══════════════════════════════════════════════════════════

void sendCameraCommand(String cmd) {
  Serial.println("📷 → ESP32-CAM: " + cmd);
  Serial2.println(cmd);
  Serial2.flush();
  cameraResponse = "";
}

void checkCameraResponse() {
  if (Serial2.available()) {
    String response = Serial2.readStringUntil('\n');
    response.trim();
    
    if (response.length() > 0) {
      cameraResponse = response;
      Serial.println("📷 ← ESP32-CAM: " + response);
    }
  }
}

String getSensorDataString() {
  String data = "PIR: ";
  data += pirMotionDetected ? "YES" : "NO";
  data += " | Distance: ";
  data += String(ultrasonicDistance);
  data += "cm";
  return data;
}

// ═══════════════════════════════════════════════════════════
// NETWORK FUNCTIONS
// ═══════════════════════════════════════════════════════════

void sendDataToServer() {
  if (!wifiConnected || ultrasonicDistance == 999) return;
  
  StaticJsonDocument<512> doc;
  doc["distance_cm"] = ultrasonicDistance;
  doc["pir_motion"] = pirMotionDetected;
  doc["box_status"] = boxIsOpen ? "OPEN" : "CLOSED";
  doc["servo_position"] = servoPosition;
  doc["relay_red"] = digitalRead(PIN_RELAY_RED);
  doc["relay_yellow"] = digitalRead(PIN_RELAY_YELLOW);
  doc["relay_green"] = digitalRead(PIN_RELAY_GREEN);
  doc["lamp_status"] = currentLampStatus;
  doc["device_id"] = "ESP32_BOX_01";
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  sendHTTPRequest(SERVER_DATA_URL, jsonString);
}

void logOperation(String type, String trigger, int duration, bool online) {
  if (!wifiConnected) return;
  
  StaticJsonDocument<512> doc;
  doc["operation_type"] = type;
  doc["trigger_method"] = trigger;
  doc["distance_at_operation"] = ultrasonicDistance;
  doc["motion_detected"] = pirMotionDetected;
  doc["lamp_status_at_operation"] = currentLampStatus;
  doc["open_duration_seconds"] = duration;
  doc["user_present"] = isUserPresent();
  doc["internet_mode"] = online;
  doc["notes"] = "Single MG995 Enhanced";
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  sendHTTPRequest(SERVER_LOG_URL, jsonString);
}

bool sendHTTPRequest(String url, String jsonData) {
  if (!wifiConnected) return false;
  
  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(10000);
  
  int code = http.POST(jsonData);
  http.end();
  
  return (code == 200);
}

// ═══════════════════════════════════════════════════════════
// DEBUG & MONITORING
// ═══════════════════════════════════════════════════════════

void printDebugInfo() {
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║          SYSTEM STATUS REPORT              ║");
  Serial.println("╚════════════════════════════════════════════╝");
  
  Serial.print("⏱️  Uptime: ");
  Serial.print(millis() / 1000);
  Serial.println("s");
  
  Serial.print("📡 WiFi: ");
  Serial.println(wifiConnected ? "CONNECTED" : "DISCONNECTED");
  
  Serial.print("📏 Distance: ");
  Serial.print(ultrasonicDistance);
  Serial.println(" cm");
  
  Serial.print("🚶 Motion: ");
  Serial.println(pirMotionDetected ? "DETECTED" : "NONE");
  
  Serial.print("📦 Box: ");
  Serial.println(boxIsOpen ? "OPEN 🔓" : "CLOSED 🔒");
  
  Serial.print("🔧 Servo: ");
  Serial.print(servoPosition);
  Serial.println("°");
  
  Serial.print("💡 Lamps: ");
  Serial.println(currentLampStatus);
  
  Serial.print("📷 Camera: ");
  Serial.println(cameraResponse.length() > 0 ? cameraResponse : "None");
  
  Serial.println("════════════════════════════════════════════\n");
}
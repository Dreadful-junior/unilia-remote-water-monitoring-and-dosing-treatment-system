#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ═══════════════════════════════════════════════════════════════
// SETTINGS — change these to match your network and server
// ═══════════════════════════════════════════════════════════════
const char* ssid       = "Honor 9N";
const char* password   = "12345678";
const char* receiveUrl = "http://192.168.43.248/water%20system/api/receive.php";
const char* configUrl  = "http://192.168.43.248/water%20system/api/get_config.php";
const char* apiKey     = "your-secret-api-key-123";

// ═══════════════════════════════════════════════════════════════
// PIN DEFINITIONS
// ═══════════════════════════════════════════════════════════════
#define RELAY_PIN      32
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16

// ═══════════════════════════════════════════════════════════════
// RELAY STATE TRACKING
// ═══════════════════════════════════════════════════════════════
bool relayState = false;   // mirrors the actual physical pin state

// ═══════════════════════════════════════════════════════════════
// SYSTEM STATE
// ═══════════════════════════════════════════════════════════════
String systemMode  = "manual";   // "manual" or "auto"
String manualState = "off";      // "on", "off", or "dose"

// Pump timing
bool          pumpRunning    = false;
unsigned long pumpStartTime  = 0;
unsigned long targetDuration = 0;   // ms

// Auto-schedule timing
bool          autoScheduleOn    = false;
unsigned long autoRunDurationMs = 0;
unsigned long autoRunStartedAt  = 0;

// Safety timeouts
const unsigned long MANUAL_SAFETY_TIMEOUT_MS = 300000UL;  // 5 minutes
const unsigned long AUTO_SAFETY_TIMEOUT_MS   = 600000UL;  // 10 minutes

// Heartbeat timing
unsigned long lastHeartbeat           = 0;
// SPEED FIX: Reduced interval to 2000ms for faster dashboard response
const unsigned long HEARTBEAT_INTERVAL_MS = 2000;  

// ═══════════════════════════════════════════════════════════════
// DYNAMIC CONFIG
// ═══════════════════════════════════════════════════════════════
float max_turbidity   = 200.0;
float max_tds         = 500.0;
float max_temp        = 35.0;
float tank_height     = 50.0;
float tank_capacity   = 10.0;
float PUMP_ML_PER_SEC = 1.5;

// ═══════════════════════════════════════════════════════════════
// SENSOR DATA
// ═══════════════════════════════════════════════════════════════
float turbidity   = 0;
float tds         = 0;
float temperature = 0;
float waterLitres = 0;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

// Forward declaration so processHeartbeat can call it immediately
void applyLogic();

// ═══════════════════════════════════════════════════════════════
// RELAY CONTROL
// ═══════════════════════════════════════════════════════════════
void setRelay(bool on) {
  if (on == relayState) return;

  relayState = on;
  digitalWrite(RELAY_PIN, on ? HIGH : LOW);

  if (on) {
    if (!pumpRunning) {
        pumpRunning   = true;
        pumpStartTime = millis();
        Serial.println(">>> RELAY ON  — pump started");
    }
  } else {
    pumpRunning      = false;
    pumpStartTime    = 0;
    targetDuration   = 0;
    autoScheduleOn   = false;
    autoRunStartedAt = 0;
    autoRunDurationMs = 0;
    Serial.println(">>> RELAY OFF — pump stopped");
  }
}

// ═══════════════════════════════════════════════════════════════
// SENSOR READING
// ═══════════════════════════════════════════════════════════════
void readSensors() {
  turbidity = (1.0 - (analogRead(TURBIDITY_PIN) / 4095.0)) * 1000.0;
  tds = analogRead(TDS_PIN) * (3.3 / 4095.0) * 500.0;
  sensors.requestTemperatures();
  temperature = sensors.getTempCByIndex(0);

  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long dur      = pulseIn(ECHO_PIN, HIGH, 25000);
  float distance = (dur * 0.0343) / 2.0;

  if (distance > 2 && distance < tank_height) {
    float fill  = 1.0 - ((distance - 2.0) / (tank_height - 2.0));
    waterLitres = fill * tank_capacity;
  } else {
    waterLitres = 0;
  }
}

// ═══════════════════════════════════════════════════════════════
// SERVER HEARTBEAT
// ═══════════════════════════════════════════════════════════════
void processHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  http.begin(receiveUrl);
  // SPEED FIX: Short timeout so we don't hang for 15s if WiFi is weak
  http.setTimeout(4000); 
  http.addHeader("Content-Type", "application/json");

  JsonDocument doc;
  doc["api_key"]     = apiKey;
  doc["turbidity"]   = turbidity;
  doc["tds"]         = tds;
  doc["temperature"] = temperature;
  doc["water_level"] = (waterLitres / tank_capacity) * 100.0;
  doc["pump"]        = pumpRunning ? 1 : 0;
  if (pumpRunning) {
    doc["pump_runtime"] = (millis() - pumpStartTime) / 1000;
  }

  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  
  if (code == 200) {
    String payload = http.getString();
    JsonDocument rDoc;
    DeserializationError err = deserializeJson(rDoc, payload);

    if (!err) {
      // 1. Update Mode
      String newMode = rDoc["command_mode"] | "manual";
      if (newMode != systemMode) {
        systemMode = newMode;
        if (systemMode == "manual") autoScheduleOn = false;
      }

      // 2. Update Config
      if (rDoc.containsKey("max_turbidity")) max_turbidity = rDoc["max_turbidity"];
      if (rDoc.containsKey("max_tds"))       max_tds       = rDoc["max_tds"];
      if (rDoc.containsKey("max_temp"))      max_temp      = rDoc["max_temp"];

      // 3. Process Command
      String newState = rDoc["command_state"] | "off";

      if (systemMode == "manual") {
        if (newState == "dose") {
          float ml = rDoc["command_extra"] | 0.0;
          if (ml > 0 && manualState != "dose") {
            targetDuration = (unsigned long)((ml / PUMP_ML_PER_SEC) * 1000);
            pumpStartTime  = millis();
            manualState    = "dose";
          }
        } else {
          manualState = newState;
        }
      } else {
        // AUTO MODE
        if (newState == "on") {
          unsigned long durSec = rDoc["duration_sec"] | 0;
          if (!autoScheduleOn) {
            autoScheduleOn    = true;
            autoRunDurationMs = (durSec > 0) ? durSec * 1000UL : 60000UL;
            autoRunStartedAt  = millis();
          }
        } else if (newState == "off") {
          autoScheduleOn = false;
        }
      }

      // SPEED FIX: Apply logic IMMEDIATELY after receiving command
      applyLogic();

    }
  }
  http.end();
}

// ═══════════════════════════════════════════════════════════════
// APPLY LOGIC
// ═══════════════════════════════════════════════════════════════
void applyLogic() {
  unsigned long now = millis();

  if (systemMode == "manual") {
    if (manualState == "on") {
      if (pumpRunning && (now - pumpStartTime > MANUAL_SAFETY_TIMEOUT_MS)) {
        manualState = "off";
        setRelay(false);
      } else {
        setRelay(true);
      }
    } else if (manualState == "dose") {
      if (targetDuration > 0 && (now - pumpStartTime < targetDuration)) {
        setRelay(true);
      } else {
        manualState = "off";
        setRelay(false);
      }
    } else {
      setRelay(false);
    }
  } else {
    // AUTO MODE
    if (autoScheduleOn) {
      unsigned long elapsed = now - autoRunStartedAt;
      if (elapsed >= autoRunDurationMs || elapsed >= AUTO_SAFETY_TIMEOUT_MS) {
        autoScheduleOn = false;
        setRelay(false);
      } else {
        setRelay(true);
      }
    } else {
      setRelay(false);
    }
  }
}

// ═══════════════════════════════════════════════════════════════
// SETUP
// ═══════════════════════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  relayState = false;

  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  sensors.begin();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nSystem ready.");
}

// ═══════════════════════════════════════════════════════════════
// LOOP
// ═══════════════════════════════════════════════════════════════
void loop() {
  readSensors();

  if (millis() - lastHeartbeat > HEARTBEAT_INTERVAL_MS) {
    processHeartbeat();
    lastHeartbeat = millis();
  }

  applyLogic();
  delay(50); // Small delay to reduce CPU usage but keep response fast
}

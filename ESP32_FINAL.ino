#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>

const char* ssid       = "Honor 9N";
const char* password   = "12345678";
const char* receiveUrl = "http://192.168.43.248/water%20system/api/receive.php";
const char* configUrl  = "http://192.168.43.248/water%20system/api/get_config.php";
const char* apiKey     = "your-secret-api-key-123";

#define RELAY_PIN      32   
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16

float tank_height   = 50.0;
float tank_capacity = 10.0;
float SENSOR_OFFSET = 3.0;
float PUMP_ML_PER_SEC = 1.5;     // Calibrate to your pump
float dose_ratio    = 2.0;       // ML per litre (from server config)

bool pumpRunning = false;

float turbidity   = 0;
float tds         = 0;
float temperature = 0;
float waterLitres = 0;

float max_turbidity = 200.0;
float max_tds       = 500.0;
float max_temp      = 35.0;

unsigned long lastSendTime    = 0;
unsigned long lastConfigFetch = 0;
unsigned long pumpStartTime   = 0;
unsigned long pumpScheduledStopTime = 0; // NEW: Non-blocking stop time
int sampling_interval = 5;

String systemMode  = "manual";   // Boot safe: manual
String manualState = "off";

// Auto-dose tracking
bool  autoDoseActive     = false;
float autoDoseTargetML   = 0;
unsigned long lastAutoDoseEndTime = 0;      // Time when the last auto-dose finished
unsigned long AUTO_DOSE_COOLDOWN_MS = 600000; // 10 minutes default cooldown (mixing period)

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

// ─────────────────────────────────────────────────────────
//  RELAY — single point of truth
// ─────────────────────────────────────────────────────────
void relayON() {
  digitalWrite(RELAY_PIN, HIGH); // ON (Active High)
  if (!pumpRunning) {
    pumpRunning   = true;
    pumpStartTime = millis();
    Serial.println("[RELAY] PUMP ON");
  }
}

void relayOFF() {
  digitalWrite(RELAY_PIN, LOW); // OFF
  if (pumpRunning) {
    pumpRunning = false;
    pumpScheduledStopTime = 0; // Clear any pending doses
    Serial.println("[RELAY] PUMP OFF");
  }
}

// ─────────────────────────────────────────────────────────
//  FETCH REMOTE CONFIG (thresholds set by technician)
// ─────────────────────────────────────────────────────────
void fetchRemoteConfig() {
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  http.begin(configUrl);
  if (http.GET() == 200) {
    JsonDocument doc;
    deserializeJson(doc, http.getString());
    if (doc["success"]) {
      max_turbidity = doc["thresholds"]["max_turbidity"] | 200.0;
      max_tds       = doc["thresholds"]["max_tds"]       | 500.0;
      max_temp      = doc["thresholds"]["max_temp"]      | 35.0;
      dose_ratio    = doc["thresholds"]["dose_ratio"]    | 2.0;
      tank_capacity = doc["thresholds"]["tank_capacity"] | 10.0;
      tank_height   = doc["thresholds"]["tank_height"]   | 50.0;
      Serial.printf("[CONFIG] Turb<%.0f TDS<%.0f Temp<%.0f DoseRatio=%.1f\n",
                    max_turbidity, max_tds, max_temp, dose_ratio);
    }
  }
  http.end();
  lastConfigFetch = millis();
}

// ─────────────────────────────────────────────────────────
//  MAIN SYNC CYCLE
// ─────────────────────────────────────────────────────────
void syncData() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastSendTime < (unsigned long)(sampling_interval * 1000)) return;

  // ── 1. Read sensors ──────────────────────────────────
  turbidity = (1.0 - (analogRead(TURBIDITY_PIN) / 4095.0)) * 1000.0;
  tds       = (analogRead(TDS_PIN) * (3.3 / 4095.0)) * 500.0;
  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);

  // Non-blocking trigger
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  float distance = pulseIn(ECHO_PIN, HIGH, 30000) * 0.0343 / 2.0;

  float fillPercent = 0;
  if (distance > 0 && distance <= SENSOR_OFFSET) {
    fillPercent = 1.0;
  } else if (distance > SENSOR_OFFSET && distance <= tank_height) {
    fillPercent = (tank_height - distance) / (tank_height - SENSOR_OFFSET);
  }
  fillPercent = constrain(fillPercent, 0.0, 1.0);
  waterLitres = fillPercent * tank_capacity;
  float dashboardPercentage = fillPercent * 100.0;

  Serial.printf("[ULTRASONIC] Dist: %.1fcm | Level: %.0f%% | %.2fL\n",
                distance, dashboardPercentage, waterLitres);

  // ── 2. Send to server and READ COMMANDS BACK ─────────
  HTTPClient http;
  http.begin(receiveUrl);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(8000);

  JsonDocument doc;
  doc["api_key"]        = apiKey;
  doc["turbidity"]      = turbidity;
  doc["tds"]            = tds;
  doc["temperature"]    = temperature;
  doc["distance_cm"]    = distance;
  doc["water_level"]    = dashboardPercentage;
  doc["pump"]           = pumpRunning ? 1 : 0;
  doc["operation_mode"] = systemMode;
  
  if (pumpRunning) {
    doc["pump_runtime"] = (millis() - pumpStartTime) / 1000;
  } else {
    doc["pump_runtime"] = 0;
  }

  String body;
  serializeJson(doc, body);

  int code = http.POST(body);

  if (code == 200) {
    JsonDocument rDoc;
    deserializeJson(rDoc, http.getString());

    if (rDoc.containsKey("command_mode")) {
      String newMode  = rDoc["command_mode"].as<String>();
      String newState = rDoc["command_state"].as<String>();

      // Mode changed?
      if (newMode != systemMode) {
        Serial.printf("[MODE] %s -> %s\n", systemMode.c_str(), newMode.c_str());
        systemMode = newMode;
      }

      // Apply manual commands
      if (systemMode == "manual") {
        if (newState == "dose") {
          float targetML     = rDoc["command_extra"].as<float>();
          unsigned long duration = (unsigned long)((targetML / PUMP_ML_PER_SEC) * 1000.0f);
          relayON();
          pumpScheduledStopTime = millis() + duration;
          manualState = "dose";
          Serial.printf("[MANUAL] Dose started: %.1f ML\n", targetML);
        } else if (newState == "on") {
          manualState = "on";
          relayON();
        } else if (newState == "off") {
          manualState = "off";
          relayOFF();
        }
      }
    }
  }
  http.end();
  lastSendTime = millis();

  // Logic moved to non-blocking check in loop() or handled immediately in syncData()

  if (systemMode == "auto") {
    bool waterNeedsTreatment = (turbidity > max_turbidity || tds > max_tds || temperature > max_temp);
    bool hasWater = (waterLitres > 0.1f);
    bool cooldownOver = (millis() - lastAutoDoseEndTime > AUTO_DOSE_COOLDOWN_MS || lastAutoDoseEndTime == 0);

    if (waterNeedsTreatment && hasWater && !pumpRunning && cooldownOver) {
      autoDoseTargetML = waterLitres * dose_ratio;
      unsigned long duration = (unsigned long)((autoDoseTargetML / PUMP_ML_PER_SEC) * 1000.0f);
      relayON();
      pumpScheduledStopTime = millis() + duration;
      Serial.printf("[AUTO] Start dose: %.1f ML\n", autoDoseTargetML);
    }
  }
}

// ─────────────────────────────────────────────────────────
//  SETUP & LOOP
// ─────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);  // Safe start: pump OFF
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  tempSensor.begin();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\n[SYSTEM] Online — Mode: MANUAL (safe boot)");

  fetchRemoteConfig();
}

void loop() {
  syncData();
  
  // Non-blocking dose timer
  if (pumpRunning && pumpScheduledStopTime > 0 && millis() >= pumpScheduledStopTime) {
    relayOFF();
    if (systemMode == "auto") {
      lastAutoDoseEndTime = millis(); // Start cooldown only for auto doses
    }
    Serial.println("[TIMER] Scheduled dose completed.");
  }

  if (millis() - lastConfigFetch > 30000) {
    fetchRemoteConfig();
  }
}

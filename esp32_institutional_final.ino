/*
 * UniLi Water Monitoring System - INSTITUTIONAL COMMAND & CONTROL
 * 
 * Features:
 * 1. Remote Calibration (TDS/Turbidity) from Dashboard
 * 2. Remote Dosing Control (mL/Litre) from Dashboard
 * 3. Remote WiFi Management (SSID/Pass) from Dashboard
 * 4. Real-time Heartbeat & Component Diagnostics
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Preferences.h> // For storing WiFi settings locally

// --- WiFi & Server Defaults (Used for initial connection) ---
char ssid[64]       = "Honor 9N";
char password[64]   = "12345678";
const char* receiveUrl = "http://192.168.43.248/water%20system/api/receive.php";
const char* configUrl  = "http://192.168.43.248/water%20system/api/get_config.php";
const char* apiKey     = "your-secret-api-key-123";

// --- Pin Definitions ---
#define RELAY_PIN      32   
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16

// --- Dynamic Parameters (Synced from Dashboard) ---
float max_turbidity = 50.0, max_tds = 500.0, max_temp = 35.0, min_level = 10.0;
float tds_slope = 1.0, tds_intercept = 0.0, turb_offset = 0.0;
float dose_ml_per_litre = 2.0;
bool  pumpRunning       = false;
unsigned long pumpStartTime = 0;
unsigned long pumpScheduledStopTime = 0;
int sampling_interval = 5;
unsigned long lastAutoDoseEndTime = 0;
unsigned long AUTO_DOSE_COOLDOWN_MS = 600000; // 10 minutes

// --- State Variables ---
float turbidity = 0, tds = 0, temperature = 0, waterLitres = 0;
float turbidity = 0, tds = 0, temperature = 0, waterLitres = 0;
// bool pumpRunning is now above
unsigned long lastSendTime = 0;
unsigned long lastConfigFetch = 0;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);
Preferences preferences;

// =============================================================
//   REMOTE CONFIGURATION & CALIBRATION
// =============================================================
void fetchRemoteConfig() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  Serial.println("[DASH] Fetching latest calibration & control settings...");
  HTTPClient http;
  http.begin(configUrl);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    StaticJsonDocument<1536> doc;
    deserializeJson(doc, payload);

    if (doc["success"]) {
      // 1. Update Thresholds & Dosing
      sampling_interval = doc["sampling_interval_sec"] | 5;
      max_turbidity     = doc["thresholds"]["max_turbidity"] | 50.0;
      max_tds           = doc["thresholds"]["max_tds"] | 500.0;
      max_temp          = doc["thresholds"]["max_temp"] | 35.0;
      dose_ml_per_litre = doc["thresholds"]["dose_ratio"] | 2.0;

      // 2. Update Calibration Factors
      tds_slope     = doc["calibration"]["tds_slope"] | 1.0;
      tds_intercept = doc["calibration"]["tds_intercept"] | 0.0;
      turb_offset   = doc["calibration"]["turbidity_offset"] | 0.0;

      // 3. Remote WiFi Check (Beginner Friendly)
      String newSsid = doc["wifi"]["ssid"] | "";
      String newPass = doc["wifi"]["pass"] | "";
      
      if (newSsid.length() > 0 && (newSsid != String(ssid) || newPass != String(password))) {
        Serial.println("[WIFI] New credentials received from Dashboard. Saving...");
        preferences.begin("net", false);
        preferences.putString("ssid", newSsid);
        preferences.putString("pass", newPass);
        preferences.end();
        Serial.println("[WIFI] RESTARTING to apply new settings...");
        delay(2000);
        ESP.restart();
      }

      Serial.println("[DASH] Sync Successful. System Calibrated.");
    }
  }
  http.end();
  lastConfigFetch = millis();
}

// =============================================================
//   SMART PUMP LOGIC (Calculated Dosing)
// =============================================================
void processTreatment() {
  bool isDirty = (turbidity > max_turbidity || tds > max_tds || temperature > max_temp);
  bool cooldownOver = (millis() - lastAutoDoseEndTime > AUTO_DOSE_COOLDOWN_MS || lastAutoDoseEndTime == 0);
  
  if (isDirty && !pumpRunning && cooldownOver) {
    float totalDoseMl = waterLitres * dose_ml_per_litre;
    long pumpTimeMs = (long)((totalDoseMl / 1.5) * 1000.0);
    
    if (pumpTimeMs > 0) {
      Serial.printf("[PUMP] Detected dirty water. Dosing %.1f mL\n", totalDoseMl);
      digitalWrite(RELAY_PIN, HIGH);
      pumpRunning = true;
      pumpStartTime = millis();
      pumpScheduledStopTime = millis() + pumpTimeMs;
    }
  }
}

// =============================================================
//   DASHBOARD SYNC & HEARTBEAT
// =============================================================
void syncData() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastSendTime < (sampling_interval * 1000)) return;
  
  // Read Sensors
  int turbRaw = analogRead(TURBIDITY_PIN);
  turbidity = ((1.0f - (turbRaw / 4095.0f)) * 1000.0f) + turb_offset;
  
  float tdsV = analogRead(TDS_PIN) * (3.3f / 4095.0f);
  tds = (tdsV * 500.0f * tds_slope) + tds_intercept;

  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);

  // Ultrasonic Level
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long dur = pulseIn(ECHO_PIN, HIGH, 30000);
  float dist = (dur * 0.0343) / 2.0;
  waterLitres = (dist > 2 && dist < 25) ? (1.0 - ((dist-2)/23.0)) * 5.0 : 0; // 5 Litre tank example

  // Send to Dashboard
  HTTPClient http;
  http.begin(receiveUrl);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<1024> doc;
  doc["api_key"]     = apiKey;
  doc["turbidity"]   = turbidity;
  doc["tds"]         = tds;
  doc["temperature"] = temperature;
  doc["water_level"] = (waterLitres / 5.0) * 100.0;
  doc["pump"]        = pumpRunning ? 1 : 0;
  
  if (pumpRunning) {
    doc["pump_runtime"] = (millis() - pumpStartTime) / 1000;
  } else {
    doc["pump_runtime"] = 0;
  }

  String body; serializeJson(doc, body);
  int code = http.POST(body);
  
  if (code == 200) {
    String resp = http.getString();
    StaticJsonDocument<512> rDoc;
    deserializeJson(rDoc, resp);
    
    // Check for Manual Override from Dashboard
    String mode = rDoc["command_mode"] | "auto";
    String state = rDoc["command_state"] | "off";
    
    if (mode == "manual") {
      if (state == "dose") {
        float ml = rDoc["command_extra"] | 0.0;
        if (ml > 0) {
          Serial.printf("[DASH] Manual Dose Command: %.1f mL\n", ml);
          long pumpTimeMs = (long)((ml / 1.5) * 1000.0);
          digitalWrite(RELAY_PIN, LOW);
          pumpRunning = true;
          pumpStartTime = millis();
          pumpScheduledStopTime = millis() + pumpTimeMs;
        }
      } else {
        bool shouldOn = (state == "on");
        if (shouldOn) {
          digitalWrite(RELAY_PIN, HIGH);
          pumpRunning = true;
          pumpStartTime = millis();
          pumpScheduledStopTime = 0; // Clear any scheduled doses
        } else {
          digitalWrite(RELAY_PIN, LOW);
          pumpRunning = false;
          pumpScheduledStopTime = 0; // Clear any scheduled doses
        }
      }
    } else {
      processTreatment();
    }
  }
  http.end();
  lastSendTime = millis();
}

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW); // OFF
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  tempSensor.begin();

  // Load WiFi from Preferences
  preferences.begin("net", true);
  String s = preferences.getString("ssid", ssid);
  String p = preferences.getString("pass", password);
  preferences.end();

  WiFi.begin(s.c_str(), p.c_str());
  Serial.printf("[WIFI] Connecting to: %s\n", s.c_str());
  
  int r = 0;
  while (WiFi.status() != WL_CONNECTED && r < 15) { delay(1000); Serial.print("."); r++; }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[SYSTEM] ONLINE.");
    fetchRemoteConfig();
  } else {
    Serial.println("\n[WIFI] Connect failed. Using fallback SSID...");
    WiFi.begin(ssid, password);
  }
}

void loop() {
  syncData();
  
  // Non-blocking dose timer
  if (pumpRunning && pumpScheduledStopTime > 0 && millis() >= pumpScheduledStopTime) {
    digitalWrite(RELAY_PIN, LOW); // OFF
    pumpRunning = false;
    pumpScheduledStopTime = 0;
    lastAutoDoseEndTime = millis(); // Start cooldown
    Serial.println("[TIMER] Dose completed.");
  }

  if (millis() - lastConfigFetch > 300000) fetchRemoteConfig();
}

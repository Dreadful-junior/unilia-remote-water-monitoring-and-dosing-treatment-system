/*
 * UniLi Water Monitoring System - INSTITUTIONAL COMMAND & CONTROL (V2 - DOSING FIXED)
 * 
 * Features:
 * 1. Remote Tank Calibration (Height/Capacity) - NEW!
 * 2. Remote Dosing Control (mL/Litre)
 * 3. Remote Calibration (TDS/Turbidity)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Preferences.h>

// --- WiFi & Server ---
char ssid[64]       = "Honor 9N";
char password[64]   = "12345678";
const char* receiveUrl = "http://192.168.43.248/water%20system/api/receive.php";
const char* configUrl  = "http://192.168.43.248/water%20system/api/get_config.php";
const char* apiKey     = "your-secret-api-key-123";

#define RELAY_PIN      25   
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16

// --- Dynamic Settings (Fetched from Dashboard) ---
float max_turbidity = 50.0, max_tds = 500.0, max_temp = 35.0, min_level = 10.0;
float tds_slope = 1.0, tds_intercept = 0.0, turb_offset = 0.0;
float dose_ml_per_litre = 2.0;
float tank_height = 25.0;     // Default 25cm
float tank_capacity = 5.0;    // Default 5 Litres
bool  pumpRunning       = false;
unsigned long pumpStartTime = 0;
unsigned long totalPumpRuntimeMs = 0;
int sampling_interval = 5;

// --- State Variables ---
float turbidity = 0, tds = 0, temperature = 0, waterLitres = 0;
unsigned long lastSendTime = 0;
unsigned long lastConfigFetch = 0;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);
Preferences preferences;

void fetchRemoteConfig() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  Serial.println("[DASH] Syncing parameters...");
  HTTPClient http;
  http.begin(configUrl);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    StaticJsonDocument<1536> doc;
    deserializeJson(doc, payload);

    if (doc["success"]) {
      sampling_interval = doc["sampling_interval_sec"] | 5;
      max_turbidity     = doc["thresholds"]["max_turbidity"] | 50.0;
      max_tds           = doc["thresholds"]["max_tds"] | 500.0;
      max_temp          = doc["thresholds"]["max_temp"] | 35.0;
      dose_ml_per_litre = doc["thresholds"]["dose_ratio"] | 2.0;
      
      // NEW: Dynamic Tank Scaling
      tank_height       = doc["thresholds"]["tank_height"] | 25.0;
      tank_capacity     = doc["thresholds"]["tank_capacity"] | 5.0;

      tds_slope         = doc["calibration"]["tds_slope"] | 1.0;
      tds_intercept     = doc["calibration"]["tds_intercept"] | 0.0;
      turb_offset       = doc["calibration"]["turbidity_offset"] | 0.0;
      
      Serial.println("[DASH] Sync Complete. Tank Height: " + String(tank_height) + " cm");
    }
  }
  http.end();
  lastConfigFetch = millis();
}

void processTreatment() {
  bool isDirty = (turbidity > max_turbidity || tds > max_tds || temperature > max_temp);
  
  if (isDirty && !pumpRunning && waterLitres > 0.1) {
    float totalDoseMl = waterLitres * dose_ml_per_litre;
    // Assuming the dosing pump moves ~1.5ml per second
    long pumpTimeMs = (long)((totalDoseMl / 1.5) * 1000.0); 
    
    if (pumpTimeMs > 0) {
      Serial.printf("[PUMP] DOSING: %.1f mL (Targeting %.1f Litres)\n", totalDoseMl, waterLitres);
      digitalWrite(RELAY_PIN, LOW); // ON
      pumpRunning = true;
      pumpStartTime = millis();
      delay(pumpTimeMs); 
      digitalWrite(RELAY_PIN, HIGH); // OFF
      pumpRunning = false;
    }
  }
}

void syncData() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastSendTime < (sampling_interval * 1000)) return;
  
  // 1. Read Sensors
  int turbRaw = analogRead(TURBIDITY_PIN);
  turbidity = ((1.0f - (turbRaw / 4095.0f)) * 1000.0f) + turb_offset;
  
  float tdsV = analogRead(TDS_PIN) * (3.3f / 4095.0f);
  tds = (tdsV * 500.0f * tds_slope) + tds_intercept;

  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);

  // 2. Calculated Water Volume (Dynamic)
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long dur = pulseIn(ECHO_PIN, HIGH, 30000);
  float dist = (dur * 0.0343) / 2.0;

  // Level Logic: 0% at 'tank_height', 100% at ~2cm from sensor
  if (dist > 0 && dist <= 2.5) {
    waterLitres = tank_capacity; // 100% if extremely close
  } else if (dist > 2.5 && dist <= tank_height) {
    float fillPercentage = 1.0 - ((dist - 2.5) / (tank_height - 2.5));
    waterLitres = fillPercentage * tank_capacity;
  } else {
    waterLitres = 0;
  }

  // 3. Send Heartbeat
  HTTPClient http;
  http.begin(receiveUrl);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<1024> doc;
  doc["api_key"]     = apiKey;
  doc["turbidity"]   = turbidity;
  doc["tds"]         = tds;
  doc["temperature"] = temperature;
  doc["distance_cm"]  = dist;
  doc["water_level"] = (waterLitres / tank_capacity) * 100.0;
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
    
    String cmdMode  = rDoc["command_mode"]  | "auto";
    String cmdState = rDoc["command_state"] | "off";
    
    if (cmdMode == "manual") {
      if (cmdState == "dose") {
        float ml = rDoc["command_extra"] | 0.0;
        if (ml > 0) {
          Serial.printf("[DASH] Manual Dose Command: %.1f mL\n", ml);
          // Assuming the pump moves ~1.5ml per second
          long pumpTimeMs = (long)((ml / 1.5) * 1000.0);
          digitalWrite(RELAY_PIN, LOW);
          pumpRunning = true;
          pumpStartTime = millis();
          delay(pumpTimeMs);
          digitalWrite(RELAY_PIN, HIGH);
          pumpRunning = false;
          Serial.println("[DASH] Dose completed.");
        }
      } else {
        // Dashboard is commanding the relay directly
        bool shouldBeOn = (cmdState == "on");
        if (shouldBeOn && !pumpRunning) {
          Serial.println("[DASH] Manual Command: PUMP ON");
          digitalWrite(RELAY_PIN, LOW); // Relay ON (Active Low)
          pumpRunning = true;
          pumpStartTime = millis();
        } else if (!shouldBeOn && pumpRunning) {
          Serial.println("[DASH] Manual Command: PUMP OFF");
          digitalWrite(RELAY_PIN, HIGH); // Relay OFF
          pumpRunning = false;
        }
      }
    } else if (cmdMode == "auto") {
      // Let auto treatment logic decide
      processTreatment();
    }
  }
  http.end();
  lastSendTime = millis();
}

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH); // OFF
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  tempSensor.begin();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\n[SYSTEM] Online.");
  fetchRemoteConfig();
}

void loop() {
  syncData();
  if (millis() - lastConfigFetch > 300000) fetchRemoteConfig();
}

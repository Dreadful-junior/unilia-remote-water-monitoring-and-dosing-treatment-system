/*
 * UniLi Water Monitoring System - Professional Integrated Firmware
 * Fully synchronized with Web Dashboard via Dynamic Configuration
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// --- WiFi & Server ---
const char* ssid       = "Honor 9N";
const char* password   = "12345678";
const char* serverIp   = "192.168.43.248";
const char* receiveUrl = "http://192.168.43.248/water%20system/api/receive.php";
const char* configUrl  = "http://192.168.43.248/water%20system/api/get_config.php";
const char* apiKey     = "your-secret-api-key-123";

// --- Pin Definitions ---
#define RELAY_PIN      25   
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16
#define MANUAL_BTN     23

// --- Dynamic Settings (Fetched from Dashboard) ---
float max_turbidity = 50.0;
float max_tds = 500.0;
float max_temp = 35.0;
float min_level = 10.0;
float tds_slope = 1.0;
float tds_intercept = 0.0;
float turb_offset = 0.0;
int sampling_interval = 5; // Default 5 seconds

// --- State Variables ---
float turbidity = 0, tds = 0, temperature = 0, waterLevel = 0;
bool pumpRunning = false;
unsigned long lastSendTime = 0;
unsigned long lastConfigFetch = 0;
String recommendation = "System Initializing...";

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

// =============================================================
//   REMOTE CONFIGURATION FETCH (The "Brain" of Integration)
// =============================================================
void fetchRemoteConfig() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  Serial.println("[CONFIG] Syncing with dashboard...");
  HTTPClient http;
  http.begin(configUrl);
  int httpCode = http.GET();

  if (httpCode == 200) {
    String payload = http.getString();
    StaticJsonDocument<1024> doc;
    deserializeJson(doc, payload);

    if (doc["success"]) {
      sampling_interval = doc["sampling_interval_sec"] | 5;
      
      // Update Thresholds
      max_turbidity = doc["thresholds"]["max_turbidity"] | 50.0;
      max_tds       = doc["thresholds"]["max_tds"] | 500.0;
      max_temp      = doc["thresholds"]["max_temp"] | 35.0;
      min_level     = doc["thresholds"]["min_water_level"] | 10.0;

      // Update Calibration
      tds_slope     = doc["calibration"]["tds_slope"] | 1.0;
      tds_intercept = doc["calibration"]["tds_intercept"] | 0.0;
      turb_offset   = doc["calibration"]["turbidity_offset"] | 0.0;

      Serial.printf("[CONFIG] Sync Complete. Interval: %ds | Turb Lim: %.1f | TDS Lim: %.1f\n", 
                    sampling_interval, max_turbidity, max_tds);
    }
  }
  http.end();
  lastConfigFetch = millis();
}

// =============================================================
//   SENSOR DATA ACQUISITION
// =============================================================
void readSensors() {
  // 1. Turbidity + Remote Offset
  int turbRaw = analogRead(TURBIDITY_PIN);
  float turbV = turbRaw * (3.3f / 4095.0f);
  turbidity = ((1.0f - (turbV / 3.3f)) * 1000.0f) + turb_offset;
  turbidity = constrain(turbidity, 0, 1000);

  // 2. TDS + Remote Slope/Intercept
  float tdsV = analogRead(TDS_PIN) * (3.3f / 4095.0f);
  tds = (tdsV * 500.0f * tds_slope) + tds_intercept;

  // 3. Temperature
  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);

  // 4. Ultrasonic (Simple Level Calculation)
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  float distance = (duration * 0.0343) / 2.0;
  waterLevel = (distance > 0) ? distance : 0; // Simplified for this example
}

// =============================================================
//   PUMP CONTROL & DATA SYNC
// =============================================================
void syncWithDashboard() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastSendTime < (sampling_interval * 1000)) return;
  
  HTTPClient http;
  http.begin(receiveUrl);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<1024> doc;
  doc["api_key"]     = apiKey;
  doc["turbidity"]   = turbidity;
  doc["tds"]         = tds;
  doc["temperature"] = temperature;
  doc["distance_cm"] = waterLevel; // Server derives % from this
  doc["pump"]        = pumpRunning ? 1 : 0;

  String body; serializeJson(doc, body);
  int httpCode = http.POST(body);

  if (httpCode == 200) {
    String response = http.getString();
    StaticJsonDocument<512> respDoc;
    deserializeJson(respDoc, response);

    // DASHBOARD REMOTE CONTROL:
    // The server tells the ESP32 if it should be in AUTO or MANUAL mode
    String mode = respDoc["command_mode"] | "auto";
    String state = respDoc["command_state"] | "off";

    if (mode == "manual") {
      setPump(state == "on");
    } else {
      // Automatic Rule: Dose if thresholds exceeded
      bool needsTreatment = (turbidity > max_turbidity || tds > max_tds);
      setPump(needsTreatment);
    }
  }
  http.end();
  lastSendTime = millis();
}

void setPump(bool on) {
  if (on) {
    digitalWrite(RELAY_PIN, LOW); // Relay Active
    pumpRunning = true;
  } else {
    digitalWrite(RELAY_PIN, HIGH); // Relay Idle
    pumpRunning = false;
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(RELAY_PIN, OUTPUT);
  setPump(false);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  tempSensor.begin();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\n[SYSTEM] Online.");
  
  fetchRemoteConfig(); // Get initial settings from Dashboard
}

void loop() {
  readSensors();
  syncWithDashboard();
  
  // Refresh config every 5 minutes to catch dashboard changes
  if (millis() - lastConfigFetch > 300000) {
    fetchRemoteConfig();
  }
}

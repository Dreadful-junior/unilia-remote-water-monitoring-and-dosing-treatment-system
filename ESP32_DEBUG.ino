#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// ====================== YOUR SETTINGS ======================
const char* ssid     = "Honor 9N_F415";
const char* password = "dali1234";
const char* serverName = "http://192.168.43.248/water%20system/api/receive.php";  // FIXED: URL encoded space
const char* apiKey   = "your-secret-api-key-123";
// ==========================================================

// ====================== SENSOR PINS =======================
#define ONE_WIRE_BUS   4    // DS18B20 data pin
#define TURBIDITY_PIN 34    // Turbidity analog output
#define TDS_PIN       35    // TDS analog output
#define TRIG_PIN      26    // HC-SR04 Trigger
#define ECHO_PIN      27    // HC-SR04 Echo
// ==========================================================

// ====================== TANK CONFIG =======================
#define TANK_EMPTY_CM  30.0   // Distance (cm) when tank is EMPTY (sensor to bottom)
#define TANK_FULL_CM    5.0   // Distance (cm) when tank is FULL (sensor to water surface)
// ==========================================================

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

// ── Ultrasonic helper ──────────────────────────────────────
float getDistanceCM() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 30000);

  if (duration == 0) {
    Serial.println("⚠️  Ultrasonic: no echo (out of range?)");
    return -1.0;
  }

  return (duration * 0.0343) / 2.0;
}

// ── Water level percentage ─────────────────────────────────
float distanceToLevel(float distanceCM) {
  if (distanceCM < 0) return -1.0;
  float level = ((TANK_EMPTY_CM - distanceCM) / (TANK_EMPTY_CM - TANK_FULL_CM)) * 100.0;
  level = constrain(level, 0.0, 100.0);
  return level;
}

void setup() {
  Serial.begin(115200);
  delay(2000);

  Serial.println("\n\n🔧 === ESP32 Water Quality Monitor - WiFi Debug ===");
  Serial.print("SSID: "); Serial.println(ssid);
  Serial.print("Server: "); Serial.println(serverName);

  sensors.begin();
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  // ── WiFi Scan ──────────────────────────────────────────────
  Serial.println("\n📡 Scanning WiFi networks...");
  int n = WiFi.scanNetworks();
  Serial.print("Found "); Serial.print(n); Serial.println(" networks:");
  for (int i = 0; i < n; i++) {
    Serial.print("  ["); Serial.print(i); Serial.print("] ");
    Serial.print(WiFi.SSID(i)); Serial.print(" | RSSI: ");
    Serial.print(WiFi.RSSI(i)); Serial.println(" dBm");
  }

  // ── Connect to WiFi ────────────────────────────────────────
  Serial.println("\n🔗 Attempting to connect...");
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  Serial.println();
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("✅ WiFi Connected!");
    Serial.print("Local IP: "); Serial.println(WiFi.localIP());
    Serial.print("RSSI: "); Serial.print(WiFi.RSSI()); Serial.println(" dBm");
  } else {
    Serial.println("❌ WiFi FAILED to connect!");
    Serial.print("Status: "); Serial.println(WiFi.status());
    // WiFi status codes: 0=WL_IDLE_STATUS, 1=WL_NO_SSID_AVAIL, 2=WL_SCAN_COMPLETED, 
    //                     3=WL_CONNECTED, 4=WL_CONNECT_FAILED, 5=WL_CONNECTION_LOST, 6=WL_DISCONNECTED
  }
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {

    // ── 1. Temperature ───────────────────────────────────────
    sensors.requestTemperatures();
    float temperature = sensors.getTempCByIndex(0);

    // ── 2. Turbidity ─────────────────────────────────────────
    int   rawTurb    = analogRead(TURBIDITY_PIN);
    float turbVoltage = rawTurb * (3.3 / 4095.0);

    // ── 3. TDS ───────────────────────────────────────────────
    int   rawTDS     = analogRead(TDS_PIN);
    float tdsVoltage = rawTDS * (3.3 / 4095.0);
    float tdsValue   = tdsVoltage * 500.0;

    // ── 4. Ultrasonic Water Level ────────────────────────────
    float distanceCM  = getDistanceCM();
    float waterLevelPct = distanceToLevel(distanceCM);

    // ── Serial Debug ─────────────────────────────────────────
    Serial.println("\n========= Sensor Readings =========");
    Serial.print("🌡️  Temperature   : "); Serial.print(temperature, 1); Serial.println(" °C");
    Serial.print("💧 Turbidity Raw  : "); Serial.print(rawTurb);
    Serial.print("  | Voltage: ");        Serial.print(turbVoltage, 2); Serial.println(" V");
    Serial.print("🧪 TDS Raw        : "); Serial.print(rawTDS);
    Serial.print("  | Voltage: ");        Serial.print(tdsVoltage, 2);
    Serial.print(" V  | Approx: ");       Serial.print(tdsValue, 0); Serial.println(" ppm");
    Serial.print("📏 Distance       : ");
    if (distanceCM < 0) Serial.println("Error");
    else { Serial.print(distanceCM, 1); Serial.println(" cm"); }
    Serial.print("🪣 Water Level    : ");
    if (waterLevelPct < 0) Serial.println("Error");
    else { Serial.print(waterLevelPct, 1); Serial.println(" %"); }
    Serial.println("====================================");

    // ── Send to Server ────────────────────────────────────────
    Serial.println("📤 Sending data to server...");
    HTTPClient http;
    http.begin(serverName);
    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<400> doc;
    doc["api_key"]      = apiKey;
    doc["temperature"]  = temperature;
    doc["turbidity"]    = turbVoltage;
    doc["tds"]          = tdsValue;
    doc["distance_cm"]  = distanceCM;
    doc["water_level"]  = waterLevelPct;

    String requestBody;
    serializeJson(doc, requestBody);
    Serial.println("Request: " + requestBody);

    int httpResponseCode = http.POST(requestBody);

    if (httpResponseCode > 0) {
      Serial.println("✅ Data sent! HTTP code: " + String(httpResponseCode));
      String response = http.getString();
      Serial.println("Response: " + response);
    } else {
      Serial.println("❌ Send failed. Error: " + String(httpResponseCode));
    }

    http.end();
  } else {
    Serial.println("⚠️  WiFi disconnected. Status: " + String(WiFi.status()));
    Serial.println("Attempting to reconnect...");
    WiFi.reconnect();
  }

  delay(60000);
}

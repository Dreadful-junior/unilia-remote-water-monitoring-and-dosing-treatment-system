/*
 * UniLi Water Monitoring System - CONNECTION RESCUE FIRMWARE
 * Use this to diagnose why the ESP32 isn't reaching the dashboard.
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

// --- Dynamic Settings ---
float max_turbidity = 50.0, max_tds = 500.0, max_temp = 35.0;
int sampling_interval = 5;

// --- State Variables ---
float turbidity = 0, tds = 0, temperature = 0, waterLevel = 0;
bool pumpRunning = false;
unsigned long lastSendTime = 0;

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n\n=== CONNECTION RESCUE START ===");
  
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH); // Pump OFF

  Serial.printf("[WIFI] Connecting to: %s\n", ssid);
  WiFi.begin(ssid, password);
  
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 20) {
    delay(1000);
    Serial.print(".");
    retry++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WIFI] CONNECTED!");
    Serial.print("[WIFI] ESP32 IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\n[WIFI] FAILED TO CONNECT. Check Hotspot/Password.");
  }
  
  tempSensor.begin();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WIFI] Connection lost! Reconnecting...");
    WiFi.begin(ssid, password);
    delay(5000);
    return;
  }

  // Read basic sensor data
  int turbRaw = analogRead(TURBIDITY_PIN);
  turbidity = (1.0f - (turbRaw / 4095.0f)) * 1000.0f;
  tds = analogRead(TDS_PIN) * 0.5f;
  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);

  // Attempt to sync
  if (millis() - lastSendTime > (sampling_interval * 1000)) {
    Serial.println("[SYNC] Attempting to send data to: " + String(receiveUrl));
    
    HTTPClient http;
    http.begin(receiveUrl);
    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<512> doc;
    doc["api_key"]     = apiKey;
    doc["turbidity"]   = turbidity;
    doc["tds"]         = tds;
    doc["temperature"] = temperature;
    doc["pump"]        = pumpRunning ? 1 : 0;

    String body; serializeJson(doc, body);
    int httpCode = http.POST(body);

    if (httpCode > 0) {
      Serial.printf("[SYNC] SUCCESS! HTTP Code: %d\n", httpCode);
      String response = http.getString();
      Serial.println("[DASH] Server Response: " + response);
    } else {
      Serial.printf("[SYNC] FAILED! Error: %s\n", http.errorToString(httpCode).c_str());
      Serial.println("[HELP] If error is 'connection refused', check your Firewall or Server IP.");
    }
    
    http.end();
    lastSendTime = millis();
  }
  
  delay(100);
}

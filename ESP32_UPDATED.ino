#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// --- WiFi & Server ---
const char* ssid       = "Honor 9N";
const char* password   = "12345678";
const char* serverName = "http://192.168.43.248/water%20system/api/receive.php";

// --- Pin Definitions ---
#define RELAY_PIN      33
#define TURBIDITY_PIN  34
#define TDS_PIN        35
#define TRIG_PIN       18
#define ECHO_PIN       19
#define ONE_WIRE_BUS   16
#define MANUAL_BTN     23

// --- Water Quality Thresholds ---
#define TURBIDITY_BAD   700.0
#define TURBIDITY_GOOD  650.0
#define TDS_BAD         800.0
#define TDS_GOOD        750.0

// --- Bucket & Ultrasonic ---
#define BUCKET_MAX_CM   21.0
#define BUCKET_MIN_CM    2.0
#define BUCKET_LITRES    5.0

// --- Dosing Config ---
#define DOSE_ML_PER_LITRE   2.0
#define PUMP_ML_PER_SEC     1.5

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature tempSensor(&oneWire);

// --- Global State ---
float turbidity = 0, tds = 0, temperature = 0;
float waterCm = 0, waterLitres = 0, doseMl = 0;
long doseDuration = 0;
bool pumpRunning = false, dosingActive = false;
unsigned long doseStartTime = 0;
unsigned long lastSendTime = 0;
unsigned long lastButtonCheck = 0;
bool lastButtonState = HIGH;

enum Mode { MODE_AUTO, MODE_MANUAL_ON, MODE_MANUAL_OFF };
Mode currentMode = MODE_AUTO;

// ================= PUMP CONTROL (INTEGRATED) =================
void setPump(bool on) {
  pinMode(RELAY_PIN, OUTPUT);
  if (on) {
    digitalWrite(RELAY_PIN, LOW); // ON
    Serial.println("PUMP ON");
  } else {
    digitalWrite(RELAY_PIN, HIGH); // OFF
    delay(10);
    pinMode(RELAY_PIN, INPUT); // Float to ensure OFF
    Serial.println("PUMP OFF");
  }
  pumpRunning = on;
}

// ================= ULTRASONIC & LEVEL =================
float readDistanceCm() {
  digitalWrite(TRIG_PIN, LOW); delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH); delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  return (duration == 0) ? -1 : (duration * 0.0343) / 2.0;
}

void updateWaterLevel() {
  float dist = readDistanceCm();
  if (dist > 0) {
    waterCm = dist;
    float pct = 1.0 - ((waterCm - BUCKET_MIN_CM) / (BUCKET_MAX_CM - BUCKET_MIN_CM));
    pct = constrain(pct, 0.0, 1.0);
    waterLitres = pct * BUCKET_LITRES;
    doseMl = waterLitres * DOSE_ML_PER_LITRE;
    doseDuration = (long)((doseMl / PUMP_ML_PER_SEC) * 1000.0);
  }
}

// ================= DOSING LOGIC =================
void startDose() {
  if (dosingActive || waterLitres < 0.1) return;
  updateWaterLevel();
  dosingActive = true;
  doseStartTime = millis();
  setPump(true);
  Serial.println("STARTING DOSE");
}

void checkDoseTimer() {
  if (!dosingActive) return;
  if (millis() - doseStartTime >= (unsigned long)doseDuration) {
    dosingActive = false;
    setPump(false);
    Serial.println("DOSE COMPLETE");
  }
}

// ================= MANUAL BUTTON LOGIC =================
void checkManualButton() {
  bool buttonState = digitalRead(MANUAL_BTN);
  if (buttonState == LOW && lastButtonState == HIGH) { // Button pressed
    delay(50); // Debounce
    if (digitalRead(MANUAL_BTN) == LOW) {
      // Cycle through modes: AUTO -> MANUAL_OFF -> MANUAL_ON -> AUTO
      if (currentMode == MODE_AUTO) {
        currentMode = MODE_MANUAL_OFF;
        Serial.println("MODE: MANUAL OFF");
      } else if (currentMode == MODE_MANUAL_OFF) {
        currentMode = MODE_MANUAL_ON;
        Serial.println("MODE: MANUAL ON");
      } else if (currentMode == MODE_MANUAL_ON) {
        currentMode = MODE_AUTO;
        Serial.println("MODE: AUTO");
      }
    }
  }
  lastButtonCheck = millis();
  lastButtonState = buttonState;
}

// ================= WEB SYNC (The Dashboard Controller) =================
void sendToDashboard() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (millis() - lastSendTime < 5000) return;
  lastSendTime = millis();

  HTTPClient http;
  http.begin(serverName);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<512> doc;
  doc["api_key"] = "your-secret-api-key-123";
  doc["turbidity"] = turbidity;
  doc["tds"] = tds;
  doc["temperature"] = temperature;
  doc["water_level"] = waterLitres;
  doc["pump_status"] = pumpRunning ? 1 : 0;

  String body; serializeJson(doc, body);
  int httpCode = http.POST(body);

  if (httpCode == 200) {
    String response = http.getString();
    StaticJsonDocument<512> respDoc;
    deserializeJson(respDoc, response);

    // READ DASHBOARD COMMANDS
    String cmdMode = respDoc["command_mode"] | "auto";
    String cmdState = respDoc["command_state"] | "off";

    if (cmdMode == "manual") {
      if (cmdState == "on") currentMode = MODE_MANUAL_ON;
      else currentMode = MODE_MANUAL_OFF;
    } else {
      currentMode = MODE_AUTO;
    }

    Serial.printf("DASHBOARD MODE: %s, STATE: %s\n", cmdMode.c_str(), cmdState.c_str());
  }
  http.end();
}

// ================= SETUP =================
void setup() {
  Serial.begin(115200);
  setPump(false); // Force OFF
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  pinMode(MANUAL_BTN, INPUT_PULLUP);
  tempSensor.begin();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) { delay(500); Serial.print("."); }
  Serial.println("\nSystem Integrated.");
}

// ================= LOOP =================
void loop() {
  checkDoseTimer();
  if (millis() - lastButtonCheck >= 100) checkManualButton();

  // 1. Read Sensors
  int turbRaw = analogRead(TURBIDITY_PIN);
  float turbV = turbRaw * (3.3 / 4095.0);
  turbidity = (turbV < 0.6) ? 0 : map(turbRaw, 0, 4095, 1000, 0);
  tds = (analogRead(TDS_PIN) * (3.3 / 4095.0)) * 500.0;
  tempSensor.requestTemperatures();
  temperature = tempSensor.getTempCByIndex(0);
  updateWaterLevel();

  // 2. REMOTE & AUTO LOGIC
  if (currentMode == MODE_AUTO && !dosingActive) {
    bool waterIsBad = (turbidity >= TURBIDITY_BAD || tds >= TDS_BAD);
    bool waterIsGood = (turbidity <= TURBIDITY_GOOD && tds <= TDS_GOOD);

    if (!pumpRunning && waterIsBad) startDose();
    else if (pumpRunning && waterIsGood) { setPump(false); dosingActive = false; }
  }
  else if (currentMode == MODE_MANUAL_ON) {
    setPump(true);
  }
  else if (currentMode == MODE_MANUAL_OFF) {
    setPump(false);
    dosingActive = false;
  }

  sendToDashboard();
  delay(100); // Fast reaction
}
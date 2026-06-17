#include <ESP8266WiFi.h>
#include <ThingSpeak.h>
#include <Servo.h>

const char* ssid = "NiahPunya-2.4GHZ";
const char* password = "saniahan1970";

unsigned long channelID = 3350840;
const char* writeAPIKey = "P0LV8WAJFKQ8FGVL";

WiFiClient client;

#define TRIG_PIN D5
#define ECHO_PIN D6
#define WATER_SENSOR A0
#define SERVO_PIN D4

Servo feederServo;

unsigned long lastFeedTime = 0;
const unsigned long feedInterval = 30000; // 30 seconds

void setup() {

  Serial.begin(115200);

  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);

  feederServo.attach(SERVO_PIN);
  feederServo.write(0);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("\nWiFi Connected");

  ThingSpeak.begin(client);

  Serial.println("System Ready");
}

void loop() {

  // Ultrasonic
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);

  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH);

  float distance = duration * 0.034 / 2;

  // Water Sensor
  int waterLevel = analogRead(WATER_SENSOR);

  // Upload to ThingSpeak
  ThingSpeak.setField(1, distance);
  ThingSpeak.setField(2, waterLevel);

  int status = ThingSpeak.writeFields(channelID, writeAPIKey);

  // Dashboard Feed Command
  long command = ThingSpeak.readLongField(channelID, 3);

  if (command == 1) {

    Serial.println("Dashboard Feed");

    feederServo.write(90);
    delay(1000);
    feederServo.write(0);

    ThingSpeak.writeField(channelID, 3, 0, writeAPIKey);
  }

// Auto Feed when bowl is low
if (distance > 10 &&
    millis() - lastFeedTime > feedInterval) {

  Serial.println("Food Low - Auto Dispense");

  feederServo.write(90);

  while (true) {

    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);

    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);

    digitalWrite(TRIG_PIN, LOW);

    long duration = pulseIn(ECHO_PIN, HIGH);

    float currentDistance = duration * 0.034 / 2;

    Serial.print("Current Distance: ");
    Serial.println(currentDistance);

    // Stop when bowl almost full
    if (currentDistance <= 5) {
      break;
    }

    delay(300);
  }

  feederServo.write(0);

  Serial.println("Bowl Full - Stop Dispensing");

  lastFeedTime = millis();
}

  Serial.print("Distance: ");
  Serial.print(distance);

  Serial.print(" cm | Water: ");
  Serial.print(waterLevel);

  Serial.print(" | Command: ");
  Serial.print(command);

  Serial.print(" | Status: ");
  Serial.println(status);

  delay(20000);
}

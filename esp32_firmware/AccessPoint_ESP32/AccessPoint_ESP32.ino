/*
  =====================================================================
   AccessPoint - Firmware ESP32 (leitor RFID + trava + LCD)
  =====================================================================

  Este firmware conecta o ESP32 de verdade ao sistema AccessPoint (PHP +
  MySQL) rodando no XAMPP. Não existe cadastro manual de dispositivo no
  painel: o ESP32 se identifica sozinho pelo endereço MAC na primeira vez
  que fala com o servidor, e aparece automaticamente em "Dispositivos
  (ESP32)". Não é preciso editar nenhum DEVICE_ID aqui.

  O que ele faz:
    1) Conecta no Wi-Fi;
    2) A cada poucos segundos, avisa o servidor que está online
       (api/heartbeat.php) e recebe de volta se deve entrar em
       "modo de cadastro de tag" (enroll_mode);
    3) Fica lendo o leitor RFID RC522;
    4) Em operação normal, ao detectar um cartão, envia o UID para
       api/verify_card.php e libera (LED verde + destrava o servo) ou
       nega (LED vermelho + buzzer) o acesso, mostrando tudo no LCD;
    5) Em modo de cadastro (ativado pelo painel, tela "Cartões de
       Acesso"), ao detectar um cartão, envia o UID para
       api/enroll_card.php em vez de verificar acesso - é assim que o
       operador cadastra uma tag nova sem digitar o UID manualmente.

  O simulador de cartões do site (simulator.php) continua existindo -
  ele testa a mesma api/verify_card.php contra um ESP32 realmente online,
  sem precisar aproximar um cartão fisicamente do leitor.

  ---------------------------------------------------------------------
  BIBLIOTECAS NECESSÁRIAS (Arduino IDE > Ferramentas > Gerenciar Bibliotecas)
  ---------------------------------------------------------------------
    - "MFRC522" (autor: GithubCommunity / miguelbalboa)
    - "LiquidCrystal I2C" (autor: Frank de Brabander)
    - "ESP32Servo" (autor: Kevin Harrington / John K. Bennett)
    - "ArduinoJson" (autor: Benoit Blanchon) - versão 6.x recomendada

  Placa (Ferramentas > Placa): "ESP32 Dev Module"
  Se a placa ESP32 não aparecer, instale o suporte em:
  Arquivo > Preferências > URLs Adicionais para Gerenciadores de Placas:
    https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
  Depois: Ferramentas > Placa > Gerenciador de Placas > procurar "esp32" > instalar.

  ---------------------------------------------------------------------
  ESQUEMA DE LIGAÇÃO (conforme montagem física)
  ---------------------------------------------------------------------
    LED verde ........... GPIO 2   (com resistor 220-330R para o GND)
    LED amarelo .......... GPIO 3  (= RX0, com resistor 220-330R para o GND)
    LED vermelho ......... GPIO 1  (= TX0, com resistor 220-330R para o GND)
    Buzzer ............... GPIO 16 (outro terminal no GND)
    Servo SG90 (sinal) ... GPIO 17 (alimentação 5V vem de um Arduino à parte,
                                     usado só como fonte de 5V; GND do Arduino
                                     ligado ao GND comum da protoboard/ESP32)
    LCD 16x2 I2C  SDA .... GPIO 21
    LCD 16x2 I2C  SCL .... GPIO 22
    RFID RC522    SDA/SS . GPIO 5
    RFID RC522    RST .... GPIO 4
    RFID RC522    SCK .... GPIO 18
    RFID RC522    MISO ... GPIO 19
    RFID RC522    MOSI ... GPIO 23
    RFID RC522 alimentado em 3V3 do ESP32 / GND comum
    (pino IRQ do RC522 não é usado; não há mais botão físico no projeto)

  ⚠️ ATENÇÃO - GPIO1 e GPIO3 (LEDs vermelho e amarelo):
  Esses pinos são o TX0/RX0, os mesmos usados pelo Monitor Serial/USB.
  É normal o LED vermelho "piscar" um pouco toda vez que o código faz
  Serial.println(...) - isso é inofensivo. Se quiser eliminar esse
  efeito, é só comentar as linhas Serial.println() abaixo (o LCD já
  mostra todo o status importante).

  ---------------------------------------------------------------------
  COMPORTAMENTO DO HARDWARE
  ---------------------------------------------------------------------
  - Servo: 0° = trava fechada (repouso) / 90° = trava aberta.
  - LED vermelho: aceso sempre que a trava está fechada (estado padrão);
    apaga durante o acesso liberado (LED verde assume) e pisca
    rapidamente para sinalizar acesso negado ou erro.
  - LED verde: aceso só durante o tempo em que a trava está destravada
    (acesso liberado).
  - LED amarelo: aceso durante o modo de configuração (cadastro de tag
    pelo painel) e piscando enquanto tenta conectar/reconectar ao Wi-Fi.
  - Buzzer: padrões diferentes de bipe para sucesso, acesso negado,
    erro/problema e tag cadastrada - ver função beepPattern() abaixo.
  - LCD: sempre mostra uma mensagem do estado atual do sistema.

  ---------------------------------------------------------------------
  FORMATO DO UID DO CARTÃO
  ---------------------------------------------------------------------
  O UID é enviado em hexadecimal maiúsculo, sem separadores (ex: "A1B2C3D4").
  Você pode cadastrar esse UID em "Cartões de Acesso" digitando manualmente,
  OU usando o botão "📡 Ler UID pelo leitor" naquela tela, que coloca este
  ESP32 em modo de cadastro e captura o UID automaticamente ao aproximar a
  tag do RC522.
  =====================================================================
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <ESP32Servo.h>

// ============== CONFIGURAÇÕES (EDITE AQUI) ==============
const char* WIFI_SSID     = "Valdeci";
const char* WIFI_PASSWORD = "valdeci518481";

// IP local do computador rodando o XAMPP (descubra com "ipconfig" no
// Windows, procure por "Endereço IPv4"). O ESP32 e o PC precisam estar
// na mesma rede Wi-Fi. Não precisa mais configurar DEVICE_ID/DEVICE_NAME
// aqui - o ESP32 se registra sozinho pelo MAC na primeira conexão.
const char* SERVER_HOST   = "192.168.2.110";
const int   SERVER_PORT   = 80;

// Endereço I2C do LCD - a maioria dos módulos usa 0x27, alguns usam 0x3F.
// Se o LCD ligar mas não mostrar texto, troque para 0x3F.
const uint8_t LCD_ADDRESS = 0x27;
// ==========================================================

// ---------------- Pinos (conforme esquema de ligação) ----------------
#define LED_VERDE      2
#define LED_AMARELO    3   // = RX0
#define LED_VERMELHO   1   // = TX0
#define BUZZER_PIN     16
#define SERVO_PIN      17

#define RFID_SS_PIN    5
#define RFID_RST_PIN   4
#define RFID_SCK_PIN   18
#define RFID_MISO_PIN  19
#define RFID_MOSI_PIN  23

#define LCD_SDA_PIN    21
#define LCD_SCL_PIN    22

// ---------------- Parâmetros de funcionamento ----------------
#define SERVO_TRAVADO         0     // ângulo da trava fechada
#define SERVO_DESTRAVADO      90    // ângulo da trava aberta (ajuste conforme a fechadura)
#define TEMPO_DESTRAVADO_MS   5000  // tempo que a trava fica aberta
#define INTERVALO_HEARTBEAT   3000  // avisa "estou online" a cada 3s (também detecta modo de cadastro)
#define COOLDOWN_LEITURA_MS   2500  // evita ler o mesmo cartão várias vezes seguidas

MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
LiquidCrystal_I2C lcd(LCD_ADDRESS, 16, 2);
Servo trava;

String urlVerify;
String urlHeartbeat;
String urlEnrollCard;

String deviceMac = "";
int    deviceId = 0;
String deviceName = "AccessPoint";

bool enrollModeAtivo = false;

unsigned long ultimoHeartbeat = 0;
unsigned long ultimaLeituraMs = 0;
String ultimoUidLido = "";

// ---------------------------------------------------------------------
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println();
  Serial.println("========================================");
  Serial.println("[BOOT] AccessPoint ESP32 - iniciando...");

  pinMode(LED_VERDE, OUTPUT);
  pinMode(LED_AMARELO, OUTPUT);
  pinMode(LED_VERMELHO, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(LED_VERDE, LOW);
  digitalWrite(LED_AMARELO, LOW);
  digitalWrite(LED_VERMELHO, LOW);
  digitalWrite(BUZZER_PIN, LOW);
  Serial.println("[BOOT] Pinos de LED/buzzer configurados.");

  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  // Velocidade mais baixa no barramento I2C - o padrão do ESP32 (400kHz)
  // costuma ser rápido demais para módulos LCD baratos com fiação de
  // protoboard, causando exatamente caracteres corrompidos/embaralhados
  // na tela mesmo com o backlight acendendo normalmente.
  Wire.setClock(50000);
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("AccessPoint");
  lcd.setCursor(0, 1);
  lcd.print("Iniciando...");
  Serial.println("[BOOT] LCD inicializado (se os caracteres saírem embaralhados, confira a fiação SDA=21/SCL=22 e o endereço I2C).");

  trava.setPeriodHertz(50);
  trava.attach(SERVO_PIN, 500, 2400);
  trava.write(SERVO_TRAVADO);
  Serial.println("[BOOT] Servo inicializado.");

  SPI.begin(RFID_SCK_PIN, RFID_MISO_PIN, RFID_MOSI_PIN, RFID_SS_PIN);
  rfid.PCD_Init();
  Serial.println("[BOOT] RFID RC522 inicializado.");

  // O MAC já pode ser lido assim que o rádio Wi-Fi é inicializado, mesmo
  // antes de conectar - é ele que identifica este ESP32 no sistema.
  WiFi.mode(WIFI_STA);
  deviceMac = WiFi.macAddress();
  Serial.println("[BOOT] MAC deste ESP32: " + deviceMac);

  urlVerify      = "http://" + String(SERVER_HOST) + ":" + String(SERVER_PORT) + "/access_point/api/verify_card.php";
  urlHeartbeat   = "http://" + String(SERVER_HOST) + ":" + String(SERVER_PORT) + "/access_point/api/heartbeat.php";
  urlEnrollCard  = "http://" + String(SERVER_HOST) + ":" + String(SERVER_PORT) + "/access_point/api/enroll_card.php";
  Serial.println("[BOOT] URL do heartbeat: " + urlHeartbeat);

  conectarWiFi();
  enviarHeartbeat();

  digitalWrite(LED_VERMELHO, HIGH); // repouso: trava fechada
  telaAguardando();
  Serial.println("[BOOT] Setup concluido. Entrando no loop principal.");
  Serial.println("========================================");
}

// ---------------------------------------------------------------------
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    conectarWiFi();
  }

  if (millis() - ultimoHeartbeat > INTERVALO_HEARTBEAT) {
    enviarHeartbeat();
  }

  // Entrar/sair do modo de cadastro conforme o painel solicitou
  static bool telaCadastroAtiva = false;
  if (enrollModeAtivo && !telaCadastroAtiva) {
    telaCadastroAtiva = true;
    entrarModoCadastro();
  } else if (!enrollModeAtivo && telaCadastroAtiva) {
    telaCadastroAtiva = false;
    sairModoCadastro();
  }

  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) {
    return;
  }

  String uid = lerUID();

  // Evita processar o mesmo cartão várias vezes seguidas (ex: cartão
  // parado sobre o leitor)
  if (uid == ultimoUidLido && (millis() - ultimaLeituraMs) < COOLDOWN_LEITURA_MS) {
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
    return;
  }
  ultimoUidLido = uid;
  ultimaLeituraMs = millis();

  if (enrollModeAtivo) {
    Serial.println("Tag lida em modo cadastro: " + uid);
    processarCadastro(uid);
    enrollModeAtivo = false; // sai do modo localmente, não espera o próximo heartbeat confirmar
    telaCadastroAtiva = false;
  } else {
    Serial.println("Cartao detectado: " + uid);
    processarCartao(uid);
  }

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

// ---------------------------------------------------------------------
String lerUID() {
  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

// ---------------------------------------------------------------------
// Operação normal: verificar cartão e liberar/negar acesso
// ---------------------------------------------------------------------
void processarCartao(String uid) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Verificando...");
  lcd.setCursor(0, 1);
  lcd.print(uid);

  if (WiFi.status() != WL_CONNECTED) {
    exibirErro("Sem WiFi!");
    return;
  }

  HTTPClient http;
  http.begin(urlVerify);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  StaticJsonDocument<192> reqDoc;
  reqDoc["card_uid"] = uid;
  reqDoc["mac_address"] = deviceMac;
  String corpo;
  serializeJson(reqDoc, corpo);

  int httpCode = http.POST(corpo);

  if (httpCode != 200) {
    Serial.println("Erro HTTP: " + String(httpCode));
    http.end();
    exibirErro("Erro servidor");
    return;
  }

  String resposta = http.getString();
  http.end();

  StaticJsonDocument<256> resDoc;
  DeserializationError erro = deserializeJson(resDoc, resposta);
  if (erro) {
    Serial.println("Erro ao interpretar resposta: " + String(erro.c_str()));
    exibirErro("Resposta invalida");
    return;
  }

  bool sucesso    = resDoc["success"]   | false;
  bool abrePorta  = resDoc["open_door"] | false;
  String mensagem = resDoc["message"]   | "";

  if (sucesso && abrePorta) {
    exibirLiberado(mensagem);
  } else {
    exibirNegado(mensagem);
  }
}

// ---------------------------------------------------------------------
void exibirLiberado(String mensagem) {
  digitalWrite(LED_VERMELHO, LOW);
  digitalWrite(LED_VERDE, HIGH);
  beepPattern(1, 150, 0); // sucesso: 1 bipe

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Acesso Liberado");

  trava.write(SERVO_DESTRAVADO);
  delay(TEMPO_DESTRAVADO_MS);
  trava.write(SERVO_TRAVADO);

  digitalWrite(LED_VERDE, LOW);
  digitalWrite(LED_VERMELHO, HIGH); // volta ao repouso: trava fechada
  telaAguardando();
}

// ---------------------------------------------------------------------
void exibirNegado(String mensagem) {
  beepPattern(2, 120, 100); // negado: 2 bipes
  piscarVermelho(3, 100);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Acesso Negado");
  lcd.setCursor(0, 1);
  lcd.print(mensagem.substring(0, 16));

  delay(1800);
  digitalWrite(LED_VERMELHO, HIGH); // repouso: trava continua fechada
  telaAguardando();
}

// ---------------------------------------------------------------------
void exibirErro(String mensagem) {
  beepPattern(3, 80, 80); // erro/problema: 3 bipes rápidos
  piscarVermelho(5, 80);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Erro:");
  lcd.setCursor(0, 1);
  lcd.print(mensagem.substring(0, 16));

  delay(1800);
  digitalWrite(LED_VERMELHO, HIGH);
  telaAguardando();
}

// ---------------------------------------------------------------------
void telaAguardando() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(deviceName.substring(0, 16));
  lcd.setCursor(0, 1);
  lcd.print("Aproxime cartao");
}

// ---------------------------------------------------------------------
// Modo de configuração: cadastro de tag pelo leitor (ativado pelo painel)
// ---------------------------------------------------------------------
void entrarModoCadastro() {
  digitalWrite(LED_AMARELO, HIGH);
  beepPattern(1, 50, 0); // chirp curto ao entrar em modo cadastro

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Modo Cadastro");
  lcd.setCursor(0, 1);
  lcd.print("Aproxime a tag");
}

// ---------------------------------------------------------------------
void sairModoCadastro() {
  digitalWrite(LED_AMARELO, LOW);
  telaAguardando();
}

// ---------------------------------------------------------------------
void processarCadastro(String uid) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Enviando tag...");
  lcd.setCursor(0, 1);
  lcd.print(uid);

  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(LED_AMARELO, LOW);
    exibirErro("Sem WiFi!");
    return;
  }

  HTTPClient http;
  http.begin(urlEnrollCard);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  StaticJsonDocument<192> reqDoc;
  reqDoc["mac_address"] = deviceMac;
  reqDoc["uid"] = uid;
  String corpo;
  serializeJson(reqDoc, corpo);

  int httpCode = http.POST(corpo);
  http.end();

  digitalWrite(LED_AMARELO, LOW);

  if (httpCode != 200) {
    Serial.println("Erro HTTP no cadastro: " + String(httpCode));
    exibirErro("Erro ao cadastrar");
    return;
  }

  beepPattern(2, 60, 60); // tag cadastrada: bipe duplo curto
  digitalWrite(LED_VERDE, HIGH);

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Tag Lida!");
  lcd.setCursor(0, 1);
  lcd.print(uid);
  delay(2000);

  digitalWrite(LED_VERDE, LOW);
  telaAguardando();
}

// ---------------------------------------------------------------------
// Buzzer e LEDs - padrões de feedback
// ---------------------------------------------------------------------
void beepPattern(int vezes, int duracaoMs, int pausaMs) {
  for (int i = 0; i < vezes; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(duracaoMs);
    digitalWrite(BUZZER_PIN, LOW);
    if (i < vezes - 1) delay(pausaMs);
  }
}

// ---------------------------------------------------------------------
void piscarVermelho(int vezes, int duracaoMs) {
  for (int i = 0; i < vezes; i++) {
    digitalWrite(LED_VERMELHO, LOW);
    delay(duracaoMs);
    digitalWrite(LED_VERMELHO, HIGH);
    delay(duracaoMs);
  }
}

// ---------------------------------------------------------------------
void conectarWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Conectando WiFi");

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int tentativas = 0;
  while (WiFi.status() != WL_CONNECTED && tentativas < 40) {
    digitalWrite(LED_AMARELO, !digitalRead(LED_AMARELO));
    delay(250);
    lcd.setCursor(tentativas % 16, 1);
    lcd.print(".");
    tentativas++;
  }
  digitalWrite(LED_AMARELO, LOW);

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi conectado! IP: " + WiFi.localIP().toString());
    Serial.println("MAC deste ESP32: " + deviceMac);
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("WiFi conectado!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.localIP().toString());
    delay(1500);
  } else {
    Serial.println("Falha ao conectar no WiFi");
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Falha no WiFi");
    lcd.setCursor(0, 1);
    lcd.print("Tentando de novo");
    delay(1500);
  }
}

// ---------------------------------------------------------------------
// Heartbeat: avisa que está online, se registra automaticamente pelo MAC
// na primeira vez, e descobre se deve entrar em modo de cadastro de tag.
// ---------------------------------------------------------------------
void enviarHeartbeat() {
  ultimoHeartbeat = millis();
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HEARTBEAT] Pulado - sem WiFi.");
    return;
  }

  HTTPClient http;
  http.begin(urlHeartbeat);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(4000);

  StaticJsonDocument<128> doc;
  doc["mac_address"] = deviceMac;
  String corpo;
  serializeJson(doc, corpo);

  int httpCode = http.POST(corpo);

  if (httpCode == 200) {
    String resposta = http.getString();
    Serial.println("[HEARTBEAT] OK -> " + resposta);
    StaticJsonDocument<256> resDoc;
    if (!deserializeJson(resDoc, resposta)) {
      deviceId       = resDoc["device_id"]   | deviceId;
      const char* nm = resDoc["device_name"] | "";
      if (strlen(nm) > 0) deviceName = String(nm);
      enrollModeAtivo = resDoc["enroll_mode"] | false;
    }
  } else if (httpCode > 0) {
    // Chegou ao servidor, mas ele respondeu um erro HTTP (ex: 404 = URL
    // errada, 500 = erro no PHP). Confira o caminho/porta em SERVER_HOST.
    Serial.println("[HEARTBEAT] Servidor respondeu HTTP " + String(httpCode) + " (chegou no servidor, mas algo está errado na URL/PHP).");
  } else {
    // Código negativo = não conseguiu nem abrir a conexão TCP. Confira:
    // SERVER_HOST (IP certo do PC?), se o Apache/XAMPP está ligado, e se
    // ESP32 e PC estão na mesma rede/sub-rede Wi-Fi (sem isolamento de
    // dispositivos no roteador).
    Serial.println("[HEARTBEAT] Falha de conexao (" + String(httpCode) + ") = " + http.errorToString(httpCode) + " -> nao conseguiu alcancar " + urlHeartbeat);
  }

  http.end();
}

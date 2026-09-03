# Integração real do ESP32 com o AccessPoint

Este pacote conecta o ESP32 **de verdade** ao sistema (sem tirar o simulador
de cartões, que continua em `simulator.php` para testar chamadas contra um
ESP32 já online, sem precisar aproximar um cartão fisicamente do leitor).

## Como funciona (sem cadastro manual de dispositivo)

Não existe mais formulário de "criar dispositivo" no painel. O ESP32 se
identifica sozinho pelo **endereço MAC** (único de fábrica) na primeira vez
que fala com o servidor, e aparece automaticamente em *Dispositivos (ESP32)*.
Isso garante que só existam no sistema dispositivos que realmente estão
ligados e conectados — não é possível "inventar" um ESP32 pelo painel.

- `api/heartbeat.php`: o ESP32 chama esse endpoint a cada ~3s avisando
  "estou online" (usa as colunas `status`/`last_seen` da tabela `devices`) e
  recebe de volta se deve entrar em **modo de cadastro de tag**
  (`enroll_mode`).
- `api/verify_card.php`: chamado a cada leitura de cartão em operação normal.
  Também aceita `mac_address` (ESP32 real) e continua aceitando `device_id`
  (usado internamente pelo Simulador e pelo botão "Testar" em Cartões de
  Acesso, sem marcar o dispositivo como online).
- `api/enroll_start.php`, `api/enroll_poll.php`, `api/enroll_cancel.php`,
  `api/enroll_card.php`: suportam o modo de cadastro de tag pelo leitor,
  disparado a partir da tela *Cartões de Acesso*.
- `esp32_firmware/AccessPoint_ESP32.ino` (**novo firmware**): descrito abaixo.
- `manage_devices.php`: só lista dispositivos reais (nome, MAC, localização,
  status online/horário do último sinal). Permite renomear/definir
  localização e excluir, mas não criar manualmente nem forçar "online".

Nada na estrutura de páginas, banco de dados ou navegação foi reorganizado.

## Passo a passo

1. **Abra `AccessPoint_ESP32.ino` na Arduino IDE** e instale as bibliotecas
   listadas no topo do arquivo (MFRC522, LiquidCrystal I2C, ESP32Servo,
   ArduinoJson) e o suporte à placa ESP32, se ainda não tiver.

2. **Edite só o bloco `CONFIGURAÇÕES (EDITE AQUI)`** no início do arquivo:
   - `WIFI_SSID` / `WIFI_PASSWORD`: sua rede Wi-Fi (o ESP32 e o PC com o
     XAMPP precisam estar na mesma rede).
   - `SERVER_HOST`: o IP local do computador que roda o XAMPP. No Windows,
     abra o `cmd` e digite `ipconfig`, use o "Endereço IPv4" (ex:
     `192.168.0.10`). **Não use `localhost`** — o ESP32 é outro dispositivo
     na rede.
   - Não existe mais `DEVICE_ID`/`DEVICE_NAME` para editar — o ESP32 se
     identifica sozinho.

3. **Selecione a placa** *ESP32 Dev Module* e a porta COM correspondente,
   depois faça o upload.

4. **Libere o Apache do XAMPP no firewall do Windows** (se ainda não
   estiver liberado), para que outros dispositivos da rede consigam
   acessar `http://SEU_IP/access_point/...`. Teste primeiro pelo celular
   (mesma rede Wi-Fi) abrindo `http://SEU_IP/access_point/login.php` no
   navegador.

5. **Ligue o ESP32.** Depois de conectar no Wi-Fi, ele já aparece sozinho em
   *Dispositivos (ESP32)* com um nome padrão (ex: `ESP32-A1B2C3`). Edite o
   nome/localização por lá se quiser (ex: "Portaria Principal").

6. **Cadastre uma tag pelo leitor.** Vá em *Cartões de Acesso*, preencha o
   formulário de novo cartão e, no bloco "📡 Ler UID pelo leitor", escolha o
   ESP32 (só aparece se estiver online) e clique em **Iniciar leitura**: o
   LED amarelo do ESP32 acende e o LCD mostra "Modo Cadastro". Aproxime a
   tag do RC522 — o UID é capturado automaticamente e preenchido no campo.
   Também dá para digitar o UID manualmente, se preferir.

7. **Teste.** Aproxime a tag cadastrada do leitor: o LCD, os LEDs, o buzzer
   e o servo devem reagir, e o acesso aparece em *Logs de Acesso* e no
   *Dashboard* normalmente.

## Esquema de ligação (para conferência)

| Componente          | Pino ESP32 |
|----------------------|-----------|
| LED verde             | GPIO 2 |
| LED amarelo           | GPIO 3 (RX0) |
| LED vermelho          | GPIO 1 (TX0) |
| Buzzer                | GPIO 16 |
| Servo SG90 (sinal)    | GPIO 17 |
| LCD I2C — SDA          | GPIO 21 |
| LCD I2C — SCL          | GPIO 22 |
| RC522 — SDA/SS         | GPIO 5 |
| RC522 — RST            | GPIO 4 |
| RC522 — SCK            | GPIO 18 |
| RC522 — MISO           | GPIO 19 |
| RC522 — MOSI           | GPIO 23 |

- LEDs com resistor de 220–330Ω para o GND.
- Buzzer com o outro terminal no GND.
- Servo alimentado por um Arduino usado só como fonte de 5V (vermelho no
  5V do Arduino, marrom no GND do Arduino); o GND do Arduino compartilha a
  mesma linha de GND do ESP32/protoboard. O 5V do Arduino **não** é ligado
  ao 3V3 do ESP32.
- LCD alimentado em 5V, GND comum.
- RC522 alimentado em 3V3 do ESP32, GND comum. Pino IRQ não é usado.
- Não há mais botão físico no projeto.

⚠️ **GPIO1 e GPIO3 são o TX0/RX0** (mesmos pinos do Monitor Serial/USB). É
normal o LED vermelho piscar um pouco a cada `Serial.println()` do
firmware — isso é inofensivo e não afeta a lógica.

## Comportamento do hardware

- **Servo**: 0° = trava fechada (repouso) / 90° = trava aberta.
- **LED vermelho**: aceso sempre que a trava está fechada (estado padrão do
  sistema); apaga durante o acesso liberado (LED verde assume) e pisca
  rapidamente para sinalizar acesso negado ou erro.
- **LED verde**: aceso só durante o tempo em que a trava está destravada
  (acesso liberado), e também pisca brevemente ao concluir o cadastro de
  uma tag nova.
- **LED amarelo**: aceso fixo durante o modo de configuração (cadastro de
  tag) e piscando enquanto tenta conectar/reconectar ao Wi-Fi.
- **Buzzer**: padrões diferentes — 1 bipe para sucesso, 2 para acesso
  negado, 3 rápidos para erro/problema, bipe duplo curto para tag
  cadastrada.
- **LCD**: sempre mostra uma mensagem do estado atual (conectando,
  aguardando cartão, verificando, liberado/negado, erro, modo cadastro).

## Segurança (nota importante)

Assim como o `simulator.php` original, os endpoints `verify_card.php`,
`heartbeat.php` e `enroll_card.php` não exigem autenticação — qualquer
dispositivo na mesma rede pode chamá-los. Isso é adequado para uso em rede
local/escolar controlada. Se este sistema for exposto fora da rede local,
será necessário adicionar autenticação (token por dispositivo, HTTPS, etc.)
antes de usar em produção.

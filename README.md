# 🔐 AccessPoint - Sistema de Controle de Acesso Escolar

Sistema web completo para gestão de controle de acesso em escolas utilizando cartões RFID e ESP32. Desenvolvido com PHP, MySQL, HTML/CSS/JS, e arquitetura pronta para integração com hardware real.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![ESP32](https://img.shields.io/badge/ESP32-FF5A00?style=flat&logo=espressif&logoColor=white)

---

## 📋 Sobre o Projeto

O **AccessPoint** é um sistema de gerenciamento de acesso físico para instituições de ensino. Ele permite:

- Cadastro de cartões RFID com permissões granulares por porta/dispositivo.
- Gerenciamento de dispositivos ESP32 (portas, salas, laboratórios).
- Controle de usuários (Administradores e Direção) com níveis de acesso distintos.
- Registro completo de logs de tentativas de acesso (sucesso/falha).
- Simulador ESP32 integrado para testes sem hardware.
- Interface moderna, responsiva e pronta para integração com hardware real.

---

## 🚀 Funcionalidades

### 👥 Gestão de Usuários
- Dois perfis: **Administrador** (acesso total) e **Direção** (gerenciamento de cartões e dispositivos).
- Criação, edição e exclusão de usuários.
- Proteção contra auto-exclusão.

### 💳 Gestão de Cartões RFID
- Cadastro de cartões com UID único.
- Definição de tipo (Aluno, Professor, Funcionário, Visitante).
- Ativação / bloqueio de cartões.
- Atribuição de permissões por dispositivo (quais portas o cartão pode abrir).
- Ações extras: copiar UID, duplicar cartão, testar acesso, visualizar logs do cartão.

### 📡 Gestão de Dispositivos (ESP32)
- Cadastro de dispositivos (portas, salas, laboratórios).
- Simulação de status online/offline.
- Visualização de última comunicação.

### 📜 Logs de Acesso
- Registro de todas as tentativas (UID, dispositivo, data/hora, resultado, mensagem).
- Filtros por UID, dispositivo, resultado e intervalo de datas.
- Exibição em tempo real.

### 🔧 Simulador ESP32
- Teste de comunicação com a API do sistema.
- Simulação de leitura de cartões em dispositivos específicos.
- Resposta simulada da trava eletrônica.
- Histórico dos últimos testes.

<?php

// Resolve um dispositivo pelo MAC address, criando-o automaticamente se for a
// primeira vez que esse ESP32 fala com o sistema. É assim que o painel nunca
// tem um dispositivo "fake": a linha em `devices` só nasce quando um ESP32
// real chama o servidor.
function getOrCreateDeviceByMac($conn, $mac, $defaultName = null) {
    $mac = strtoupper(trim($mac));

    $stmt = $conn->prepare("SELECT * FROM devices WHERE mac_address = ?");
    $stmt->bind_param("s", $mac);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $device = $result->fetch_assoc();
        $update = $conn->prepare("UPDATE devices SET status = 'online', last_seen = NOW() WHERE id = ?");
        $update->bind_param("i", $device['id']);
        $update->execute();
        $update->close();
        $device['status'] = 'online';
        return $device;
    }

    if (!$defaultName) {
        $shortMac = str_replace(':', '', $mac);
        $defaultName = "ESP32-" . substr($shortMac, -6);
    }

    $insert = $conn->prepare("INSERT INTO devices (device_name, mac_address, status, last_seen) VALUES (?, ?, 'online', NOW())");
    $insert->bind_param("ss", $defaultName, $mac);
    $insert->execute();
    $newId = $insert->insert_id;
    $insert->close();

    $result = $conn->query("SELECT * FROM devices WHERE id = " . intval($newId));
    return $result->fetch_assoc();
}

// Critério único de "está online de verdade" usado em todas as telas.
function isDeviceOnline($device) {
    if (!$device['last_seen'] || $device['status'] != 'online') return false;
    return (time() - strtotime($device['last_seen'])) <= 10;
}
?>

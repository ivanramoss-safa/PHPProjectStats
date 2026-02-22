<?php
$fallbackDir = __DIR__ . '/var/api_fallback_cache';
if (is_dir($fallbackDir)) {
    $files = glob($fallbackDir . '/*.json');
    $deleted = 0;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (empty($data['response']) || isset($data['errors']) && !empty($data['errors'])) {
            unlink($file);
            $deleted++;
        }
    }
    echo "[*] Purged $deleted empty/error fallback cache files.\n";
}
else {
    echo "[!] No fallback dir found.\n";
}

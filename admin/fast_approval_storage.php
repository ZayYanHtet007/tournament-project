<?php

function getApprovalStatusSimple($tournament_id) {
    $file = __DIR__ . '/../data/tournament_approvals.json';
    if (!file_exists($file)) return 'pending';
    
    $content = file_get_contents($file);
    $data = json_decode($content, true) ?: [];
    return $data[$tournament_id] ?? 'pending';
}

function saveApprovalSimple($tournament_id, $status) {
    $file = __DIR__ . '/../data/tournament_approvals.json';
    $dir = dirname($file);
    
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $data = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true) ?: [];
    }
    
    $data[$tournament_id] = $status;
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Fast class (for performance)
class FastApprovalStorage {
    private static $cache = [];
    private static $file;
    
    public static function init() {
        self::$file = __DIR__ . '/../data/tournament_approvals_fast.dat';
        self::loadToMemory();
    }
    
    public static function get($tournament_id) {
        return self::$cache[$tournament_id] ?? 'pending';
    }
    
    public static function save($tournament_id, $status) {
        self::$cache[$tournament_id] = $status;
        self::saveToFile();
        return true;
    }
    
    private static function loadToMemory() {
        if (file_exists(self::$file)) {
            $data = file_get_contents(self::$file);
            self::$cache = unserialize($data) ?: [];
        }
    }
    
    private static function saveToFile() {
        $data = serialize(self::$cache);
        file_put_contents(self::$file, $data, LOCK_EX);
    }
}

// Auto-init fast storage
FastApprovalStorage::init();
?>
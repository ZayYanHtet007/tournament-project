<?php

define('APPROVAL_STORAGE_METHOD', 'fast');


function approval_get_simple($tournament_id)
{
    $file = __DIR__ . '/../data/tournament_approvals.json';
    if (!file_exists($file)) return 'pending';

    $content = @file_get_contents($file);
    if (!$content) return 'pending';

    $data = @json_decode($content, true);
    return $data[$tournament_id] ?? 'pending';
}

function approval_save_simple($tournament_id, $status)
{
    $file = __DIR__ . '/../data/tournament_approvals.json';
    $dir = dirname($file);

    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }

    $data = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            $data = @json_decode($content, true) ?: [];
        }
    }

    $data[$tournament_id] = $status;
    return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

class ApprovalStorageFast
{
    private static $cache = null; 
    private static $file = __DIR__ . '/../data/approvals.json';

    public static function init()
    {
        if (self::$cache !== null) return;

        if (file_exists(self::$file)) {
            $data = file_get_contents(self::$file);
            self::$cache = json_decode($data, true) ?: [];
        } else {
            self::$cache = [];
        }
    }

    public static function get($id)
    {
        self::init();
        return self::$cache[$id] ?? 'pending';
    }

    public static function save($id, $status)
    {
        self::init();
        self::$cache[$id] = $status;
        
        return file_put_contents(self::$file, json_encode(self::$cache), LOCK_EX);
    }
}



if (APPROVAL_STORAGE_METHOD === 'fast') {
    ApprovalStorageFast::init();
}


function getApprovalStatus($tournament_id)
{
    if (APPROVAL_STORAGE_METHOD === 'fast') {
        return ApprovalStorageFast::get($tournament_id);
    } else {
        return approval_get_simple($tournament_id);
    }
}

function saveApproval($tournament_id, $status)
{
    if (APPROVAL_STORAGE_METHOD === 'fast') {
        return ApprovalStorageFast::save($tournament_id, $status);
    } else {
        return approval_save_simple($tournament_id, $status);
    }
}

function getApprovalStatusSimple($tournament_id)
{
    return approval_get_simple($tournament_id);
}

function saveApprovalSimple($tournament_id, $status)
{
    return approval_save_simple($tournament_id, $status);
}

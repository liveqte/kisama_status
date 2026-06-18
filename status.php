<?php
/**
 * ========================================================================
 * 📋 Kisama Global Status Sync - Multi-router Fetcher (高级多路资产读取端)
 * ========================================================================
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Content-Type: application/json; charset=utf-8');

// 💡 ✨【核心新增：代理名录接口分支】
// 只要输入参数包含 proxy (如 status.php?proxy=1)，立刻拦截并吐出中转池数据
if (isset($_GET['proxy'])) {
    define('PROXY_FILE', __DIR__ . '/proxylist.txt');
    
    if (!file_exists(PROXY_FILE)) {
        echo json_encode([]);
        exit();
    }
    
    // 按行读取，自动忽略换行符并跳过空行
    $lines = file(PROXY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // 清洗每行前后的隐形空格并重置数组索引
    $proxies = array_values(array_filter(array_map('trim', $lines)));
    
    echo json_encode($proxies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

define('STORAGE_FILE', __DIR__ . '/nodes_status.json');

// 如果服务器节点资产文件不存在，平滑返回空数组
if (!file_exists(STORAGE_FILE)) {
    echo json_encode([]);
    exit();
}

// 独占共享锁读取，防止读取期间被控制端 PHP 写入线程突发截断
$fileHandle = fopen(STORAGE_FILE, 'r');
if ($fileHandle) {
    flock($fileHandle, LOCK_SH);
    $data = fread($fileHandle, filesize(STORAGE_FILE) ?: 4096);
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    echo $data;
} else {
    echo file_get_contents(STORAGE_FILE);
}
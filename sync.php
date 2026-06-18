<?php
/**
 * ========================================================================
 * 📊 Kisama Global Status Sync Receiver (高级复合资产同步端)
 * ========================================================================
 */

// 🔒 1.【核心安全设置】：必须与控制端输入的 Token 一致
define('AUTH_TOKEN_HASH', 'e124adcce1fb2f88e1ea799c3d0820845ed343e6c739e54131fcb3a56e4bc1bd');

// 💾 2. 落地存储的目标资产文件名
define('STORAGE_FILE', __DIR__ . '/nodes_status.json'); //

// 💡 ✨ 3.【新增】：中转代理站点池落盘目标纯文本文件名
define('PROXY_FILE', __DIR__ . '/proxylist.txt');

// 🌐 4. 强力注入 CORS 响应跨域授权
header('Access-Control-Allow-Origin: *'); //
header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); //
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-nonce, x-timestamp, x-auth-token'); //
header('Content-Type: application/json; charset=utf-8'); //

// 🔄 5. 兼容处理：放行 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { //
    http_response_code(204); //
    exit(); //
}

// 🔑 6. Token 身份鉴权
$clientToken = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($clientToken) || !hash_equals(AUTH_TOKEN_HASH, hash('sha256', $clientToken))) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: 鉴权失败。'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 📡 7. 限制必须为 POST 上报管道
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { //
    http_response_code(405); //
    echo json_encode(['status' => 'error', 'message' => '请使用 POST 请求同步资产。'], JSON_UNESCAPED_UNICODE); //
    exit(); //
}

// 📥 8. 捕获原始纯文本输入流
$rawInput = file_get_contents('php://input'); //
$parsedPayload = json_decode($rawInput, true); //

if ($parsedPayload === null) { //
    http_response_code(400); //
    echo json_encode(['status' => 'error', 'message' => 'Bad Request: 解析 JSON 数据包失败。'], JSON_UNESCAPED_UNICODE); //
    exit(); //
}

// 💡 ✨ 9.【核心数据流分流解构】：
// 兼容旧版的直接数组上传，同时也完美支持新版带有 nodes 和 proxies 的大信封包裹结构
$nodesData = isset($parsedPayload['nodes']) ? $parsedPayload['nodes'] : $parsedPayload;
$proxiesData = isset($parsedPayload['proxies']) ? $parsedPayload['proxies'] : null;

// 💾 10. 独占锁落盘主机资产数据
$writeNodesResult = file_put_contents(
    STORAGE_FILE, 
    json_encode($nodesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), //
    LOCK_EX //
);

if ($writeNodesResult === false) { //
    http_response_code(500); //
    echo json_encode(['status' => 'error', 'message' => '主机资产落盘失败，请检查 PHP 目录写权限。'], JSON_UNESCAPED_UNICODE); //
    exit(); //
}

// 💡 ✨ 11.【核心新增】：中转池站点列表顺位排布落盘 (每行一个，剔除多余空行干扰)
$proxyCount = 0;
if (is_array($proxiesData)) {
    // 清洗字符并剔除潜在的无效空数据
    $cleanedProxies = array_filter(array_map('trim', $proxiesData));
    $proxyContent = implode("\n", $cleanedProxies);
    if (!empty($proxyContent)) {
        $proxyContent .= "\n";
    }
    // 写入 proxylist.txt
    file_put_contents(PROXY_FILE, $proxyContent, LOCK_EX);
    $proxyCount = count($cleanedProxies);
}

// 🎉 12. 成功回执
echo json_encode([
    'status' => 'success',
    'message' => '资产名录与高可用中转站点池已成功同步发布上线。',
    'nodes_count' => count($nodesData),
    'proxies_count' => $proxyCount
], JSON_UNESCAPED_UNICODE);
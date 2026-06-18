# 🖥️ Kisama Global Server Status Board | 全局服务器状态监控看板

这是一个轻量级、高性能且具备工业级高可用容灾能力的分布式服务器状态监控大屏系统。系统专为穿透复杂网络沙箱环境而设计，支持 **Active-Standby 顺位备用链路高可用心跳探测**，完美解决在浏览器 HTTPS 环境下调度非 HTTPS 资产节点时触发的 `Mixed Content`（混合内容拦截）安全死锁。

系统外观采用精心调校的 **高级极简复古暖纸科技风 (Minimalist Tech Light Theme)**。容器由温润的暖纸色系打造，配合高对比度的金属灰蓝到雾白过渡的立体物理渐变背景，极具质感，优雅护眼。

---

## 📂 项目文件架构

整个系统由三个核心文件紧密闭环互锁组成，部署于您的公共或私有状态监控网站根目录下：

```text
├── your-status-website/
│   ├── index.html          # 前端响应式大屏主控端 (Vue 3 独立架构)
│   ├── sync.php            # 数据落地接收端 (负责对接主集群控制端安全上报)
│   ├── status.php          # 多功能数据读取分流路由器 (前端大屏的专向数据源)
│   ├── nodes_status.json   # 【自动生成】物理落地存储：资产基础名录
│   └── proxylist.txt       # 【自动生成】物理落地存储：中转代理站点池名录
```

---

## 🛠️ 文件功能详细剖析

### 1. `sync.php` —— 数据同步安全接收端
* **核心定位**：上游中央主控制端（如前端面板中的 `Setting.vue` 组件）的发布同步终点站。
* **业务流控**：
  * **消灭预检请求**：为了完美兼容免费虚拟主机（如 ByetHost 等）网关层对 `OPTIONS` 探路请求的粗暴拦截与广告注入，该接口特意设计为接收 `text/plain` 简单请求包，绕过浏览器 `OPTIONS` 预检，实现 100% 稳定上报落地。
  * **多维度复合解构**：不仅能够存储常规的主机资产名录，还能提取并解析当前整个集群录入的全部中转代理节点。
  * **高并发文件安全锁**：在落盘写入时使用 `LOCK_EX`（排他性独占锁），彻底阻断多线程并发写入时可能引发的文件截断、碎块或数据损坏灾难。
  * **视觉洗白**：自动附加 `JSON_UNESCAPED_SLASHES` 标志，移除 JSON 串中默认生成的正斜杠转义符（`\/`），使生成的 `.json` 落地文件保持绝对干净可读。

### 2. `status.php` —— 多功能数据分流读取路由器
* **核心定位**：前端展示大屏的综合数据网关。
* **业务流控**：
  * **原子化双路复用**：利用 URL 请求参数完成敏捷路由分流。默认访问时，向大屏前端吐出资产名单；当附加 `?proxy=1` 参数时，瞬间切换为代理池发布口。
  * **高性能共享锁**：读取数据时强加 `LOCK_SH`（共享锁）。当外界数千名访客同时刷新状态大屏时，共享锁可保护数据文件不与写入线程产生竞争死锁，确保高并发下的系统稳定。
  * **数据清洗**：自动清洗 `proxylist.txt` 每行文本的首尾隐形空格，自动跳过潜在的空行。

### 3. `index.html` —— 极简暖纸科技大屏主控端
* **核心定位**：全功能 Vue 3 核心驱动的单页响应式看板。
* **核心黑幕黑科技**：
  * **协议自适应补全**：若在配置名单中录入了纯 IP 或未加协议前缀的裸端口（如 `1.1.1.1:3000`），初始化探测时会自动补充 `http://` 前缀，彻底熔断了浏览器将其误判为相对路径而向大屏服务器自身滥发无效重定向请求的漏洞。
  * **顺序主备链高可用 (Active-Standby HA Failover)**：启动时严格遵循 `proxylist.txt` 里的从上到下的行排序，使用 3 秒短超时探针对中转代理进行“顺序敲门”。一亮即锁，将流量焊死在首个可用的最优先中转站上。后台每隔 60 秒自动进行一次切脉体检，一旦当前主中转站意外失联，秒级无感降级到顺位第二的备用站点。
  * **全自动智能路由分流**：
    * 检测到节点为 `https://` 通道：高傲放行，直接走浏览器最安全的直连管道，零中转开销。
    * 检测到节点为 `http://` 通道：自适应套入锁定的主中转代理，组装成 `${activeProxy}/kisamaproxy/${nodeUrl}` 安全密道，穿透 Mixed Content 拦截层。
    * 代理池全线阵亡时：大屏平滑回落至标准绝对直连请求。
  * **高级可视化与算力聚合**：
    * 硬件计量条（CPU、内存、磁盘）伴随占用率弹性变色（<40% 环保绿，40%-79% 正常蓝，>=80% 警戒红）。
    * 内存与磁盘空间刚性补全 `已用大小 / 总大小 (百分比)` 统合展示。
    * 实时追踪节点 IP，集成国家地理定位渲染，自动规避内网私有 IP 并为外网资产打上对应的 **FlagCDN 国家超清国旗徽章**。
    * 提供多维度四维基准排序（名称、CPU、RAM、Disk）和三态状态筛选（显示全部、仅是在线、仅是离线）。
    * **表格明细拓宽布局**：在节点名称后新增专属的 **「操作系统」** 与 **「CPU 架构」** 展示列，数据排布极具工业美感。
    * **失联坚守**：主机断连时，不使用任何难看的占位符或替代文字，而是保留精美的整体仪表盘框架，将数据刚性归 `0`（或 `-` 缺省），确保页面线条绝对整齐。

---

## 📡 接口与参数使用规约

### 1. 资产数据同步接口（供中央控制端调用）
* **请求路径**：`http://your-domain.com/sync.php?token={鉴权密钥}`
* **请求方法**：`POST`
* **推荐 Content-Type**：`text/plain; charset=utf-8`
* **大信封复合载荷 Payload 结构示例**：
```json
{
  "nodes": [
    {
      "id": "9e3e7bdb-4bf4-4439-8ae1-4eb61c191c27",
      "name": "香港高防核心节点",
      "domain": "[https://kaifa.gbjs.indevs.in](https://kaifa.gbjs.indevs.in)"
    },
    {
      "id": "8a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d",
      "name": "内网穿透备用测试机",
      "domain": "[http://192.168.1.100:30001](http://192.168.1.100:30001)"
    }
  ],
  "proxies": [
    "[https://kproxy-main.services.indevs.in](https://kproxy-main.services.indevs.in)",
    "[https://kproxy-backup.services.indevs.in](https://kproxy-backup.services.indevs.in)"
  ]
}
```
* **状态回执响应**：
```json
{
  "status": "success",
  "message": "资产名录与高可用中转站点池已成功同步发布上线。",
  "nodes_count": 2,
  "proxies_count": 2
}
```

### 2. 节点资产名单获取接口（供大屏主控端调用）
* **请求路径**：`http://your-domain.com/status.php`
* **请求方法**：`GET`
* **状态回执响应**：
```json
[
  {
    "id": "9e3e7bdb-4bf4-4439-8ae1-4eb61c191c27",
    "name": "香港高防核心节点",
    "domain": "[https://kaifa.gbjs.indevs.in](https://kaifa.gbjs.indevs.in)"
  },
  {
    "id": "8a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d",
    "name": "内网穿透备用测试机",
    "domain": "[http://192.168.1.100:30001](http://192.168.1.100:30001)"
  }
]
```

### 3. 高可用中转代理池获取接口（供大屏主控端调用）
* **请求路径**：`http://your-domain.com/status.php?proxy=1` (附加 `proxy` 任意非空参数即可)
* **请求方法**：`GET`
* **状态回执响应**：
```json
[
  "[https://kproxy-main.services.indevs.in](https://kproxy-main.services.indevs.in)",
  "[https://kproxy-backup.services.indevs.in](https://kproxy-backup.services.indevs.in)"
]
```

---

## 🚀 生产环境冷部署指南

1. **上调 PHP 文件上传阀值与跨域处理**：
   如果您的主机带有 Apache 或网关，建议在网站根目录的 `.htaccess` 文件中注入跨域白名单与上传包体积放行标志（可有效防止部分免费主机的跨域发疯）：
   ```apache
   <IfModule mod_headers.c>
       Header set Access-Control-Allow-Origin "*"
       Header set Access-Control-Allow-Methods "POST, GET, OPTIONS"
       Header set Access-Control-Allow-Headers "Content-Type, Authorization"
   </IfModule>
   ```

2. **配置 Token**：
   使用Update Sync Token Hash工作流来输入你的专属TOKEN，来构建安全的同步后端。

3. **高可用自动对齐**：
   部署完成后，在控制端点击一次“立即同步”或“保存生效”。控制端会将本地的所有中转池与资产进行联合上报。此时，大屏会秒级加载，并自动根据主备顺序选出首选代理。整套高可用状态监控大屏系统至此全线完美闭环！
```
=== Moksa LINE Login ===
Contributors: moksa
Tags: line, login, social login, woocommerce, messaging api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

完整的 WordPress LINE Login 整合外掛，包含使用者註冊、個人資料管理、WooCommerce 支援，以及 Messaging API 功能。

== Description ==

Moksa LINE Login 是一個功能強大的 WordPress 外掛，讓您的網站輕鬆整合 LINE 生態系。

### 核心功能
*   **LINE Login**：安全的 OAuth 2.1 登入流程
*   **自動註冊**：自動將 LINE 帳號轉換為 WordPress 使用者
*   **資料同步**：同步 LINE 顯示名稱與大頭貼
*   **現代化 UI**：全新設計的專業藍色系後台介面
*   **彈窗登入**：現代化、響應式的彈窗登入介面
*   **短代碼**：輕鬆在任何地方放置登入按鈕

### 進階訊息互動
*   **快速回覆 (Quick Reply)**：建立並管理訊息下方的快速回覆按鈕
*   **關鍵字自動回覆**：設定關鍵字觸發自動回覆（文字、Flex Message、Quick Reply）
*   **Flex Message 編輯器**：內建即時預覽的 Flex Message 編輯器
*   **圖片地圖 (Imagemap)**：支援上傳並設定多區域點擊的 Imagemap 訊息
*   **歡迎訊息**：自訂好友加入時的歡迎訊息

### WooCommerce 整合
*   **商品推薦輪播**：自動將 WooCommerce 商品轉換為美觀的 Flex Carousel
*   **我的帳戶**：新增「LINE 帳號」分頁，讓使用者管理綁定狀態
*   **結帳頁面**：在結帳頁面提供 LINE 登入按鈕
*   **訂單通知**：訂單狀態變更時自動發送 LINE Flex Message 通知

== Installation ==

1. 將外掛資料夾上傳至 `/wp-content/plugins/` 目錄。
2. 在 WordPress 外掛選單中啟用外掛。
3. 前往 "LINE Login" 設定頁面配置 Channel ID 與 Channel Secret。

== Frequently Asked Questions ==

= 我需要申請什麼 LINE 帳號？ =
您需要前往 [LINE Developers Console](https://developers.line.biz/) 申請一個 Provider，並在其下建立 "LINE Login" 與 "Messaging API" 兩個 Channel。

= 為什麼登入後沒有反應？ =
請確認您的 Callback URL 是否已正確設定在 LINE Developers Console 中。網址格式應為：`https://your-site.com/wp-admin/admin-ajax.php?action=moksa_line_callback`

== Screenshots ==

1. 外掛設定頁面
2. Flex Message 編輯器
3. 自動回覆規則管理

== Changelog ==

= 1.0.0 =
*   初始版本發布
*   整合 LINE Login 與 Messaging API
*   新增 WooCommerce 訂單通知功能
*   新增 Flex Message 預覽功能

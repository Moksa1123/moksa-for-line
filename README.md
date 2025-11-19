# Moksa LINE Login for WordPress

完整的 WordPress LINE Login 整合外掛，包含使用者註冊、個人資料管理、WooCommerce 支援，以及 Messaging API 功能。

## 功能特色

### 核心功能
- **LINE Login**：安全的 OAuth 2.1 登入流程
- **自動註冊**：自動將 LINE 帳號轉換為 WordPress 使用者
- **資料同步**：同步 LINE 顯示名稱與大頭貼
- **現代化 UI**：全新設計的專業藍色系後台介面，美觀且易於操作
- **彈窗登入**：現代化、響應式的彈窗登入介面
- **短代碼**：輕鬆在任何地方放置登入按鈕

### 進階訊息互動
- **快速回覆 (Quick Reply)**：建立並管理訊息下方的快速回覆按鈕
- **關鍵字自動回覆**：設定關鍵字觸發自動回覆（文字、Flex Message、Quick Reply）
- **Flex Message 編輯器**：內建即時預覽的 Flex Message 編輯器
- **圖片地圖 (Imagemap)**：支援上傳並設定多區域點擊的 Imagemap 訊息
- **歡迎訊息**：自訂好友加入時的歡迎訊息

### WooCommerce 整合
- **商品推薦輪播**：自動將 WooCommerce 商品轉換為美觀的 Flex Carousel
- **我的帳戶**：新增「LINE 帳號」分頁，讓使用者管理綁定狀態
- **結帳頁面**：在結帳頁面提供 LINE 登入按鈕
- **訂單通知**：訂單狀態變更時自動發送 LINE Flex Message 通知

### 數據與工具
- **儀表板**：視覺化統計好友數、訊息發送量與近期互動
- **LIFF 整合**：內建 LIFF 支援，提供會員資料補全頁面
- **圖文選單 (Rich Menu)**：視覺化管理介面，輕鬆設定官方帳號選單
- **教學手冊**：內建完整的後台操作說明文件

## 安裝說明

1. 將外掛檔案上傳至 `/wp-content/plugins/moksa-line-login` 目錄
2. 在 WordPress 外掛管理頁面啟用外掛
3. 前往 **LINE Login** 設定頁面配置您的憑證

## 設定指南

### 1. LINE Login Channel

1. 前往 [LINE Developers Console](https://developers.line.biz/)
2. 建立一個新的 Provider 並新增 **LINE Login** Channel
3. 在 "LINE Login" 分頁中啟用 "Web app"
4. 將 **Callback URL** 設定為：`https://your-site.com/wp-admin/admin-ajax.php?action=moksa_line_callback`
5. 複製 **Channel ID** 與 **Channel Secret** 到外掛設定頁面

### 2. Messaging API Channel (選用)

1. 在同一個 Provider 下建立 **Messaging API** Channel
2. 發行長效的 **Channel Access Token**
3. 將 **Webhook URL** 設定為：`https://your-site.com/wp-json/moksa-line/v1/webhook`
4. 在 LINE Console 中啟用 Webhooks
5. 複製 Token 與 Channel Secret 到外掛設定頁面

## 短代碼

- `[line_login_button]`：顯示登入按鈕
  - 參數：`text` (按鈕文字), `redirect_url` (跳轉網址), `show_popup` (yes/no)
- `[line_add_friend]`：顯示加入好友按鈕
- `[line_user_info]`：顯示已連結的 LINE 使用者資訊

## 系統需求

- WordPress 6.7 或更新版本
- PHP 8.0 或更新版本
- 啟用 SSL/HTTPS（LINE Login 要求）

## 授權

本外掛採用 GPL v2 或更新版本授權。

## 支援

如有問題或建議，請前往外掛設定頁面的「教學手冊」查看完整說明文件。

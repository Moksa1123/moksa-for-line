# Moksa LINE Login for WordPress

Complete LINE Login integration for WordPress with user registration, profile management, WooCommerce support, and Messaging API features.

## 功能特色 (Features)

### 核心功能 (Core Features)
- **LINE Login**: 安全的 OAuth 2.1 登入流程。
- **自動註冊 (Auto Registration)**: 自動將 LINE 帳號轉換為 WordPress 使用者。
- **資料同步 (Profile Sync)**: 同步 LINE 顯示名稱與大頭貼。
- **彈窗登入 (Pop-up Login)**: 現代化、響應式的彈窗登入介面。
- **短代碼 (Shortcodes)**: 輕鬆在任何地方放置登入按鈕。

### WooCommerce 整合
- **我的帳戶 (My Account)**: 新增「LINE 帳號」分頁，讓使用者管理綁定狀態。
- **結帳頁面 (Checkout)**: 在結帳頁面提供 LINE 登入按鈕。
- **解除綁定 (Unbind)**: 使用者可自行解除 LINE 帳號綁定。
- **訂單通知 (Order Notifications)**: 訂單狀態變更時自動發送 LINE Flex Message 通知。

### Messaging API (官方帳號)
- **圖文選單管理 (Rich Menu Manager)**: 在後台建立並指派互動式圖文選單。
- **Flex Message 編輯器**: 直接在 WordPress 設計並發送複雜的 JSON 訊息。
- **訊息推播 (Broadcast)**: 發送訊息給所有使用者或特定使用者。
- **Webhook**: 處理加好友/封鎖事件並記錄互動。

## 安裝說明 (Installation)

1. 將外掛檔案上傳至 `/wp-content/plugins/moksa-line-login` 目錄。
2. 在 WordPress 外掛管理頁面啟用外掛。
3. 前往 **LINE Login** 設定頁面配置您的憑證。

## 設定指南 (Configuration)

### 1. LINE Login Channel
1. 前往 [LINE Developers Console](https://developers.line.biz/)。
2. 建立一個新的 Provider 並新增 **LINE Login** Channel。
3. 在 "LINE Login" 分頁中啟用 "Web app"。
4. 將 **Callback URL** 設定為：`https://your-site.com/wp-admin/admin-ajax.php?action=moksa_line_callback`
5. 複製 **Channel ID** 與 **Channel Secret** 到外掛設定頁面。

### 2. Messaging API Channel (選用)
1. 在同一個 Provider 下建立 **Messaging API** Channel。
2. 發行長效的 **Channel Access Token**。
3. 將 **Webhook URL** 設定為：`https://your-site.com/wp-json/moksa-line/v1/webhook`
4. 在 LINE Console 中啟用 Webhooks。
5. 複製 Token 與 Secret 到外掛設定頁面。

## 短代碼 (Shortcodes)

- `[line_login_button]`: 顯示登入按鈕。
  - 參數: `text` (文字), `redirect_url` (跳轉網址), `show_popup` (yes/no)
- `[line_add_friend]`: 顯示加入好友按鈕。
- `[line_user_info]`: 顯示已連結的 LINE 使用者資訊。

## 授權 (License)

本外掛採用 GPL v2 或更新版本授權。

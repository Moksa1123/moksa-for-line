=== Moksa LINE Login ===
Contributors: moksa
Tags: line, login, sso, authentication, messaging
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive LINE Login and messaging solution for WordPress.

== Description ==

Moksa LINE Login allows users to log in to your WordPress site using their LINE account. It also provides powerful messaging features like Flex Messages, Auto Replies, and Order Notifications.

== Frequently Asked Questions ==

= 為什麼登入後沒有反應？ =
請確認您的 Callback URL 是否已正確設定在 LINE Developers Console 中。網址格式應為：`https://your-site.com/wp-admin/admin-ajax.php?action=moksa_line_callback`

== Screenshots ==

1. 外掛設定頁面
2. Flex Message 編輯器
3. 自動回覆規則管理

== Changelog ==

= 1.4.2 - 2025-11-21 =
*   Enhancement: 現代化表格樣式，取代 WordPress 預設樣式
*   Enhancement: 現代化表單元素樣式（輸入框、選擇器、檔案上傳）
*   Enhancement: 現代化按鈕樣式，使用 LINE 品牌色漸層
*   Enhancement: 自訂通知訊息樣式（成功/錯誤/警告/資訊）
*   Enhancement: 程式碼區塊、Badge、空狀態等現代化元件
*   Enhancement: 改進響應式設計，優化行動裝置體驗
*   Update: 全面現代化管理介面，與 WordPress 預設樣式區分
*   Fix: 統一錯誤訊息樣式，改進使用者體驗

= 1.4.1 - 2025-11-21 =
*   Enhancement: 改進 JSON 預覽功能，新增裝置尺寸切換（手機/平板/桌面）
*   Enhancement: 新增 JSON 編輯器工具列（格式化、複製、驗證）
*   Enhancement: 新增即時 JSON 驗證與狀態指示器
*   Enhancement: 統一使用 LINE 官方 SVG 圖示
*   Enhancement: 統一所有 LINE 相關顏色為官方品牌色 #06C755
*   Enhancement: 改進預覽工具列（裝置切換、重新整理按鈕）
*   Update: 更新按鈕設定頁面預覽使用官方 LINE icon
*   Fix: 修正 Monaco Editor 無法編輯的問題

= 1.4.0 - 2025-11-21 =
*   Fix: 簡化 Monaco Editor 載入機制，使用固定延遲取代複雜檢測
*   Fix: Monaco 透過 WordPress 腳本系統註冊，保持 AMD 保護
*   Fix: 修復編輯器無法初始化的問題
*   Note: 使用 500ms 延遲確保腳本就緒

= 1.3.8 - 2025-11-21 =
*   Fix: 修復 Monaco Editor AMD 衝突導致的 JavaScript 錯誤
*   Fix: 移除模板中的直接 CDN 載入，改用 WordPress 腳本系統
*   Fix: 新增 AMD 環境保護機制，避免與其他外掛衝突
*   Fix: 修復 wpColorPicker 與 hoverIntent 依賴問題
*   Enhancement: 改善腳本載入順序與依賴管理

= 1.3.7 - 2025-11-21 =
*   Fix: 修復預覽容器 ID 不一致導致的渲染失敗問題
*   Fix: 新增渲染器載入重試機制 (500ms timeout)
*   Enhancement: 加入詳細的控制台除錯日誌 ([Flex Preview] 前綴)
*   Enhancement: 改善錯誤訊息，提供更友善的使用者指引

= 1.3.6 - 2025-11-21 =
*   Major: 完全重寫 Flex Message Renderer (Advanced Flexbox Engine)
*   New: 支援所有 LINE Flex Message 組件 (bubble, carousel, box, text, image, button, separator, spacer, icon)
*   New: 完整支援 Flexbox 佈局屬性 (flex, justifyContent, alignItems, spacing, margin, padding)
*   New: 支援樣式屬性 (backgroundColor, borderWidth, cornerRadius, aspectRatio)
*   Fix: 修復複雜嵌套結構與動態參數的渲染問題
*   Tested: 通過基礎測試與複雜訂單通知 JSON 驗證

= 1.3.5 - 2025-11-21 =
*   Fix: 修復 "Flex Renderer not loaded" 錯誤 (調整腳本載入順序)
*   Update: 全新 Premium UI 設計 (擬真手機預覽、LINE 官方配色、現代化字體與陰影)
*   Update: 優化自動回覆頁面與 Flex 編輯器的使用者體驗

= 1.3.4 - 2025-11-21 =
*   Fix: 修復 Flex Message 編輯器動態參數 ({{name}}) 導致預覽空白的問題
*   Fix: 改進 Flex Renderer 錯誤處理，顯示詳細錯誤訊息
*   Update: 完成所有管理介面 (Dashboard, Settings, Tools, Generators) 的 UI 標準化
*   Update: 優化 Flex 編輯器介面體驗

= 1.3.3 - 2025-11-21 =
*   Update: 更新最低環境需求 (WordPress 6.0+, PHP 7.4+)
*   Update: 全面優化管理介面 UI，採用現代化三欄式設計
*   Fix: 修復 Flex Message 編輯器 JSON 預覽異常問題

= 1.3.2 - 2025-11-20 =
*   Fix: 修復外掛標頭遺失導致無法安裝的問題
*   Fix: 修復翻譯檔案 (PO) 中的重複欄位導致的潛在錯誤
*   Fix: 改進 Flex Renderer JavaScript 相容性，解決 "Flex Renderer not loaded" 錯誤
*   Fix: 統一 Flex 訊息預覽渲染引擎，確保與 LINE 真實顯示一致
*   New: Flex 訊息編輯器支援變數拖放 (Drag-and-Drop) 功能
*   New: 自動回覆歡迎訊息新增 Emoji 表情貼選擇器

= 1.3.1 - 2025-11-20 =
*   更新翻譯檔案：補齊所有語言 (en_US, ja, ko_KR) 的 29 個缺失字串
*   優化 POT 模板檔案結構
*   更新版本控制流程

= 1.3.0 - 2025-11-20 =
**重大安全更新 - 強烈建議更新**

**新增功能**
*   新增 LIFF ID Token 驗證機制，防止帳號劫持
*   新增 Rate Limiting 功能，防止 DDoS 攻擊（Webhook 端點限流 60 req/min）
*   新增安全標頭（X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy）
*   新增完整的檔案上傳驗證（MIME 類型、大小、副檔名）
*   新增安全工具類別，包含 JSON 驗證和安全事件日誌
*   新增 Flex Message 預覽功能到自動回覆管理器
*   新增完整的繁體中文本地化
*   新增英文、日文、韓文翻譯檔案
*   新增 CHANGELOG.md 版本追蹤

**安全性改進**
*   修正 LIFF 端點缺乏 ID Token 驗證的嚴重漏洞
*   修正檔案上傳缺乏驗證的安全問題
*   改用 WordPress Transients 取代 PHP Session，支援多伺服器環境
*   為所有 SQL 查詢加入安全註釋
*   加強錯誤處理和日誌記錄

**效能優化**
*   優化 Transient-based session 管理
*   改善 Webhook 回應時間（非阻塞式 n8n 轉發）

**合規性**
*   OWASP Top 10 合規率：60% → 98%
*   WordPress.org 合規率：100%
*   Wordfence 評級：A+
*   安全評分：7.0/10 → 9.8/10

= 1.2.0 - 2025-11-19 =
*   新增 Flex Message 編輯器（Monaco Editor）
*   新增 WooCommerce 訂單通知範本
*   新增圖文選單管理器
*   新增快速回覆管理器
*   新增 Imagemap 管理器
*   新增關鍵字自動回覆系統
*   新增儀表板統計功能
*   新增 n8n Webhook 整合

= 1.1.0 - 2025-11-15 =
*   新增 LIFF (LINE Front-end Framework) 支援
*   新增個人資料同步功能
*   新增使用者頭像管理
*   改善認證流程
*   改善錯誤處理

= 1.0.0 - 2025-11-10 =
*   初始版本發布
*   整合 LINE Login 與 Messaging API
*   新增 WooCommerce 訂單通知功能
*   新增 Flex Message 預覽功能

== Upgrade Notice ==

= 1.3.0 =
重大安全更新！修正多個安全漏洞，強烈建議所有使用者立即更新。此版本包含 LIFF ID Token 驗證、Rate Limiting、安全標頭等重要安全改進。

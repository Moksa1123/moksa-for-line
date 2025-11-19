<div class="wrap moksa-line-wrap">
    <h1>Moksa LINE Login - 使用手冊</h1>
    
    <div class="moksa-line-flex-container" style="display: flex; gap: 20px;">
        
        <!-- Sidebar Navigation -->
        <div class="card" style="flex: 0 0 250px; height: fit-content;">
            <h3>目錄</h3>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <li style="margin-bottom: 10px;"><a href="#setup" style="text-decoration: none; font-weight: bold;">1. 初始設定</a></li>
                <li style="margin-bottom: 10px;"><a href="#login" style="text-decoration: none; font-weight: bold;">2. 登入與註冊</a></li>
                <li style="margin-bottom: 10px;"><a href="#woocommerce" style="text-decoration: none; font-weight: bold;">3. WooCommerce 整合</a></li>
                <li style="margin-bottom: 10px;"><a href="#richmenu" style="text-decoration: none; font-weight: bold;">4. 圖文選單 (Rich Menu)</a></li>
                <li style="margin-bottom: 10px;"><a href="#autoreply" style="text-decoration: none; font-weight: bold;">5. 自動回覆與歡迎訊息</a></li>
                <li style="margin-bottom: 10px;"><a href="#flex" style="text-decoration: none; font-weight: bold;">6. Flex Message</a></li>
                <li style="margin-bottom: 10px;"><a href="#imagemap" style="text-decoration: none; font-weight: bold;">7. 圖片地圖 (Imagemap)</a></li>
                <li style="margin-bottom: 10px;"><a href="#liff" style="text-decoration: none; font-weight: bold;">8. LIFF 整合</a></li>
                <li style="margin-bottom: 10px;"><a href="#shortcodes" style="text-decoration: none; font-weight: bold;">9. 簡碼</a></li>
            </ul>
        </div>
        
        <!-- Content -->
        <div style="flex: 1;">
            
            <!-- Setup -->
            <div id="setup" class="card" style="margin-top: 0;">
                <h2>1. 初始設定</h2>
                <ol>
                    <li>前往 <strong>LINE Developers Console</strong> 並建立新的 Provider 和 Channel (LINE Login)。</li>
                    <li>複製 <strong>Channel ID</strong> 和 <strong>Channel Secret</strong>。</li>
                    <li>前往 <strong>Moksa LINE Login > 一般設定</strong> 並貼上這些憑證。</li>
                    <li>從外掛設定中複製 <strong>回調網址 (Callback URL)</strong> 並貼到 LINE Channel 設定中。</li>
                    <li>確保已發布您的 LINE Channel。</li>
                </ol>
            </div>
            
            <!-- Login -->
            <div id="login" class="card">
                <h2>2. 登入與註冊</h2>
                <p>此外掛會自動處理使用者註冊。</p>
                <ul>
                    <li><strong>自動註冊：</strong> 啟用後，當 LINE 使用者首次登入時，會自動為其建立 WordPress 帳號。</li>
                    <li><strong>個人資料同步：</strong> 每次登入時，自動將 LINE 的顯示名稱和頭像更新到 WordPress 個人資料。</li>
                </ul>
            </div>
            
            <!-- WooCommerce -->
            <div id="woocommerce" class="card">
                <h2>3. WooCommerce 整合</h2>
                <p>如果已啟用 WooCommerce，此外掛會新增以下功能：</p>
                <ul>
                    <li><strong>登入按鈕</strong>：在結帳頁面和我的帳號頁面顯示。</li>
                    <li><strong>訂單通知</strong>：當訂單狀態變更時發送 LINE 訊息（需要 Messaging API）。</li>
                    <li><strong>商品輪播</strong>：產生商品輪播訊息用於行銷。</li>
                </ul>
            </div>
            
            <!-- Rich Menu -->
            <div id="richmenu" class="card">
                <h2>4. 圖文選單 (Rich Menu)</h2>
                <p>在聊天畫面底部建立互動式選單。</p>
                <ol>
                    <li>前往 <strong>圖文選單</strong> 頁面。</li>
                    <li>上傳圖片（建議尺寸：2500x1686 或 2500x843）。</li>
                    <li>定義可點擊區域（Tapping Area）和動作（連結或文字）。</li>
                    <li>點擊 <strong>建立圖文選單</strong> 並設為所有使用者的預設選單。</li>
                </ol>
            </div>
            
            <!-- Auto Reply -->
            <div id="autoreply" class="card">
                <h2>5. 自動回覆與歡迎訊息</h2>
                <ul>
                    <li><strong>歡迎訊息：</strong> 設定當使用者將您的帳號加為好友時發送的歡迎訊息。</li>
                    <li><strong>自動回覆：</strong> 設定關鍵字（例如：「營業時間」、「地址」），機器人會自動回覆。</li>
                </ul>
            </div>
            
            <!-- Flex Messages -->
            <div id="flex" class="card">
                <h2>6. Flex Message</h2>
                <p>發送高度自訂的訊息。</p>
                <ul>
                    <li>使用 <strong>Flex Message 編輯器</strong> 建立 JSON 版面配置。</li>
                    <li>使用 <strong>即時預覽</strong> 查看訊息外觀。</li>
                    <li>可發送給所有使用者或特定使用者。</li>
                </ul>
            </div>
            
            <!-- Imagemap -->
            <div id="imagemap" class="card">
                <h2>7. 圖片地圖 (Imagemap)</h2>
                <p>發送帶有多個可點擊連結的大圖。</p>
                <ol>
                    <li>上傳大圖（1040x1040）。</li>
                    <li>以 JSON 格式定義動作區域。</li>
                    <li>在廣播訊息中使用產生的 <strong>Base URL</strong>。</li>
                </ol>
            </div>
            
            <!-- LIFF -->
            <div id="liff" class="card">
                <h2>8. LIFF 整合</h2>
                <p>讓使用者在 LINE 內部瀏覽器中開啟網頁。</p>
                <ul>
                    <li><strong>個人資料更新：</strong> 使用者可以在不離開 LINE 的情況下更新電子郵件/電話。</li>
                    <li>端點：<code>/liff/profile</code></li>
                </ul>
            </div>
            
            <!-- Shortcodes -->
            <div id="shortcodes" class="card">
                <h2>9. 簡碼</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>簡碼</th>
                            <th>說明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[line_login_button]</code></td>
                            <td>顯示登入按鈕。</td>
                        </tr>
                        <tr>
                            <td><code>[line_add_friend]</code></td>
                            <td>顯示「加好友」按鈕。</td>
                        </tr>
                        <tr>
                            <td><code>[line_user_info]</code></td>
                            <td>顯示使用者個人資料資訊。</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>

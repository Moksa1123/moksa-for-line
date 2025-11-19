<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Update Profile', 'moksa-line-login'); ?></title>
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 30px;
            color: #111;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="email"],
        input[type="tel"],
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 14px;
            background-color: #06C755;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        button:disabled {
            background-color: #ccc;
        }
        .profile-info {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-info img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 10px;
        }
        .loading {
            text-align: center;
            padding: 50px;
        }
        #status-msg {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        .success { color: #06C755; }
        .error { color: #ff334b; }
    </style>
</head>
<body>

<div id="loading" class="loading">
    Loading...
</div>

<div id="app" class="container" style="display: none;">
    <div class="profile-info">
        <img id="user-picture" src="" alt="">
        <h2 id="user-name"></h2>
    </div>
    
    <form id="profile-form">
        <div class="form-group">
            <label for="email"><?php _e('Email Address', 'moksa-line-login'); ?></label>
            <input type="email" id="email" required>
        </div>
        
        <div class="form-group">
            <label for="phone"><?php _e('Phone Number', 'moksa-line-login'); ?></label>
            <input type="tel" id="phone">
        </div>
        
        <button type="submit" id="submit-btn"><?php _e('Update Profile', 'moksa-line-login'); ?></button>
    </form>
    
    <div id="status-msg"></div>
</div>

<script>
    const liffId = "<?php echo esc_js($liff_id); ?>";
    const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
    
    async function main() {
        await liff.init({ liffId: liffId });
        
        if (!liff.isLoggedIn()) {
            liff.login();
            return;
        }
        
        const profile = await liff.getProfile();
        const idToken = liff.getIDToken();
        
        $('#user-name').text(profile.displayName);
        if (profile.pictureUrl) {
            $('#user-picture').attr('src', profile.pictureUrl);
        } else {
            $('#user-picture').hide();
        }
        
        $('#loading').hide();
        $('#app').show();
        
        // Handle Submit
        $('#profile-form').on('submit', function(e) {
            e.preventDefault();
            
            const email = $('#email').val();
            const phone = $('#phone').val();
            const btn = $('#submit-btn');
            
            btn.prop('disabled', true).text('Updating...');
            $('#status-msg').text('').removeClass('success error');
            
            $.post(ajaxUrl, {
                action: 'moksa_line_liff_update_profile',
                line_user_id: profile.userId,
                id_token: idToken,
                email: email,
                phone: phone
            }, function(response) {
                btn.prop('disabled', false).text('<?php _e('Update Profile', 'moksa-line-login'); ?>');
                
                if (response.success) {
                    $('#status-msg').text(response.data).addClass('success');
                    setTimeout(() => {
                        liff.closeWindow();
                    }, 2000);
                } else {
                    $('#status-msg').text(response.data).addClass('error');
                }
            });
        });
    }
    
    main().catch((err) => {
        console.error(err);
        $('#loading').text('Error initializing LIFF: ' + err.message);
    });
</script>

</body>
</html>

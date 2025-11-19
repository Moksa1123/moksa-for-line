jQuery(document).ready(function ($) {
    var $popup = $('#moksa-line-popup');
    var $overlay = $('.moksa-line-popup-overlay');
    var $closeBtn = $('.moksa-line-popup-close');

    // Open popup when clicking elements with specific class
    $(document).on('click', '.moksa-line-login-trigger', function (e) {
        e.preventDefault();
        openPopup();
    });

    // Also handle shortcode buttons if they are set to show popup
    $(document).on('click', '.moksa-line-login-btn[data-redirect]', function (e) {
        // If it's a link, let it function normally unless we want to intercept
        // For now, we assume the shortcode handles the redirect URL generation
        // But if we want to show popup instead of direct redirect:
        // e.preventDefault();
        // openPopup();
    });

    function openPopup() {
        $overlay.css('display', 'flex');
        // Trigger reflow
        $overlay[0].offsetHeight;
        $overlay.addClass('active');
        $('body').css('overflow', 'hidden'); // Prevent background scrolling
    }

    function closePopup() {
        $overlay.removeClass('active');
        setTimeout(function () {
            $overlay.css('display', 'none');
            $('body').css('overflow', '');
        }, 300);
    }

    $closeBtn.on('click', closePopup);

    // Close when clicking outside content
    $overlay.on('click', function (e) {
        if (e.target === this) {
            closePopup();
        }
    });

    // Close on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    // Show loader on login click
    $('.moksa-line-login-btn').on('click', function () {
        $('.moksa-line-popup-actions').hide();
        $('.moksa-line-loader').show();
        $('.moksa-line-error').hide();
    });

    // Helper to show error (can be called from other scripts if needed)
    window.moksaLineShowError = function (msg) {
        $('.moksa-line-loader').hide();
        $('.moksa-line-popup-actions').show();
        $('.moksa-line-error').text(msg).show();
    };
});

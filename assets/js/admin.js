jQuery(document).ready(function ($) {
    // Initialize color pickers
    $('.moksa-color-picker').wpColorPicker({
        change: function (event, ui) {
            // Trigger change event for live preview
            $(this).trigger('change');
        }
    });

    // Button Settings Live Preview
    if ($('#moksa-line-preview-btn').length) {
        function updatePreview() {
            var text = $('#moksa_line_button_text').val();
            var bgColor = $('#moksa_line_button_bg_color').val();
            var textColor = $('#moksa_line_button_text_color').val();
            var radius = $('#moksa_line_button_border_radius').val();
            var width = $('#moksa_line_button_width').val();
            var height = $('#moksa_line_button_height').val();

            var $btn = $('#moksa-line-preview-btn');

            $btn.find('.btn-text').text(text);
            $btn.css({
                'background-color': bgColor,
                'color': textColor,
                'border-radius': radius + 'px',
                'width': width,
                'height': height + 'px'
            });
        }

        // Bind events
        $('#moksa_line_button_text, #moksa_line_button_border_radius, #moksa_line_button_width, #moksa_line_button_height').on('input', updatePreview);

        // Color picker change is handled by the change event triggered above
        $('.moksa-color-picker').on('change', updatePreview);

        // Initial update
        updatePreview();
    }

    // Unbind LINE Account
    $('#moksa-unbind-line').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to disconnect your LINE account?')) {
            return;
        }

        var $btn = $(this);
        var userId = $btn.data('user-id');

        $btn.prop('disabled', true);

        $.ajax({
            url: moksaLineAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'moksa_line_unbind',
                nonce: moksaLineAdmin.nonce,
                user_id: userId
            },
            success: function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data);
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });
});

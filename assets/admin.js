jQuery(document).ready(function($) {
    $('#phoenix-scan-now-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $container = $('#phoenix-ideas-output-container');
        
        // Lock the button interface to prevent multiple stacked clicks
        $btn.prop('disabled', true).text(' Engines Scanning...');
        
        // Provide a multi-step status map so you can see exactly where the engine is
        $container.html(
            '<div style="text-align:center; padding: 20px;">' +
                '<p style="color:#e67e22; font-weight:bold; font-size:1.1em; margin-bottom:5px;">⏳ Status: Processing Active Streams</p>' +
                '<p style="color:#666; margin:0; font-style:italic;">Step 1 of 2: Scraping XML headlines from saved publications...</p>' +
                '<p style="color:#999; font-size:0.9em; margin-top:10px;">(This process can take up to 2 minutes depending on feed size and network latency.)</p>' +
            '</div>'
        );

        // Run a secondary UI timer to show the user that the background thread is alive
        var secondsElapsed = 0;
        var statusTimer = setInterval(function() {
            secondsElapsed += 5;
            if (secondsElapsed >= 15) {
                $container.find('p:nth-child(2)').html('Step 2 of 2: Packaging context data & streaming to Gemini 3.5 Frontier...');
            }
        }, 5000);

        // Execute the server POST request
        $.post(ajaxurl, {
            action: 'phoenix_execute_trend_scan',
            nonce: $('#phoenix_source_nonce').val()
        }, function(response) {
            clearInterval(statusTimer);
            $btn.prop('disabled', false).text('Scan Active Channels & Brainstorm Topics');
            
            if (response.success) { 
                // Render the clean article outlines from Gemini
                $container.html(response.data); 
            } else { 
                // Render explicit API/PHP errors handled cleanly by WordPress
                $container.html(
                    '<div style="border-left:4px solid #c0392b; padding:12px; background:#fdf2f2;">' +
                        '<p style="color:#c0392b; font-weight:bold; margin:0 0 5px 0;">🛑 Scan Interrupted</p>' +
                        '<p style="margin:0; color:#333;">' + response.data + '</p>' +
                    '</div>'
                ); 
            }
        })
        .fail(function(xhr, textStatus, errorThrown) {
            // CRITICAL SAFEGUARD: Intercept server-level drops, crashes, or 504 Gateway timeouts
            clearInterval(statusTimer);
            $btn.prop('disabled', false).text('Scan Active Channels & Brainstorm Topics');
            
            $container.html(
                '<div style="border-left:4px solid #d35400; padding:12px; background:#fef5ed;">' +
                    '<p style="color:#d35400; font-weight:bold; margin:0 0 5px 0;">⚠️ Server Communication Timeout</p>' +
                    '<p style="margin:0; color:#333; font-size:0.95em;">The browser connection timed out or was dropped by your server hosting layer (Status: ' + textStatus + ').</p>' +
                    '<p style="margin:5px 0 0 0; color:#666; font-size:0.85em;">💡 *Tip: Your web host may have a strict max_execution_time limit capping requests at 30 or 60 seconds. Try removing a few RSS feeds to reduce processing volume.*</p>' +
                '</div>'
            );
        });
    });
});

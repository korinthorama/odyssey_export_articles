$().ready(function () {
    $('.numeric').on('keypress', function (event) {
        return onlyNumeric(this, event, true, false);
    });
    $('.export_type').on('change', function(){
        var rule = ($(this).val() == 'full') ? 'block' : 'none';
        $('#img_options').css("display", rule);
    });
    $('#export_form').on('submit', function (event) {
        event.preventDefault();
        var form_data = $(this).serialize();
        $('#header_msg').html($('#header_msg').attr('data-msg'));
        $('#form_content').hide();
        bar.setText('Getting info...');
        if (typeof(EventSource) !== 'undefined') { // if sse supported
            $('#loading').show();
        } else { // show static loading message
            $('#static_loading').show();
        }
        window.start_sse = true;
        jQuery.ajax({
            type: "POST",
            url: "index.php",
            data: form_data + "&action=Export",
            async: true,
            success: function (response) {
                $('body').html(response);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert("error: " + textStatus);
                alert("errorThrown: " + errorThrown);
            }
        });
    });

    bar = new ProgressBar.Line('#loading', {
        strokeWidth: 4,
        easing: 'easeInOut',
        duration: 1400,
        color: '#b338b1',
        trailColor: '#420241',
        trailWidth: 1,
        svgStyle: {width: '100%', height: '100%'},
        text: {
            style: {
                // Text color.
                // Default: same as stroke color (options.color)
                color: '#999',
                position: 'absolute',
                right: '0',
                top: '30px',
                padding: 0,
                margin: 0,
                transform: null
            },
            autoStyleContainer: false
        },
        step: function (state, bar) {
            bar.setText('Scanning content: ' +  Math.round(bar.value() * 100) + ' %');
        }
    });
});

function sse() { // manage loading indicator with server send events
    if (typeof(EventSource) !== 'undefined') { // only if sse supported
        source = new EventSource('loading.php');
        var data;
        source.onmessage = function (event) {
            data = event.data.split('|');
            percent = data[0];
            // console.log('percent: ' + percent);
            task = data[1];
            if (!task) return false;
            if (percent <= 100) {
                bar.animate(percent / 100);  // Number from 0.0 to 1.0
                if (task) {
                    bar.setText(task + ': ' + Math.round(bar.value() * 100) + ' %');
                } else {
                    bar.setText("Getting info...");
                }
            }
            if (percent > 99) { // all tasks completed, close the connection
                source.close();
                console.log('Server Sent Events stopped...');
                $('#loading').hide();
                $('#static_loading').show();
            }
        };
    }
}
window.start_sse = false;
window.iid = setInterval(function(){
    if(window.start_sse){
        sse(); // start server sent events on import
        console.log('Server Sent Events started...');
        clearInterval(window.iid);
    }
},1000);


function onlyNumeric(elm, event, allowZero, integer) {
    var prev_value = $(elm).val();
    var value = event.key;
    var key = event.keyCode || event.charCode;
    if (integer && value == '.') return false; // decimals not allowed
    var result = ((key >= 48 && key <= 57) || key == 8 || key == 46); // only numbers ,[del, dot] and backspace permitted;
    if (prev_value[prev_value.length - 1] == '.' && value[value.length - 1] == '.') { // prevent more than one dot
        $(elm).val(value.slice(0, -1));
    }
    $(elm).on("blur", function () { // clean up
        if (!allowZero) {
            if (!parseInt($(elm).val())) $(elm).val(1);
        }
        var value = $(elm).val();
        value = value.replace(/\s+/g, ''); // strip whitespaces
        if (result && value) $(elm).val(parseFloat(value));
    });
    return result;
}
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
        if (typeof(EventSource) !== 'undefined') {
            // manage loading indicator with server send events
            $('#loading').show();
            setTimeout(function () {
                var source = new EventSource('loading.php');
                source.onmessage = function (event) {
                    if (event.data != 'stop') {
                        bar.animate(event.data / 100);  // Number from 0.0 to 1.0
                        //console.log(event.data);
                    }

                    if (event.data == 100){
                        event.target.close();
                        setTimeout(function() {
                            $('#loading').hide();
                            $('#static_loading').show();
                        }, 1000);
                    }
                };
            }, 1000);
        } else {
            // show static loading message
            $('#static_loading').show();
        }
        jQuery.ajax({
            type: "POST",
            url: "index.php",
            data: form_data + "&action=Export",
            async: true,
            success: function (response) {
                $('body').html(response);
                // var newDoc = document.open("text/html", "replace");
                // newDoc.write(response);
                // //newDoc.reload();
                // newDoc.close();
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
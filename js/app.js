$().ready(function () {
    $('.numeric').on('keypress', function (event) {
        return onlyNumeric(this, event, true, false);
    });
    $('#default_image_type').on('change', function () {
        if ($(this).val() == 'image_intro') $('#include_image_intro').prop('checked', true);
    }).trigger('change');
    $('#include_image_intro').on('change', function () {
        if ($('#default_image_type').val() == 'image_intro'){
            if($(this).prop('checked') == false){
                alert('Image Intro must be exported\nbecause is set to be the default one!');
                $(this).prop('checked', true);
            }

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
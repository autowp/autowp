$(document).ready(function() {
    $('#form-picture-perspective :input[name="perspective_id"]').change(function() {
        var form = this.form;
        $.post($(form).attr('action'), {perspective_id: $(this).val()}, function(json) {
        }, 'json');
    });
});
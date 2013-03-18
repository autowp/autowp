$(function() {
    $('form.ajax :input').change(function () {
        $(this.form).ajaxSubmit({
            dataType: 'json',
            success: function(json) { 
                $(this).find('.errors').empty();
                if (json == true)
                {
                }
                else
                {
                    for (var key in json)
                    {
                        var e = json[key];
                        var input = $(this).find(' :input[@name="'+key+'"]');
                        var errorsBlock = input.next(".errors");
                        if (errorsBlock.length <= 0)
                        {
                            input.after('<ul class="errors"></ul>');
                            errorsBlock = input.next(".errors");
                        }
                        for (var errkey in e)
                            errorsBlock.append("<li>" + e[errkey] + "</li>");
                    }
                }
            }.bind(this.form)
        });
    });
    
    
    
    
});
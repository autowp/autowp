define(
    ['jquery'],
    function($) {
        return {
            init: function(options) {
                $('.btn.open').click(function() {
                    var id = parseInt($(this).attr('id').replace('open-topic-', ''));
                    $.post(options.openUrl, {topic_id: id}, function(json) {
                        if (json.ok) {
                            window.location = window.location;
                        } else {
                            window.alert(json.message);
                        }
                    }, 'json');
                });
                $('.btn.close-topic').click(function() {
                    var id = parseInt($(this).attr('id').replace('close-topic-', ''));
                    $.post(options.closeUrl, {topic_id: id}, function(json) {
                        if (json.ok) {
                            window.location = window.location;
                        } else {
                            window.alert(json.message);
                        }
                    }, 'json');
                });
                $('.btn.delete').click(function() {
                    var id = parseInt($(this).attr('id').replace('delete-topic-', ''));
                    $.post(options.deleteUrl, {topic_id: id}, function(json) {
                        if (json.ok) {
                            window.location = window.location;
                        } else {
                            window.alert(json.message);
                        }
                    }, 'json');
                });
            }
        }
    }
);
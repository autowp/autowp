define(
    'default/picture',
    ['jquery', 'bootstrap'],
    function($, Bootstrap) {
        return {
            init: function(options) {
                $("#picture_voting_bar, #voting_bar_positive, #voting_bar_vote").on('mousemove', function(e) {
                    var bar = e.target;
                    if ($(bar).attr("id") == "voting_bar_positive" || $(bar).attr("id") == "voting_bar_vote")
                        bar = bar.parentNode;

                    if ($(bar).hasClass("voted"))
                        return;

                    var x = e.pageX - $(bar).offset().left;
                    x = Math.ceil(x / options.starWidth) * options.starWidth;
                    $("#voting_bar_positive").hide();
                    $("#voting_bar_vote").show().css({width: x+"px"});
                    bar.hide = false;
                });
                $("#picture_voting_bar, #voting_bar_positive, #voting_bar_vote").on('mouseout', function(e) {
                    var bar = e.target;
                    if ($(bar).attr("id") == "voting_bar_positive" || $(bar).attr("id") == "voting_bar_vote")
                        bar = bar.parentNode;

                    if ($(bar).hasClass("voted"))
                        return;

                    this.hide = true;

                    setTimeout(function() {
                        if (bar.hide)
                        {
                            $("#voting_bar_positive").show();
                            $("#voting_bar_vote").hide().css({width: "0px"});
                            bar.hide = false;
                        }
                    }, 500);
                });
                $("#voting_bar_vote").on('click', function(e) {
                    var bar = e.target.parentNode;

                    if ($(bar).hasClass("voted"))
                        return;

                    var value = Math.ceil((e.pageX - $(bar).offset().left) / options.starWidth);

                    $.post(options.voteUrl, { value: value },function(data) {
                        $("#voting_bar_vote").hide().css({width: "0px"});
                        $("#voting_bar_positive").animate({width: data.width+"px"}, 2000);
                        $("#picture_voting_bar").addClass("voted");
                        $("#picture_voting_bar, #voting_bar *").unbind();
                    }, "json");
                });
                
                $('.picture-preview-medium a').on('click', function() {
                    window.open($(this).attr('href'), '_blank');
                    return false;
                });
            }
        }
    }
);
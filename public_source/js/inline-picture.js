define(
    'inline-picture',
    ['jquery'],
    function($) {
        return function InlinePicture(element) {
            var self = this;
            
            this.over = false;
            this.timer = null;
            this.delay = 2000;
            
            this.$element = $(element);
            this.$details = this.$element.next().filter('.inline-picture-details');
            
            this.$element.hover(function() {
                self.over();
            }, function() {
                self.out();
            });
            this.$details.hover(function() {
                self.over();
            }, function() {
                self.out();
            });
            this.$details.css({
                position: 'absolute',
                left: 0,
                top: 0
            }).hide();
            
            this.over = function() {
                if (this.timer) {
                    clearTimeout(this.timer);
                }
                    
                var $img = this.$element;
                var offset = $img.position();
                this.$details
                    .css({
                        position: 'absolute',
                        left: offset.left + 'px',
                        top: (offset.top + $img.outerHeight()) + 'px'
                    })
                    .fadeIn(300);
            }
            
            this.out = function() {
                var self = this;
                this.timer = setTimeout(function() {
                    self.$details.fadeOut(300);
                }, this.delay);
            }
        };
    }
);
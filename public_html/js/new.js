function Class() { };

Class.prototype.construct = function() { };

Class.extend = function(def) {
    var classDef = function() {
        if (arguments[0] !== Class) {
            this.construct.apply(this, arguments);
        }
    };
    var proto = new this(Class);
    var superClass = this.prototype;
    for (var n in def) {
        var item = def[n];
        if (item instanceof Function) item.$ = superClass;
                else classDef[n] = item;
        proto[n] = item;
    }
    classDef.prototype = proto;
    classDef.extend = this.extend;
    return classDef;
};

// ----------------------------------------------------------------------------
var LoadingClass = Class.extend({
    element: null,
    
    construct: function (options) {
        this.element = $(
            '<div style="background: #ffffff;padding:10px;display:none;position:absolute;left:0px;top:0px;width:50px;height:50px">' +
                // background: #ffffff;margin:0 auto;padding:10px;width:210px;
                '<img src="/img/main/loading.gif" alt="" style="width:50px;height:50px;float:left" />' +
                //'<p style="margin-left:60px">Загрузка ...</p>' +
            '</div>'
        );
    },
    
    show: function() {
        $(this.element)
            .appendTo(document.body)
            .css({
                left: ($(document).width() - $(this.element).width()) / 2,
                top: ($(window).height() - $(this.element).height()) / 2 + $(document).scrollTop()
            })
            .show();
    },
    
    hide: function() {
        $(this.element)
            .hide()
            .remove();
    }
});

// ----------------------------------------------------------------------------
var ErrorClass = Class.extend({
    element: null,
    
    construct: function (message) {
        var self = this;
    	this.element = $(
            '<div style="background: #ffffff;margin:0 auto;padding:10px;display:none;position:absolute;left:0px;top:0px;width:210px;border:1px solid darkred">' +
                '<img src="/i/icons/error.gif" alt="" style="width:50px;height:50px;float:left" />' +
                '<p style="margin-left:60px" class="text"></p>' +
                '<div style="text-align:center;margin:10px"><button>Закрыть</button></div>' + 
            '</div>'
        );
        
        $('.text', this.element).html(nl2br(htmlspecialchars(message ? message : '')));
        $('button', this.element).click(function() {
        	self.hide();
        });
    },
    
    show: function() {
        $(this.element)
            .appendTo(document.body)
            .css({
                left: ($(document).width() - $(this.element).width()) / 2,
                top: ($(document).height() - $(this.element).height()) / 2
            })
            .show();
    },
    
    hide: function() {
        $(this.element)
            .hide()
            .remove();
    }
});
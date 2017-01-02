var Navbar = require("navbar/navbar.js");
var $ = require("jquery");

require("bootstrap/bootstrap");
require("styles.less");
require("flags/flags.js");
require("font-awesome-webpack");
require('es6-promise').polyfill();

var doc = document;
$(function() {
    Navbar.init();
    
    $(doc.body).on('click', '[data-trigger=show-pm-dialog]', function(e) {
        e.preventDefault();
        var userId = $(this).data('userId');
        require(['message'], function(Message) {
            Message.showDialog(userId);
        });
    });
    
    $('.inline-picture-preview').each(function() {
        var element = this;
        require(['inline-picture'], function(InlinePicture) {
            new InlinePicture(element);
        });
    });
    
    $('a.picture-hover-preview').each(function() {
        var href = $(this).attr('href');
        var element = null;
        var anchor = $(this);
        var loaded = false;
        
        var fadeOutTimer = null;
        
        function over() {
            clearInterval(fadeOutTimer);
            fadeOutTimer = null;
        }
        
        function out() {
            clearInterval(fadeOutTimer);
            fadeOutTimer = null;
            fadeOutTimer = setInterval(function() {
                element.hide();
                clearInterval(fadeOutTimer);
            }, 1500);
        }
        
        $(this).hover(function () {
            over();
            if (!element) {
                var offset = anchor.offset();
                element = $('<div style="position:absolute;width:170px">Loading ...</div>');
                element.css({
                    left: offset.left,
                    top: offset.top + anchor.height()
                });
                element.hover(over, out);
                
                $(doc.body).append(element);
            }
            
            if (!loaded) {
                loaded = true;
                $.get(href, {preview: 1}, function(html) {
                    element.empty().append(html);
                    var offset = anchor.offset();
                    element.css({
                        left: offset.left,
                        top: offset.top + anchor.height()
                    });
                }, 'text');
            } else {
                element.show();
            }
        }, out);
        
    });
    
    $('[data-module]').each(function() {
        var element = this;
        var module = './' + $(this).data('module');
        require([module], function(Module) {
            Module(element);
        });
    });
    
    $('[data-page-module]').each(function() {
        var module = './' + $(this).data('page-module');
        var moduleOptions = $(this).data('page-module-options');
        require([module], function(Module) {
            Module.init(moduleOptions);
        });
    });
    
    $('footer [data-toggle="tooltip"]').tooltip();
});

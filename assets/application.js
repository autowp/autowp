var Navbar = require("navbar/navbar.js");
var $ = require("jquery");
var i18next = require('i18next');
import notify from 'notify';

require("angular.app");
require('app.module');
require("bootstrap/bootstrap");
require("styles.less");
require("flags/flags.js");

var resources = {};
$.map(['en', 'fr', 'ru', 'zh', 'be', 'pt-br'], function(language) {
    resources[language] = {
        translation: require("languages/"+language+".json")
    };
});

i18next.init({
    lng: $(document.documentElement).attr('lang'),
    resources: resources
});

var doc = document;
$(function() {
    Navbar.init();
    
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
        var moduleName = $(this).data('module');
        require(['widget/' + moduleName], function(Module) {
            Module(element);
        });
    });
    
    $('[data-page-module]').each(function() {
        var moduleName = $(this).data('page-module');
        var moduleOptions = $(this).data('page-module-options');
        require(['pages/' + moduleName], function(Module) {
            Module.init(moduleOptions);
        });
    });
    
    $('footer [data-toggle="tooltip"]').tooltip();
    
    $('form.login').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        
        $form.find('.help-block').remove();
        
        $.ajax({
            method: 'POST',
            url: '/api/login',
            data: {
                login: $form.find(':input[name=login]').val(),
                password: $form.find(':input[name=password]').val(),
                remember: $form.find(':input[name=remember]').prop('checked') ? 1 : 0
            }
        }).then(function() {
            window.location = '/ng/login/ok'; 
        }, function(response) {
            if (response.status == 400) {
                $.each(response.responseJSON.invalid_params, function(field, errors) {
                    var $input = $form.find(':input[name='+field+']');
                    $.map(errors, function(message) {
                        var $p = $('<p class="help-block" />').text(message);
                        $p.insertAfter($input);
                    });
                });
                
            } else {
                notify.response(response);
            }
        });
    });
    
    $(document.body).on('click', 'a.logout', function() {
        $.ajax({
            method: 'DELETE',
            url: '/api/login'
        }).then(function() {
            
            window.location = '/ng/login';
            
        }, function(response) {
            notify.response(response);
        });
    });
});

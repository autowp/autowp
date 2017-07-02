import angular from 'angular';
import Module from 'app.module';

angular.module(Module)
    .directive('autoFocus', function($timeout) {
        return {
            restrict: 'AC',
            link: function(_scope, element) {
                $timeout(function(){
                    element[0].focus();
                }, 0);
            }
        };
    });
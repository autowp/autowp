import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

angular.module(Module)
    .directive('autowpModerItemTree', function() {
        return {
            restirct: 'E',
            scope: {
                item: '='
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ 
                function() {
                    
                }
            ]
        };
    });
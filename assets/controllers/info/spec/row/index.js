import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

angular.module(Module)
    .directive('autowpInfoSpecRow', function() {
        return {
            restirct: 'E',
            scope: {
                row: '<',
                deep: '<'
            },
            template: template,
            transclude: true,
            controller: [ 
                function() {
                }
            ]
        };
    });
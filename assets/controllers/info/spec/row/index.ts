import * as angular from 'angular';
import Module from 'app.module';

angular.module(Module)
    .directive('autowpInfoSpecRow', function() {
        return {
            restirct: 'E',
            scope: {
                row: '<',
                deep: '<'
            },
            template: require('./template.html'),
            transclude: true,
            controller: [ 
                function() {
                }
            ]
        };
    });
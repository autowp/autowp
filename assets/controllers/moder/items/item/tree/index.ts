import * as angular from 'angular';
import Module from 'app.module';

angular.module(Module)
    .directive('autowpModerItemTree', function() {
        return {
            restirct: 'E',
            scope: {
                item: '='
            },
            template: require('./template.html'),
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ 
                function() {
                    
                }
            ]
        };
    });
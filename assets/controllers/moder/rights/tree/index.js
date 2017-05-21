import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

angular.module(Module)
    .directive('autowpRolesTree', function() {
        return {
            restirct: 'E',
            scope: {
                roles: '='
            },
            template: template,
            transclude: true
        };
    });
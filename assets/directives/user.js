import angular from 'angular';
import Module from 'app.module';
import template from './user.html';

angular.module(Module)
    .directive('autowpUser', function() {
        return {
            restirct: 'E',
            scope: {
                user: '=',
                isModer: '='
            },
            template: template,
            transclude: true,
            controller: ['$scope', function($scope) {
            }]
        };
    });
import angular from 'angular';
import Module from 'app.module';
import template from './user.html';
import sprintf from 'sprintf';

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
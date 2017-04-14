import angular from 'angular';
import Module from 'app.module';
import template from './markdown-edit.html';

angular.module(Module)
    .directive('autowpMarkdownEdit', function() {
        return {
            restirct: 'E',
            scope: {
                text: '=',
                save: '='
            },
            template: template,
            controller: ['$scope', function($scope) {
            }]
        };
    });
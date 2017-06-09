import angular from 'angular';
import Module from 'app.module';
import template from './markdown-edit.html';
import autosize from 'autosize';

angular.module(Module)
    .directive('autowpMarkdownEdit', function() {
        return {
            restirct: 'E',
            scope: {
                text: '=',
                save: '='
            },
            template: template,
            controller: ['$scope', '$element', function($scope, $element) {
                autosize($element.find('textarea'));
            }]
        };
    });
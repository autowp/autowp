import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

import './styles.less';

angular.module(Module)
    .directive('autowpPastTimeIndicator', function() {
        return {
            restirct: 'E',
            scope: {
                date: '<'
            },
            template: template,
            controllerAs: 'ctrl',
            controller: ['$scope',
                function($scope) {
                    this.past = (new Date($scope.date)).getTime() < ((new Date()).getTime() - 86400*1000);
                }
            ]
        };
    });
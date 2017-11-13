import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import { SpecService } from 'services/spec';

import './row';

const CONTROLLER_NAME = 'InfoSpecController';
const STATE_NAME = 'info-spec';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/info/spec',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', 'SpecService',
        function($scope, SpecService) {
            
            var ctrl = this;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/174/name',
                pageId: 174
            });
            
            SpecService.getSpecs().then(function(specs) {
                ctrl.specs = specs;
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;

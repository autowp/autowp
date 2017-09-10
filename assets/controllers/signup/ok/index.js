import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'SignupOkController';
const STATE_NAME = 'signup-ok';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/signup/ok',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope',
        function($scope) {
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                pageId: 53
            });
        }
    ]);

export default CONTROLLER_NAME;

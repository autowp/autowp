import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'RestorePasswordNewOkController';
const STATE_NAME = 'restore-password-new-ok';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/new/ok',
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
                name: 'page/135/name',
                pageId: 135
            });
        }
    ]);

export default CONTROLLER_NAME;

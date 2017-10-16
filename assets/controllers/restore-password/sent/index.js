import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'RestorePasswordSentController';
const STATE_NAME = 'restore-password-sent';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/sent',
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
                name: 'page/60/name',
                pageId: 60
            });
        }
    ]);

export default CONTROLLER_NAME;

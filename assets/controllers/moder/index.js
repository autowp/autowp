import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'ModerController';
const STATE_NAME = 'moder';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder',
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
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 67
            });
        }
    ]);

export default CONTROLLER_NAME;

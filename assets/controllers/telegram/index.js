import angular from 'angular';
import Module from 'app.module';
import template from './template.html';

const CONTROLLER_NAME = 'TelegramController';
const STATE_NAME = 'telegram';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/telegram',
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
                name: 'page/204/name',
                pageId: 204
            });
        }
    ]);

export default CONTROLLER_NAME;

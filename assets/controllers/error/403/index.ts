import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'Error403Controller';
const STATE_NAME = 'error-403';

export class Error403Controller {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: true
            },
            title: '403 Forbidden'
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, Error403Controller)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/error/403',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


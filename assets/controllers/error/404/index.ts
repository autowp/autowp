import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'Error404Controller';
const STATE_NAME = 'error-404';

export class Error404Controller {
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
            title: '404 Not Found'
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, Error404Controller)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/error/404',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

export default CONTROLLER_NAME;

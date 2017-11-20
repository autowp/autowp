import * as angular from 'angular';
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';

const CONTROLLER_NAME = 'LoginOkController';
const STATE_NAME = 'login-ok';

export class LoginOkController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: IAutowpControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/79/name',
            pageId: 79
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, LoginOkController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/login/ok',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'AccountDeletedController';
const STATE_NAME = 'account-deleted';

export class AccountDeletedController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/93/name',
            pageId: 93
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountDeletedController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/deleted',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


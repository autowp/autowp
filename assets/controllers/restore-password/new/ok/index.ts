import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'RestorePasswordNewOkController';
const STATE_NAME = 'restore-password-new-ok';

export class RestorePasswordNewOkController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/135/name',
            pageId: 135
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, RestorePasswordNewOkController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/new/ok',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


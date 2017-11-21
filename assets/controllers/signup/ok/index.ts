import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'SignupOkController';
const STATE_NAME = 'signup-ok';

export class SignupOkController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/53/name',
            pageId: 53
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, SignupOkController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/signup/ok',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);



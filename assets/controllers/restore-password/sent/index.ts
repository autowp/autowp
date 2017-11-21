import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'RestorePasswordSentController';
const STATE_NAME = 'restore-password-sent';

export class RestorePasswordSentController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/60/name',
            pageId: 60
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, RestorePasswordSentController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/sent',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


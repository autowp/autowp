import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'TelegramController';
const STATE_NAME = 'telegram';

export class TelegramController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/204/name',
            pageId: 204
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, TelegramController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/telegram',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);

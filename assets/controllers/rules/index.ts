import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'RulesController';
const STATE_NAME = 'rules';

export class RulesController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: autowp.IControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/106/name',
            pageId: 106
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, RulesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/rules',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


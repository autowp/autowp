import * as angular from 'angular';
import Module from 'app.module';

const CONTROLLER_NAME = 'FeedbackSentController';
const STATE_NAME = 'feedback-sent';

export class FeedbackSentController {
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
    .controller(CONTROLLER_NAME, FeedbackSentController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/feedback/sent',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);



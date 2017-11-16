import * as angular from "angular";
import Module from 'app.module';
import { IAutowpControllerScope } from 'declarations.d.ts';
import { UserService } from 'services/user';

const CONTROLLER_NAME = 'DonateVodSuccessController';
const STATE_NAME = 'donate-vod-success';

export class DonateVodSuccessController {
    static $inject = ['$scope'];
  
    constructor(
        private $scope: IAutowpControllerScope
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/196/name',
            pageId: 196
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateVodSuccessController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate/vod/success',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])

import * as angular from "angular";
import Module from 'app.module';
import { UserService } from 'services/user';

const CONTROLLER_NAME = 'DonateLogController';
const STATE_NAME = 'donate-log';

export class DonateLogController {
    static $inject = ['$scope', 'UserService'];
    public items: any[]; 
  
    constructor(
        private $scope: autowp.IControllerScope,
        private UserService: UserService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/196/name',
            pageId: 196
        });
      
        this.items = require('./data.json');
        
        for (let item of this.items) {
            if (item.user_id) {
                this.UserService.getUser(item.user_id).then(function(user) {
                    item.user = user;
                });
            }
        }
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, DonateLogController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/donate/log',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])

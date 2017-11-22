import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountEmailcheckController';
const STATE_NAME = 'account-emailcheck';

export class AccountEmailcheckController {
    static $inject = ['$scope', '$http', '$state'];
  
    public success: boolean = false;
    public failure: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any
    ) {
        var self = this;
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/54/name',
            pageId: 54
        });
        
        this.$http({
            method: 'POST',
            url: '/api/user/emailcheck',
            data: {
                code: this.$state.params.code
            }
        }).then(function() {
            
            self.success = true;
            
        }, function(response: ng.IHttpResponse<any>) {
            self.failure = true;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountEmailcheckController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/emailcheck/:code',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountEmailController';
const STATE_NAME = 'account-email';

export class AccountEmailController {
    static $inject = ['$scope', '$http', '$state'];
  
    public email: string|null = null;
    public newEmail: string|null = null;
    public invalidParams: any;
    public sent: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/55/name',
            pageId: 55
        });
      
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/user/me',
            params: {
                fields: 'email'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.email = response.data.email;
            self.newEmail = response.data.email;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public submit() {
        this.invalidParams = {};
      
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/user/me',
            data: {
                email: this.newEmail
            }
        }).then(function() {
            
            self.sent = true;
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountEmailController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/email',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


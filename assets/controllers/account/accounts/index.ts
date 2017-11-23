import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'AccountAccountsController';
const STATE_NAME = 'account-accounts';

export class AccountAccountsController {
    static $inject = ['$scope', '$http', '$state', '$translate'];
  
    public service = null;
    public accounts: any[] = [];
    public connectFailed = false;
    public disconnectFailed = false;
    public services = {
        facebook: 'Facebook',
        vk: 'VK',
        'google-plus': 'Google+',
        twitter: 'Twitter',
        github: 'Github',
        linkedin: 'Linkedin'
    };
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService
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
            name: 'page/123/name',
            pageId: 123
        });
      
        this.load();
    }
  
    public load() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/account'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.accounts = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public start() {
        
        if (! this.service) {
            return;
        }
        
        this.$http({
            method: 'POST',
            url: '/api/account/start',
            data: {
                service: this.service
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            window.location.href = response.data.url;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public remove(account: any) {
      
        var self = this;
      
        this.$http({
            method: 'DELETE',
            url: '/api/account/' + account.id
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.$translate('account/accounts/removed').then(function(translation: string) {
                new notify({
                    icon: 'fa fa-check',
                    message: translation
                }, {
                    type: 'success'
                });
            });
            
            self.load();
        }, function(response: ng.IHttpResponse<any>) {
            self.disconnectFailed = true;
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountAccountsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/accounts',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


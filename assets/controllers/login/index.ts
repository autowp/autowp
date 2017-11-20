import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { IAutowpControllerScope, IAutowpRootControllerScope } from 'declarations.d.ts';

import './ok';

const CONTROLLER_NAME = 'LoginController';
const STATE_NAME = 'login';

export class LoginController {
    static $inject = ['$scope', '$rootScope', '$http', '$state'];
    public services: any[] = [];
    public form = {
        login: '',
        password: '',
        remember: false
    };
    public invalidParams: any;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $rootScope: IAutowpRootControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        if (this.$rootScope.getUser()) {
            this.$state.go('login-ok');
            return;
        }
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/79/name',
            pageId: 79
        });
        
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/login/services'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.services = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public submit() {
        
        var self = this;
        
        this.$http({
            method: 'POST',
            url: '/api/login',
            data: this.form
        }).then(function() {
            self.$http({
                method: 'GET',
                url: '/api/user/me'
            }).then(function(response: ng.IHttpResponse<any>) {
                self.$rootScope.setUser(response.data);
                self.$state.go('login-ok');
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    };
    
    public start(serviceId: string) {
        this.$http({
            method: 'GET',
            url: '/api/login/start',
            params: {
                type: serviceId
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            window.location.href = response.data.url;
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, LoginController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/login',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


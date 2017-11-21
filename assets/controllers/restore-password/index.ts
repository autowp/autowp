import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

import './new';
import './sent';

const CONTROLLER_NAME = 'RestorePasswordController';
const STATE_NAME = 'restore-password';

export class RestorePasswordController {
    static $inject = ['$scope', '$http', '$state'];
    public recaptchaKey: string;
    public showCaptcha: boolean = false;
    public form = {
        email: '',
        captcha: ''
    };
    public invalidParams: any;
    public failure: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/recaptcha',
            data: this.form
        }).then(function(response: ng.IHttpResponse<any>) {
            self.recaptchaKey = response.data.publicKey;
            self.showCaptcha = !response.data.success;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/60/name',
            pageId: 60
        });
    }
  
    public submit() {
      
        var self = this;
      
        this.$http({
            method: 'POST',
            url: '/api/restore-password/request',
            data: this.form
        }).then(function() {
            self.$state.go('restore-password-sent');
        }, function(response: ng.IHttpResponse<any>) {
            self.failure = response.status == 404;
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
                
                self.showCaptcha = response.data.invalid_params.captcha;
            } else if (response.status == 404) {
                
            } else {
                notify.response(response);
            }
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, RestorePasswordController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


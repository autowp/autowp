import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

import './ok';

const CONTROLLER_NAME = 'SignupController';
const STATE_NAME = 'signup';

export class SignupController {
    static $inject = ['$scope', '$http', '$state'];
    public recaptchaKey: string;
    public showCaptcha: boolean = false;
    public form = {
        email: '',
        name: '',
        password: '',
        password_confirm: '',
        captcha: ''
    };
    public invalidParams: any;
  
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
        
        $scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/52/name',
            pageId: 52
        });
    }
  
    public submit() {
        var self = this;
      
        this.$http({
            method: 'POST',
            url: '/api/user',
            data: this.form
        }).then(function() {
            self.$state.go('signup-ok');
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
                
                self.showCaptcha = response.data.invalid_params.captcha;
            } else {
                notify.response(response);
            }
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, SignupController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/signup',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


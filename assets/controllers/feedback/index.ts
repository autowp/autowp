import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

import './sent';

const CONTROLLER_NAME = 'FeedbackController';
const STATE_NAME = 'feedback';

export class FeedbackController {
    static $inject = ['$scope', '$http', '$state'];
  
    public form = {
        name: '',
        email: '',
        message: '',
        captcha: ''
    };
    public recaptchaKey: string;
    public showCaptcha: boolean = true;
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
            data: self.form
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
            name: 'page/89/name',
            pageId: 89
        });
    }
  
    public submit() {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/feedback',
            data: this.form
        }).then(function() {
            self.$state.go('feedback-sent');
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
                
                self.showCaptcha = response.data.invalid_params.captcha;
            } else {
                notify.response(response);
            }
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, FeedbackController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/feedback',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


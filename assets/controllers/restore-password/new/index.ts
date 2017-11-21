import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

import './ok';

const CONTROLLER_NAME = 'RestorePasswordNewController';
const STATE_NAME = 'restore-password-new';

export class RestorePasswordNewController {
    static $inject = ['$scope', '$http', '$state'];
    public form = {
        code: '',
        password: '',
        password_confirm: ''
    };
    public invalidParams: any;
    public failure: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        var self = this;
      
        this.form.code = this.$state.params.code;
            
        this.$http({
            method: 'GET',
            url: '/api/restore-password/new',
            params: {
                code: this.$state.params.code
            }
        }).then(function() {
            
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });
        
        $scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/134/name',
            pageId: 134
        });
    }
  
    public submit() {
      
        var self = this;
      
        this.$http({
            method: 'POST',
            url: '/api/restore-password/new',
            data: this.form
        }).then(function() {
            self.$state.go('restore-password-new-ok');
        }, function(response: ng.IHttpResponse<any>) {
            self.failure = response.status == 404;
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else if (response.status == 404) {
                
            } else {
                notify.response(response);
            }
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, RestorePasswordNewController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/restore-password/new?code',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


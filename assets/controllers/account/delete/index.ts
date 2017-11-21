import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

import './deleted';

const CONTROLLER_NAME = 'AccountDeleteController';
const STATE_NAME = 'account-delete';

export class AccountDeleteController {
    static $inject = ['$scope', '$http', '$state'];
  
    public form = {
        password_old: ''
    };
    public invalidParams: any;
  
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
            name: 'page/137/name',
            pageId: 137
        });
    }
  
    public submit() {
      
        var self = this;
      
        this.$http({
            method: 'PUT',
            url: '/api/user/me',
            data: {
                password_old: self.form.password_old,
                deleted: 1
            }
        }).then(function() {
            self.$scope.setUser(null);
            self.$state.go('account-deleted');
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
    .controller(CONTROLLER_NAME, AccountDeleteController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/delete',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


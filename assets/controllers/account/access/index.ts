import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { IAutowpControllerScope } from 'declarations.d.ts';

const CONTROLLER_NAME = 'AccountAccessController';
const STATE_NAME = 'account-access';

export class AccountAccessController {
    static $inject = ['$scope', '$http', '$state', '$translate'];
    public invalidParams: any;
    public form: any;
  
    constructor(
        private $scope: IAutowpControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.form = {
            password_old: null,
            password: null,
            password_confirm: null
        };
        
        this.invalidParams = {};
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/133/name',
            pageId: 133
        });
    }
    
    public submit() {
        this.invalidParams = {};
        
        var self = this;
        
        this.$http({
            method: 'PUT',
            url: '/api/user/me',
            data: this.form
        }).then(function() {
            
            self.form = {
                password_old: null,
                password: null,
                password_confirm: null
            };
            
            self.$translate('account/access/change-password/saved').then(function(translation) {
                notify({
                    icon: 'fa fa-check',
                    message: translation
                }, {
                    type: 'success'
                });
            });
            
        }, function(response) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    };
};

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountAccessController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/access',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { UserService } from 'services/user';

const CONTROLLER_NAME = 'AccountSpecsConflictsController';
const STATE_NAME = 'account-specs-conflicts';

export class AccountSpecsConflictsController {
    static $inject = ['$scope', '$http', '$state', 'UserService'];
  
    public filter: string;
    public conflicts: any[] = [];
    public paginator: autowp.IPaginator;
    public weight: number|null = null;
    public page: number;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private UserService: UserService
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.filter = $state.params.filter || '0';
        this.page = $state.params.page;
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/188/name',
            pageId: 188
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/user/me',
            params: {
                fields: 'specs_weight'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.weight = response.data.specs_weight;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        $http({
            method: 'GET',
            url: '/api/attr/conflict?fields=values',
            params: {
                filter: self.filter,
                page: self.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.conflicts = response.data.items;
            angular.forEach(self.conflicts, function(conflict) {
                angular.forEach(conflict.values, function(value) {
                    if ($scope.user.id != value.user_id) {
                        UserService.getUser(value.user_id).then(function(user) {
                            value.user = user;
                        });
                    }
                });
            });
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountSpecsConflictsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/specs-conflicts?filter&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


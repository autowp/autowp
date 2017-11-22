import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerUsersController';
const STATE_NAME = 'moder-users';

export class ModerUsersController {
    static $inject = ['$scope', '$http', '$state'];

    public paginator: autowp.IPaginator;
    public loading: number = 0;
    public users: any[] = [];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/203/name',
            pageId: 203
        });
        
        this.load();
    }
    
    private load() {
        this.loading++;
        this.users = [];
        
        var params = {
            page: this.$state.params.page,
            limit: 30,
            fields: 'image,reg_date,last_online,email,login'
        };
        
        this.$state.go(STATE_NAME, params, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/user',
            params: params
        }).then(function(response: ng.IHttpResponse<any>) {
            self.users = response.data.items;
            self.paginator = response.data.paginator;
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerUsersController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/users?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);


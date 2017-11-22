import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';

const STATE_NAME = 'moder-cars-alpha';
const CONTROLLER_NAME = 'ModerItemsAlphaController';

export class ModerItemsAlphaController {
    static $inject = ['$scope', '$http', '$state'];
    
    public loading: number = 0;
    public paginator: autowp.IPaginator | null = null;
    public page: number | null = null;
    public groups: any[];
    public items: any[];

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
            name: 'page/74/name',
            pageId: 74
        });
        
        this.page = $state.params.page;
        
        var self = this;
        this.$http.get('/api/item/alpha').then(function(response: ng.IHttpResponse<any>) {
            self.groups = response.data.groups;
        });
        
        if (this.$state.params.char) {
            this.loadChar(this.$state.params.char);
        }
    }
    
    public selectChar(char: string) {
        
        this.page = null;
        
        this.$state.go(STATE_NAME, {
            char: char,
            page: this.page
        }, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        this.loadChar(char);
    };
    
    public loadChar(char: string) {
        this.paginator = null;
        this.items = [];
        this.loading++;
        
        var self = this;
        this.$http.get('/api/item', {
            params: {
                name: char + '%',
                page: this.page,
                limit: 500,
                fields: 'name_html'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.paginator = response.data.paginator;
            self.items = response.data.items;
            self.loading--;
        }, function() {
            self.loading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsAlphaController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/cars/alpha?char&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    char: { dynamic: true },
                    page: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);


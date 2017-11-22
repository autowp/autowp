import *as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPagesAddController';
const STATE_NAME = 'moder-pages-add';

function toPlainArray(pages: any[], level: number): any[] {
    var result: any[] = [];
    angular.forEach(pages, function(page: any, i: number) {
        page.level = level;
        page.moveUp = i > 0;
        page.moveDown = i < pages.length-1;
        result.push(page);
        angular.forEach(toPlainArray(page.childs, level+1), function(child: any) {
            result.push(child);
        });
    });
    return result;
}

export class ModerPagesAddController {
    static $inject = ['$scope', '$http', '$state', 'AclService'];
    
    public loading: number = 0;
    public item: any = {};
    public pages: any[];

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private Acl: AclService
    ) {
        $scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/69/name',
            pageId: 69
        });
        
        var self = this;
        
        $http({
            method: 'GET',
            url: '/api/page'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pages = toPlainArray(response.data.items, 0);
        });
    }
    
    public save() {
        this.loading++;
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/page',
            data: {
                parent_id: self.item.parent_id,
                name: self.item.name,
                title: self.item.title,
                breadcrumbs: self.item.breadcrumbs,
                url: self.item.url,
                is_group_node: self.item.is_group_node ? 1 : 0,
                registered_only: self.item.registered_only ? 1 : 0,
                guest_only: self.item.guest_only ? 1 : 0,
                'class': self.item['class']
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.loading--;
            
            self.loading++;
            self.$http({
                method: 'GET',
                url: response.headers('Location')
            }).then(function(response: ng.IHttpResponse<any>) {
                self.loading--;
                
                self.$state.go('moder-pages-edit', {
                    id: response.data.id
                });
            }, function() {
                self.loading--;
            });

        }, function() {
            self.loading--;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPagesAddController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pages/add',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('pages-moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);
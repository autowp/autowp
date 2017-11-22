import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import './add';
import './edit';

const CONTROLLER_NAME = 'ModerPagesController';
const STATE_NAME = 'moder-pages'; 

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

export class ModerPagesController {
    static $inject = ['$scope', '$http', 'AclService'];
    
    public items: any[] = [];
    public canManage: boolean = false;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private Acl: AclService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/68/name',
            pageId: 68
        });
        
        var self = this;
        
        this.Acl.isAllowed('hotlinks', 'manage').then(function(allow) {
            self.canManage = !!allow;
        }, function() {
            self.canManage = false;
        });

        this.load();
    }
    
    private load() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/page'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = toPlainArray(response.data.items, 0);
        });
    };
    
    public move(page: any, direction: any) {
        var self = this;
        this.$http({
            method: 'PUT',
            url: '/api/page/' + page.id,
            data: {
                position: direction
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.load();
        });
    }
    
    public deletePage(ev: any, page: any) {
        var self = this;
        if (window.confirm('Would you like to delete page?')) {
            this.$http({
                method: 'DELETE',
                url: '/api/page/' + page.id
            }).then(function(response: ng.IHttpResponse<any>) {
                self.load();
            });
        }
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPagesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pages',
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
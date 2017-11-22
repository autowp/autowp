import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPerspectivesController';
const STATE_NAME = 'moder-perspectives';

export class ModerPerspectivesController {
    static $inject = ['$scope', '$http'];
    
    public pages: any[];

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/202/name',
            pageId: 202
        });
        
        var self = this;
            
        this.$http({
            method: 'GET',
            url: '/api/perspective-page',
            params: {
                fields: 'groups.perspectives'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pages = response.data.items;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPerspectivesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/perspectives',
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


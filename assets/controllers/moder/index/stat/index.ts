import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerIndexStatController';

export class ModerIndexStatController {
    static $inject = ['$scope', '$http'];
    
    public items: any[];

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
            name: 'page/119/name',
            pageId: 119
        });
        
        var self = this;
        this.$http.get('/api/stat/global-summary').then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerIndexStatController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: 'moder-index-stat',
                url: '/moder/index/stat',
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


import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';

const STATE_NAME = 'moder-items-too-big';
const CONTROLLER_NAME = 'ModerItemsTooBigController';

export class ModerItemsTooBigController {
    static $inject = ['$scope', '$http', '$state'];
    
    public loading: boolean = false;
    public items: any[];

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.loading = true;
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            }
        });
        
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                order: 'childs_count',
                limit: 100,
                fields: 'childs_count,name_html'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.loading = false;
        }, function() {
            self.loading = false;
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsTooBigController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/item/too-big',
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
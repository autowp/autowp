import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerLogController';
const STATE_NAME = 'log';

export class ModerLogController {
    static $inject = ['$scope', '$http', '$state'];

    public items: any[] = [];
    public paginator: autowp.IPaginator;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/75/name',
            pageId: 75
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/log',
            params: {
                article_id: this.$state.params.article_id,
                item_id: this.$state.params.item_id,
                picture_id: this.$state.params.picture_id,
                page: this.$state.params.page,
                user_id: this.$state.params.user_id,
                fields: 'pictures.name_html,items.name_html,user'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    } 
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerLogController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/log?article_id&item_id&picture_id&user_id&page',
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


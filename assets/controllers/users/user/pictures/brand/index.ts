import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'UsersUserPicturesBrandController';
const STATE_NAME = 'users-user-pictures-brand';

export class UsersUserPicturesBrandController {
    static $inject = ['$scope', '$state', '$http'];
    public user: any;
    public chunks: any[];
    public paginator: autowp.IPaginator;
    public brand: any;
    public identity: string;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $state: any,
        private $http: ng.IHttpService
    ) {
        var self = this;
            
        var result = this.$state.params.identity.match(/^user([0-9]+)$/);
        
        if (result) {
            this.$http({
                method: 'GET',
                url: '/api/user/' + result[1],
                params: {
                    fields: 'identity'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.user = response.data;
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        } else {
            this.$http({
                method: 'GET',
                url: '/api/user',
                params: {
                    identity: this.$state.params.identity,
                    limit: 1,
                    fields: 'identity'
                }
            }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                if (response.data.items.length <= 0) {
                    self.$state.go('error-404');
                    return;
                }
                self.user = response.data.items[0];
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    }
  
    public init() {
        if (this.user.deleted) {
            this.$state.go('error-404');
            return;
        }
        
        this.identity = this.user.identity ? this.user.identity : 'user' + this.user.id;
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                limit: 1,
                catname: this.$state.params.brand,
                fields: 'name_only,catname'
            }
        }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
            if (response.data.items.length <= 0) {
                self.$state.go('error-404');
                return;
            }
            self.brand = response.data.items[0];
            
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/141/name',
                pageId: 141,
                args: {
                    USER_NAME: self.user.name,
                    USER_IDENTITY: self.identity,
                    BRAND_NAME: self.brand.name_only,
                    BRAND_CATNAME: self.brand.catname
                }
            });
            
            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                    limit: 30,
                    page: self.$state.params.page,
                    item_id: self.brand.id,
                    owner_id: self.user.id,
                    order: 1
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.chunks = chunkBy(response.data.pictures, 6);
                self.paginator = response.data.paginator;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UsersUserPicturesBrandController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/pictures/:brand?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


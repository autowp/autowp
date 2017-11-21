import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

require("brandicon");

import './brand';

const CONTROLLER_NAME = 'UsersUserPicturesController';
const STATE_NAME = 'users-user-pictures';

export class UsersUserPicturesController {
    static $inject = ['$scope', '$state', '$http'];
    public user: any;
    public paginator: autowp.IPaginator;
    public brands: any[];
    public identity: string;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $state: any,
        private $http: ng.IHttpService
    ) {
        var self = this;
            
        var result = $state.params.identity.match(/^user([0-9]+)$/);
        
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
                    identity: $state.params.identity,
                    limit: 1,
                    fields: 'identity'
                }
            }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                if (response.data.items.length <= 0) {
                    $state.go('error-404');
                }
                self.user = response.data.items[0];
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    }
  
    public init() {
        this.identity = this.user.identity ? this.user.identity : 'user' + this.user.id;
                
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/63/name',
            pageId: 63,
            args: {
                USER_NAME: this.user.name,
                USER_IDENTITY: this.identity
            }
        });
      
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                type_id: 5,
                limit: 500,
                order: 'name_nat',
                fields: 'name_only,catname,current_pictures_count',
                'descendant_pictures[status]': 'accepted',
                'descendant_pictures[owner_id]': this.user.id
            }
        }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
            self.brands = response.data.items;
            angular.forEach(self.brands, function(item: any) {
                item.cssClass = item.catname.replace(/\./g, '_');
            });
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UsersUserPicturesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/pictures',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


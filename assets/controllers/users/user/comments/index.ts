import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'UsersUserCommentsController';
const STATE_NAME = 'users-user-comments';

export class UsersUserCommentsController {
    static $inject = ['$scope', '$state', '$http'];
    public loading: number = 0;
    public user: any;
    public paginator: autowp.IPaginator;
    public comments: any[];
    public orders = {
        date_desc: 'users/comments/order/new',
        date_asc: 'users/comments/order/old',
        vote_desc: 'users/comments/order/positive',
        vote_asc: 'users/comments/order/negative'
    };
    public order: string;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $state: any,
        private $http: ng.IHttpService
    ) {
        this.order = this.$state.params.order || 'date_desc';
        
        var result = this.$state.params.identity.match(/^user([0-9]+)$/);
        
        var self = this;
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
                }
                self.user = response.data.items[0];
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    }
  
    public init() {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/205/name',
            pageId: 205,
            args: {
                USER_NAME: this.user.name,
                USER_IDENTITY: this.user.identity ? this.user.identity : 'user' + this.user.id
            }
        });
        
        var params = {
            user_id: this.user.id,
            page: this.$state.params.page,
            limit: 30,
            order: this.order,
            fields: 'preview,url,vote'
        };
      
        var self = this;
        
        this.loading++;
        this.$http({
            method: 'GET',
            url: '/api/comment',
            params: params
        }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
            self.comments = response.data.items;
            self.paginator = response.data.paginator;
            self.loading--;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
            self.loading--;
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UsersUserCommentsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/comments?order&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    order: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ]);



import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'AccountInboxPicturesController';
const STATE_NAME = 'account-inbox-pictures';

export class AccountInboxPicturesController {
    static $inject = ['$scope', '$http', '$state'];
  
    public pictures: any[] = [];
    public paginator: autowp.IPaginator;
    public chunks: any[] = [];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/94/name',
            pageId: 94
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: {
                status: 'inbox',
                owner_id: $scope.user.id,
                fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                limit: 16,
                page: $state.params.page,
                order: 1
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pictures = response.data.pictures;
            self.chunks = chunkBy(self.pictures, 4);
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountInboxPicturesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/inbox-pictures?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'CutawayController';
const STATE_NAME = 'cutaway';

export class CutawayController {
    static $inject = ['$scope', '$http', '$state'];
  
    public chunks: any[] = [];
    public paginator: autowp.IPaginator;
    
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        var self = this;
            
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/109/name',
            pageId: 109
        });
            
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: {
                status: 'accepted',
                fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                limit: 18,
                page: $state.params.page,
                perspective_id: 9,
                order: 15
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.chunks = chunkBy(response.data.pictures, 6);
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CutawayController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cutaway/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    page: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ]);


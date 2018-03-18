import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ForumsSubscriptionsController';
const STATE_NAME = 'forums-subscriptions';

export class ForumsSubscriptionsController {
    static $inject = ['$scope', '$http', '$state', 'AclService'];

    public topics: any[] = [];
    public paginator: autowp.IPaginator;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private Acl: AclService
    ) {
        var self = this;

        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/42/name',
            pageId: 42
        });

        this.$http({
            url: '/api/forum/topic',
            method: 'GET',
            params: {
                fields: 'author,messages,last_message.datetime,last_message.user',
                subscription: 1,
                'page': $state.params.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.topics = response.data.items;
            self.paginator = response.data.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public unsubscribe(topic: any) {
        var self = this;
        this.$http({
            url: '/api/forum/topic/' + topic.id,
            method: 'PUT',
            data: {
                subscription: 0
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            for (var i=self.topics.length-1; i>=0; i--) {
                if (self.topics[i].id == topic.id) {
                    self.topics.splice(i, 1);
                    break;
                }
            }

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsSubscriptionsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/subscriptions?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ForumService } from 'services/forum';

const CONTROLLER_NAME = 'ForumsMoveMessageController';
const STATE_NAME = 'forums-move-message';

export class ForumsMoveMessageController {
    static $inject = ['$scope', '$http', '$state', 'ForumService'];

    public message_id: number;
    public themes: any[] = [];
    public theme: any = null;
    public topics: any[] = [];

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private Forum: ForumService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/83/name',
            pageId: 83
        });

        this.message_id = this.$state.params.message_id;

        var self = this;

        $http({
            url: '/api/forum/themes',
            method: 'GET'
        }).then(function(response: ng.IHttpResponse<any>) {

            self.themes = response.data.items;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public selectTheme(theme: any) {

        var self = this;

        this.theme = theme;
        this.$http({
            url: '/api/forum/topic',
            method: 'GET',
            params: {
                theme_id: theme.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            self.topics = response.data.items;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };

    public selectTopic(topic: any) {

        var self = this;

        this.$http({
            method: 'PUT',
            url: '/api/comment/' + this.message_id,
            data: {
                item_id: topic.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            self.Forum.getMessageStateParams(self.message_id).then(function(params: any) {
                self.$state.go('forums-topic', params);
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsMoveMessageController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/move-message?message_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


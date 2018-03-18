import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'ForumsMoveTopicController';
const STATE_NAME = 'forums-move-topic';

export class ForumsMoveTopicController {
    static $inject = ['$scope', '$http', '$state'];

    public message_id: number;
    public themes: any[] = [];
    public topic: any = null;

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
            name: 'page/83/name',
            pageId: 83
        });

        var ctrl = this;

        this.$http({
            url: '/api/forum/topic/' + this.$state.params.topic_id,
            method: 'GET'
        }).then(function(response: ng.IHttpResponse<any>) {

            ctrl.topic = response.data;

        }, function(response: ng.IHttpResponse<any>) {
            $state.go('error-404');
        });

        this.$http({
            url: '/api/forum/themes',
            method: 'GET'
        }).then(function(response: ng.IHttpResponse<any>) {

            ctrl.themes = response.data.items;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public selectTheme(theme: any) {

        var self = this;

        this.$http({
            method: 'PUT',
            url: '/api/forum/topic/' + this.topic.id,
            data: {
                theme_id: theme.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            self.$state.go('forums-topic', {
                topic_id: self.topic.id
            });

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsMoveTopicController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/move-topic?topic_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


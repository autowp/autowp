import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ForumService } from 'services/forum';

const CONTROLLER_NAME = 'ForumsTopicController';
const STATE_NAME = 'forums-topic';

export class ForumsTopicController {
    static $inject = ['$scope', '$http', '$state', '$translate', 'ForumService'];

    public topic: any;
    public paginator: autowp.IPaginator;
    public page: number;
    public limit: number;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService,
        private Forum: ForumService
    ) {
        var self = this;

        this.page = $state.params.page;
        this.limit = Forum.getLimit();

        this.$http({
            url: '/api/forum/topic/' + this.$state.params.topic_id,
            method: 'GET',
            params: {
                fields: 'author,theme,subscription',
                'page': this.$state.params.page
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.topic = response.data;

            self.$translate(self.topic.theme.name).then(function(translation: string) {
                self.$scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/44/name',
                    pageId: 44,
                    args: {
                        THEME_NAME: translation,
                        THEME_ID: self.topic.theme_id,
                        TOPIC_NAME: self.topic.name,
                        TOPIC_ID: self.topic.id
                    }
                });
            }, function() {
                self.$scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/44/name',
                    pageId: 44,
                    args: {
                        THEME_NAME: self.topic.theme.name,
                        THEME_ID: self.topic.theme_id,
                        TOPIC_NAME: self.topic.name,
                        TOPIC_ID: self.topic.id
                    }
                });
            });

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);

            self.$state.go('error-404');
        });
    }

    public subscribe() {
        var self = this;

        this.$http({
            url: '/api/forum/topic/' + this.topic.id,
            method: 'PUT',
            data: {
                subscription: 1
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            self.topic.subscription = true;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };

    public unsubscribe() {
        var self = this;

        this.$http({
            url: '/api/forum/topic/' + this.topic.id,
            method: 'PUT',
            data: {
                subscription: 0
            }
        }).then(function(response: ng.IHttpResponse<any>) {

            self.topic.subscription = false;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsTopicController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/topic/:topic_id?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


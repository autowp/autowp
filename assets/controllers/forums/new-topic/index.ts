import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ForumsNewTopicController';
const STATE_NAME = 'forums-new-topic';

export class ForumsNewTopicController {
    static $inject = ['$scope', '$http', '$state', 'AclService'];

    public form = {
        name: '',
        text: '',
        moderator_attention: false,
        subscription: false
    };
    public invalidParams: any;
    public theme: any;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private Acl: AclService
    ) {
        var self = this;

        this.$http({
            url: '/api/forum/themes/' + this.$state.params.theme_id,
            method: 'GET'
        }).then(function(response: ng.IHttpResponse<any>) {

            self.theme = response.data;

            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/45/name',
                pageId: 45,
                args: {
                    THEME_NAME: self.theme.name,
                    THEME_ID:   self.theme.id
                }
            });

        }, function(response: ng.IHttpResponse<any>) {
            $state.go('error-404');
        });
    }

    public submit() {
        this.invalidParams = {};

        var self = this;

        this.$http({
            method: 'POST',
            url: '/api/forum/topic',
            data: {
                theme_id: this.$state.params.theme_id,
                name: this.form.name,
                text: this.form.text,
                moderator_attention: this.form.moderator_attention ? 1 : 0,
                subscription: this.form.subscription ? 1 : 0
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            var location = response.headers('Location');

            self.$http({
                url: location,
                method: 'GET'
            }).then(function(response: ng.IHttpResponse<any>) {

                self.$state.go('forums-topic', {
                    topic_id: response.data.id
                });

            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });

        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                self.invalidParams = response.data.invalid_params;
            } else {
                notify.response(response);
            }
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsNewTopicController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/new-topic/:theme_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


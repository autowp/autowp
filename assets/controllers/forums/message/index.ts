import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ForumService } from 'services/forum';

const CONTROLLER_NAME = 'ForumsMessageController';
const STATE_NAME = 'forums-message';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/message/:message_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$state', 'ForumService',
        function($state: any, Forum) {
            Forum.getMessageStateParams($state.params.message_id).then(function(params: any) {
                $state.go('forums-topic', params);
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;

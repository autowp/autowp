import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import { ForumService } from 'services/forum';

const CONTROLLER_NAME = 'ForumsMessageController';
const STATE_NAME = 'forums-message';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/message/:message_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$state', 'ForumService',
        function($state, Forum) {
            var ctrl = this;
            
            Forum.getMessageStateParams($state.params.message_id).then(function(params) {
                $state.go('forums-topic', params);
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;

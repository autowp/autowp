import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'AccountInboxPicturesController';
const STATE_NAME = 'account-inbox-pictures';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/inbox-pictures?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            var ctrl = this;
            
            ctrl.pictures = [];
            ctrl.paginator = null;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/94/name',
                pageId: 94
            });
            
            $http({
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
            }).then(function(response) {
                ctrl.pictures = response.data.pictures;
                ctrl.chunks = chunkBy(ctrl.pictures, 4);
                ctrl.paginator = response.data.paginator;
            }, function(response) {
                notify.response(response);
            });
            
        }
    ]);

export default CONTROLLER_NAME;

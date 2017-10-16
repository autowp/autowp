import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerLogController';
const STATE_NAME = 'log';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/log?article_id&item_id&picture_id&user_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/75/name',
                pageId: 75
            });
            
            var ctrl = this;
            ctrl.items = [];
            $scope.paginator = null;
            
            $http({
                method: 'GET',
                url: '/api/log',
                params: {
                    article_id: $state.params.article_id,
                    item_id: $state.params.item_id,
                    picture_id: $state.params.picture_id,
                    page: $state.params.page,
                    user_id: $state.params.user_id,
                    fields: 'pictures.name_html,items.name_html,user'
                }
            }).then(function(response) {
                ctrl.items = response.data.items;
                $scope.paginator = response.data.paginator;
            }, function(response) {
                notify.response(response);
            });
        }
    ]);

export default CONTROLLER_NAME;

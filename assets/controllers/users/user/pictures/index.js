import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

require("brandicon");

import './brand';

const CONTROLLER_NAME = 'UsersUserPicturesController';
const STATE_NAME = 'users-user-pictures';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/pictures',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            var ctrl = this;
            
            function init() {
                
                ctrl.identity = ctrl.user.identity ? ctrl.user.identity : 'user' + ctrl.user.id;
                
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/63/name',
                    pageId: 63,
                    args: {
                        USER_NAME: ctrl.user.name,
                        USER_IDENTITY: ctrl.identity
                    }
                });
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 5,
                        limit: 500,
                        order: 'name_nat',
                        fields: 'name_only,catname,current_pictures_count',
                        'descendant_pictures[status]': 'accepted',
                        'descendant_pictures[owner_id]': ctrl.user.id
                    }
                }).then(function(response) {
                    ctrl.brands = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
            }
            
            var result = $state.params.identity.match(/^user([0-9]+)$/);
            
            if (result) {
                $http({
                    method: 'GET',
                    url: '/api/user/' + result[1],
                    fields: 'identity'
                }).then(function(response) {
                    ctrl.user = response.data;
                    init();
                }, function(response) {
                    notify.response(response);
                });
                
            } else {
                $http({
                    method: 'GET',
                    url: '/api/user',
                    params: {
                        identity: $state.params.identity,
                        limit: 1,
                        fields: 'identity'
                    }
                }).then(function(response) {
                    if (response.data.items.length <= 0) {
                        $state.go('error-404');
                    }
                    ctrl.user = response.data.items[0];
                    init();
                }, function(response) {
                    notify.response(response);
                });
            }

        }
    ]);

export default CONTROLLER_NAME;

import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'AccountContactsController';
const STATE_NAME = 'account-contacts';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/contacts',
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
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/198/name',
                pageId: 198
            });
            
            ctrl.items = [];
            
            $http({
                method: 'GET',
                url: '/api/contacts',
                params: {
                    fields: 'avatar,gravatar,last_online',
                }
            }).then(function(response) {
                ctrl.items = response.data.items;
                ctrl.chunks = chunkBy(ctrl.items, 2);
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.deleteContact = function(id) {
                $http({
                    method: 'DELETE',
                    url: '/api/contacts/' + id
                }).then(function() {
                    for (var i=0; i<ctrl.items.length; i++) {
                        if (ctrl.items[i].id == id) {
                            ctrl.items.splice(i, 1);
                            break;
                        }
                    }
                    ctrl.chunks = chunkBy(ctrl.items, 2);
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

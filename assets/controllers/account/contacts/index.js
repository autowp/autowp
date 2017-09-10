import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

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
        '$scope', '$http',
        function($scope, $http) {
            
            var ctrl = this;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
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
                ctrl.chunks = ctrl.chunkBy(ctrl.items, 2);
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
                    ctrl.chunks = ctrl.chunkBy(ctrl.items, 2);
                }, function(response) {
                    notify.response(response);
                });
            };

            ctrl.chunkBy = function(arr, count) {
                if (! arr) {
                    return [];
                }
                var newArr = [];
                var size = Math.ceil(count);
                for (var i=0; i<arr.length; i+=size) {
                    newArr.push(arr.slice(i, i+size));
                }

                return newArr;
            };
        }
    ]);

export default CONTROLLER_NAME;

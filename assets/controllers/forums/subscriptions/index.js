import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ForumsSubscriptionsController';
const STATE_NAME = 'forums-subscriptions';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/subscriptions?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', ACL_SERVICE_NAME,
        function($scope, $http, $state, Acl) {
            var ctrl = this;
            
            ctrl.topics = [];
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                pageId: 42
            });
            
            $http({
                url: '/api/forum/topic',
                method: 'GET',
                params: {
                    fields: 'author,messages,last_message.datetime,last_message.user',
                    subscription: 1,
                    'page': $state.params.page
                }
            }).then(function(response) {
                ctrl.topics = response.data.items;
                ctrl.paginator = response.data.paginator;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.unsubscribe = function(topic) {
                $http({
                    url: '/api/forum/topic/' + topic.id,
                    method: 'PUT',
                    data: {
                        subscription: 0
                    }
                }).then(function(response) {
                    
                    for (var i=ctrl.topics.length-1; i>=0; i--) {
                        if (ctrl.topics[i].id == topic.id) {
                            ctrl.topics.splice(i, 1);
                            break;
                        }
                    }
                    
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

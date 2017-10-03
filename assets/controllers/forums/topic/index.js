import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import FORUM_SERVICE_NAME from 'services/forum';

const CONTROLLER_NAME = 'ForumsTopicController';
const STATE_NAME = 'forums-topic';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/topic/:topic_id?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$translate', FORUM_SERVICE_NAME,
        function($scope, $http, $state, $translate, Forum) {
            var ctrl = this;
            
            ctrl.page = $state.params.page;
            ctrl.limit = Forum.getLimit();
            
            $http({
                url: '/api/forum/topic/' + $state.params.topic_id,
                method: 'GET',
                params: {
                    fields: 'author,theme,subscription',
                    'page': $state.params.page
                }
            }).then(function(response) {
                ctrl.topic = response.data;
                
                $translate(ctrl.topic.theme.name).then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            blankPage: false,
                            needRight: true
                        },
                        pageId: 44,
                        args: {
                            THEME_NAME: translation,
                            THEME_ID: ctrl.topic.theme_id,
                            TOPIC_NAME: ctrl.topic.name,
                            TOPIC_ID: ctrl.topic.id
                        }
                    });
                }, function() {
                    $scope.pageEnv({
                        layout: {
                            blankPage: false,
                            needRight: true
                        },
                        pageId: 44,
                        args: {
                            THEME_NAME: ctrl.topic.theme.name,
                            THEME_ID: ctrl.topic.theme_id,
                            TOPIC_NAME: ctrl.topic.name,
                            TOPIC_ID: ctrl.topic.id
                        }
                    });
                });
                
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.subscribe = function() {
                $http({
                    url: '/api/forum/topic/' + ctrl.topic.id,
                    method: 'PUT',
                    data: {
                        subscription: 1
                    }
                }).then(function(response) {
                    
                    ctrl.topic.subscription = true;
                    
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.unsubscribe = function() {
                $http({
                    url: '/api/forum/topic/' + ctrl.topic.id,
                    method: 'PUT',
                    data: {
                        subscription: 0
                    }
                }).then(function(response) {
                    
                    ctrl.topic.subscription = false;
                    
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

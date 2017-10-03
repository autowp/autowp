import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import ACL_SERVICE_NAME from 'services/acl';


import './move-topic';
import './new-topic';
import './subscriptions';
import './topic';

const CONTROLLER_NAME = 'ForumsController';
const STATE_NAME = 'forums';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/:theme_id?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: {
                    theme_id: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    page: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', ACL_SERVICE_NAME, '$translate',
        function($scope, $http, $state, Acl, $translate) {
            var ctrl = this;
            
            ctrl.topics = [];
            
            ctrl.forumAdmin = false;
            Acl.isAllowed('forums', 'moderate').then(function() {
                ctrl.forumAdmin = true;
            }, function() {
                ctrl.forumAdmin = false;
            });
            
            if (! $state.params.theme_id) {
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: true
                    },
                    pageId: 42
                });
                
                $http({
                    url: '/api/forum/themes',
                    method: 'GET',
                    params: {
                        fields: 'last_message.datetime,last_message.user,last_topic,description',
                        'topics[page]': $state.params.page
                    }
                }).then(function(response) {
                    ctrl.themes = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
                
            } else {
                
                $http({
                    url: '/api/forum/themes/' + $state.params.theme_id,
                    method: 'GET',
                    params: {
                        fields: 'themes.last_message.user,themes.last_message.datetime,themes.last_topic,themes.description,topics.author,topics.messages,topics.last_message.datetime,topics.last_message.user',
                        'topics[page]': $state.params.page
                    }
                }).then(function(response) {
                    
                    ctrl.theme = response.data;
                    ctrl.themes = response.data.themes;
                    ctrl.topics = response.data.topics;
                    
                    $translate(ctrl.theme.name).then(function(translation) {
                        $scope.pageEnv({
                            layout: {
                                blankPage: false,
                                needRight: true
                            },
                            pageId: 43,
                            args: {
                                THEME_NAME: translation,
                                THEME_ID:   ctrl.theme.id
                            }
                        });
                    }, function() {
                        $scope.pageEnv({
                            layout: {
                                blankPage: false,
                                needRight: true
                            },
                            pageId: 43,
                            args: {
                                THEME_NAME: ctrl.theme.name,
                                THEME_ID:   ctrl.theme.id
                            }
                        });
                    });
                    
                }, function(response) {
                    notify.response(response);
                });
            }
            
            ctrl.openTopic = function(topic) {
                $http({
                    url: '/api/forum/topic/' + topic.id,
                    method: 'PUT',
                    data: {
                        status: 'normal'
                    }
                }).then(function(response) {
                    
                    topic.status = 'normal';
                    
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.closeTopic = function(topic) {
                $http({
                    url: '/api/forum/topic/' + topic.id,
                    method: 'PUT',
                    data: {
                        status: 'closed'
                    }
                }).then(function(response) {
                    
                    topic.status = 'closed';
                    
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.deleteTopic = function(topic) {
                $http({
                    url: '/api/forum/topic/' + topic.id,
                    method: 'PUT',
                    data: {
                        status: 'deleted'
                    }
                }).then(function(response) {
                    
                    for (var i=ctrl.topics.items.length-1; i>=0; i--) {
                        if (ctrl.topics.items[i].id == topic.id) {
                            ctrl.topics.items.splice(i, 1);
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

import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'ForumsMoveTopicController';
const STATE_NAME = 'forums-move-topic';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/move-topic?topic_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                pageId: 83
            });
            
            var ctrl = this;
            
            ctrl.topic = null;
            ctrl.themes = [];
            
            $http({
                url: '/api/forum/topic/' + $state.params.topic_id,
                method: 'GET'
            }).then(function(response) {
                
                ctrl.topic = response.data;
                
            }, function(response) {
                $state.go('error-404');
            });
            
            $http({
                url: '/api/forum/themes',
                method: 'GET'
            }).then(function(response) {
                
                ctrl.themes = response.data.items;
                
            }, function(response) {
                notify.response(response);
            });

            ctrl.selectTheme = function(theme) {
                $http({
                    method: 'PUT',
                    url: '/api/forum/topic/' + ctrl.topic.id,
                    data: {
                        theme_id: theme.id
                    }
                }).then(function(response) {
                    
                    $state.go('forums-topic', {
                        topic_id: ctrl.topic.id
                    });
                    
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ForumsNewTopicController';
const STATE_NAME = 'forums-new-topic';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/new-topic/:theme_id',
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
            
            ctrl.form = {
                name: '',
                text: '',
                moderator_attention: false,
                subscription: false
            };
            
            $http({
                url: '/api/forum/themes/' + $state.params.theme_id,
                method: 'GET'
            }).then(function(response) {
                
                ctrl.theme = response.data;
                
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: true
                    },
                    pageId: 45,
                    args: {
                        THEME_NAME: ctrl.theme.name,
                        THEME_ID:   ctrl.theme.id
                    }
                });
                
            }, function(response) {
                $state.go('error-404');
            });

            ctrl.submit = function(id) {
                $http({
                    method: 'POST',
                    url: '/api/forum/topic',
                    data: {
                        theme_id: $state.params.theme_id,
                        name: ctrl.form.name,
                        text: ctrl.form.text,
                        moderator_attention: ctrl.form.moderator_attention ? 1 : 0,
                        subscription: ctrl.form.subscription ? 1 : 0
                    }
                }).then(function(response) {
                    var location = response.headers('Location');
                    
                    $http({
                        url: location,
                        method: 'GET'
                    }).then(function(response) {

                        $state.go('forums-topic', {
                            topic_id: response.data.id
                        });
                        
                    }, function(response) {
                        notify.response(response);
                    });
                    
                }, function(response) {
                    if (response.status == 400) {
                        ctrl.invalidParams = response.data.invalid_params;
                    } else {
                        notify.response(response);
                    }
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

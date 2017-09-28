import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

angular.module(Module)
    .directive('autowpComments', function() {
        return {
            restirct: 'E',
            scope: {
                itemId: '=',
                typeId: '=',
                user: '='
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ACL_SERVICE_NAME, '$http', '$scope',
                function(Acl, $http, $scope) {
                    var ctrl = this;
                    
                    ctrl.messages = [];
                    
                    ctrl.load = function() {
                        $http({
                            method: 'GET',
                            url: '/api/comment',
                            params: {
                                type_id: $scope.typeId,
                                item_id: $scope.itemId,
                                no_parents: 1,
                                fields: 'user.avatar,user.gravatar,replies,text_html,datetime,vote,user_vote',
                                order: 'date_asc'
                            }
                        }).then(function(response) {
                            ctrl.messages = response.data.items;
                        }, function(response) {
                            notify.response(response);
                        });
                    };
                    
                    ctrl.load();
                }
            ]
        };
    });
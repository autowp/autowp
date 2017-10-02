import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

angular.module(Module)
    .directive('autowpCommentsForm', function() {
        return {
            restirct: 'E',
            scope: {
                parentId: '=',
                itemId: '=',
                typeId: '=',
                onSent: '='
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ACL_SERVICE_NAME, '$scope', '$http',
                function(Acl, $scope, $http) {
                    var ctrl = this;
                    
                    ctrl.form = {
                        message: '',
                        moderator_attention: false
                    };
                    ctrl.invalidParams = {};

                    ctrl.sendMessage = function() {
                        $http({
                            method: 'POST',
                            url: '/api/comment',
                            data: {
                                type_id: $scope.typeId,
                                item_id: $scope.itemId,
                                parent_id: $scope.parentId,
                                moderator_attention: ctrl.form.moderator_attention ? 1 : 0,
                                message: ctrl.form.message
                            }
                        }).then(function(response) {
                            ctrl.form.message = '';
                            ctrl.form.moderator_attention = false;
                            
                            var location = response.headers('Location');

                            $scope.onSent(location);
                        }, function(response) {
                            if (response.status == 400) {
                                ctrl.invalidParams = response.data.invalid_params;
                            } else {
                                notify.response(response);
                            }
                        });
                    };
                }
            ]
        };
    });
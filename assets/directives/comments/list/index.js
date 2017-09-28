import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import notify from 'notify';

angular.module(Module)
    .directive('autowpCommentsList', function() {
        return {
            restirct: 'E',
            scope: {
                messages: '<',
                deep: '<',
                user: '<',
                itemId: '=',
                typeId: '=',
                onSent: '='
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ACL_SERVICE_NAME, '$http',
                function(Acl, $http) {
                    var ctrl = this;
                    
                    ctrl.canRemoveComments = false;
                    ctrl.canMoveMessage = false;
                    ctrl.showReply = false;
                    
                    Acl.isAllowed('comment', 'remove').then(function(allowed) {
                        ctrl.canRemoveComments = allowed;
                    }, function() {
                        ctrl.canRemoveComments = false;
                    });
                    
                    ctrl.vote = function(message, value) {
                        $http({
                            method: 'PUT',
                            url: '/api/comment/' + message.id,
                            data: {
                                user_vote: value
                            }
                        }).then(function() {
                            
                            $.getJSON('/api/comment/' + message.id, {fields: 'vote'}, function(response) {
                                message.vote = response.data.vote;
                                message.user_vote = value;
                            });
                            
                            ga('send', 'event', 'comment-vote', value > 0 ? 'like' : 'dislike');
                            
                        }, function(response) {
                            if (response.status == 400) {
                                angular.forEach(response.data.invalid_params, function(field) {
                                    angular.forEach(field, function(message) {
                                        notify({
                                            icon: 'fa fa-exclamation-triangle',
                                            message: message
                                        }, {
                                            type: 'warning'
                                        });
                                    });
                                });
                                
                            } else {
                                notify.response(response);
                            }
                        });
                    };
                    
                    ctrl.setIsDeleted = function(message, value) {
                        $http({
                            url: '/api/comment/' + message.id,
                            method: 'PUT',
                            data: {
                                deleted: value
                            }
                        }).then(function() {
                            message.deleted = value;
                        });
                    };
                    
                    ctrl.reply = function(message, resolve) {
                        if (message.showReply) {
                            message.showReply = false;
                        } else {
                            message.showReply = true;
                        }
                    };
                }
            ]
        };
    });
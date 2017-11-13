import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import { AclService } from 'services/acl';
import notify from 'notify';
import $ from 'jquery';

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
            controller: ['AclService', '$http',
                function(Acl, $http) {
                    var ctrl = this;
                    
                    ctrl.canRemoveComments = false;
                    ctrl.canMoveMessage = false;
                    ctrl.showReply = false;
                    ctrl.canMoveMessage = false;
                    
                    Acl.isAllowed('comment', 'remove').then(function(allowed) {
                        ctrl.canRemoveComments = allowed;
                    }, function() {
                        ctrl.canRemoveComments = false;
                    });
                    
                    Acl.isAllowed('forums', 'moderate').then(function(allowed) {
                        ctrl.canMoveMessage = allowed;
                    }, function() {
                        ctrl.canMoveMessage = false;
                    });
                    
                    ctrl.vote = function(message, value) {
                        $http({
                            method: 'PUT',
                            url: '/api/comment/' + message.id,
                            data: {
                                user_vote: value
                            }
                        }).then(function() {
                            
                            message.user_vote = value;
                            
                            $http({
                                method: 'GET',
                                url: '/api/comment/' + message.id, 
                                params: {fields: 'vote'}
                            }).then(function(response) {
                                message.vote = response.data.vote;
                            }, function(response) {
                                notify.response(response);
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
                        }, function(response) {
                            notify.response(response);
                        });
                    };
                    
                    ctrl.reply = function(message, resolve) {
                        if (message.showReply) {
                            message.showReply = false;
                        } else {
                            message.showReply = true;
                        }
                    };
                    
                    ctrl.showVotes = function(message) {
                        var $modal = $(require('./votes.html'));
                        
                        var $body = $modal.find('.modal-body');
                        
                        $modal.modal();
                        $modal.on('hidden.bs.modal', function() {
                            $modal.remove();
                        });
                        
                        var $btnClose = $modal.find('.btn-default');
                        
                        $btnClose.button('loading');
                        $http({
                            url: '/comments/votes', 
                            params: {id: message.id}
                        }).then(function(response) {
                            $body.html(response.data);
                            $btnClose.button('reset');
                        }, function(response) {
                            notify.response(response);
                        });
                    };
                }
            ]
        };
    });
import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import notify from 'notify';
import * as $ from 'jquery';

interface IAutowpCommentsListDirectiveScope extends ng.IScope {
    onSent: Function;
    typeId: number;
    itemId: number;
    parentId: number|null;
}

class AutowpCommentsListDirectiveController {

    public canRemoveComments: boolean = false;
    public canMoveMessage: boolean = false;
    public showReply: boolean = false;

    static $inject = ['$scope', 'AclService', '$http'];
    constructor(
        protected $scope: IAutowpCommentsListDirectiveScope, 
        private AclService: AclService,
        private $http: ng.IHttpService
    ) {
        var self = this;
        
        this.AclService.isAllowed('comment', 'remove').then(function(allowed) {
            self.canRemoveComments = allowed;
        }, function() {
            self.canRemoveComments = false;
        });
        
        this.AclService.isAllowed('forums', 'moderate').then(function(allowed) {
            self.canMoveMessage = allowed;
        }, function() {
            self.canMoveMessage = false;
        });
    }
    
    public vote(message: any, value: number) {
        var self = this;
        this.$http({
            method: 'PUT',
            url: '/api/comment/' + message.id,
            data: {
                user_vote: value
            }
        }).then(function() {
            
            message.user_vote = value;
            
            self.$http({
                method: 'GET',
                url: '/api/comment/' + message.id, 
                params: {fields: 'vote'}
            }).then(function(response: ng.IHttpResponse<any>) {
                message.vote = response.data.vote;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            ga('send', 'event', 'comment-vote', value > 0 ? 'like' : 'dislike');
            
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 400) {
                angular.forEach(response.data.invalid_params, function(field) {
                    angular.forEach(field, function(message) {
                        new notify({
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
    }
    
    public setIsDeleted(message: any, value: any) {
        this.$http({
            url: '/api/comment/' + message.id,
            method: 'PUT',
            data: {
                deleted: value
            }
        }).then(function() {
            message.deleted = value;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public reply(message: any, resolve: any) {
        if (message.showReply) {
            message.showReply = false;
        } else {
            message.showReply = true;
        }
    }

    public showVotes(message: any) {
        var $modal = $(require('./votes.html'));
        
        var $body = $modal.find('.modal-body');
        
        $modal.modal();
        $modal.on('hidden.bs.modal', function() {
            $modal.remove();
        });
        
        var $btnClose = $modal.find('.btn-secondary');
        
        $btnClose.button('loading');
        this.$http({
            url: '/comments/votes',
            method: 'GET',
            params: {
                id: message.id
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            $body.html(response.data);
            $btnClose.button('reset');
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

class AutowpCommentsListDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
        messages: '<',
        deep: '<',
        user: '<',
        itemId: '=',
        typeId: '=',
        onSent: '='
    };
    public template = require('./template.html');
    public controller = AutowpCommentsListDirectiveController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpCommentsListDirective();
    }
}

angular.module(Module).directive('autowpCommentsList', AutowpCommentsListDirective.factory());

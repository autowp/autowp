import * as angular from 'angular';
import Module from 'app.module';
import { MessageService } from 'services/message';
import { ForumService } from 'services/forum';
import notify from 'notify';
import { PageService } from 'services/page';

interface IAutowpAccountSidebarDirectiveScope extends ng.IScope {
    user: any;
}

class AutowpAccountSidebarDirectiveController {
    static $inject = ['$scope', '$state', 'MessageService', '$http', 'ForumService', 'PageService'];
    
    public items: any[];
    
    constructor(
        protected $scope: IAutowpAccountSidebarDirectiveScope,
        private $state: any, 
        private MessageService: MessageService, 
        private $http: ng.IHttpService,
        private ForumService: ForumService, 
        private PageService: PageService
    ) {
        var self = this;
        
        if (! this.$scope.user) {
            return;
        }
        
        this.items = [
            {
                pageId: 129,
                url: this.$state.href('account-profile', {}, {inherit: false}),
                icon: 'user',
                name: 'page/129/name'
            },
            {
                pageId: 198,
                url: this.$state.href('account-contacts', {}, {inherit: false}),
                icon: 'address-book',
                name: 'page/198/name'
            },
            {
                pageId: 55,
                url: this.$state.href('account-email', {}, {inherit: false}),
                icon: 'envelope-o',
                name: 'page/55/name'
            },
            {
                pageId: 133,
                url: this.$state.href('account-access', {}, {inherit: false}),
                icon: 'lock',
                name: 'page/133/name'
            },
            {
                pageId: 123,
                url: this.$state.href('account-accounts', {}, {inherit: false}),
                icon: 'asterisk',
                name: 'page/123/name'
            },
            {
                pageId: 130,
                url: this.$state.href('users-user-pictures', {identity: $scope.user.identity ? $scope.user.identity : 'user' + $scope.user.id}, {inherit: false}),
                icon: 'th',
                name: 'page/130/name'
            },
            {
                pageId: 94,
                url: this.$state.href('account-inbox-pictures', {}, {inherit: false}),
                icon: 'th',
                name: 'page/94/name'
            },
            {
                pageId: 57,
                url: this.$state.href('forums-subscriptions', {}, {inherit: false}),
                icon: 'bookmark',
                name: 'page/57/name'
            },
            {
                name: 'catalogue/specifications',
            },
            {
                pageId: 188,
                url: this.$state.href('account-specs-conflicts', {}, {inherit: false}),
                icon: 'exclamation-triangle',
                name: 'page/188/name'
            },
            {
                name: 'page/49/name'
            },
            {
                pageId: 128,
                url: this.$state.href('account-messages', {}, {inherit: false}),
                icon: 'comments-o',
                name: 'page/128/name'
            },
            {
                pageId: 80,
                url: this.$state.href('account-messages', {folder: 'sent'}, {inherit: false}),
                icon: 'comments-o',
                name: 'page/80/name'
            },
            {
                pageId: 81,
                url: this.$state.href('account-messages', {folder: 'system'}, {inherit: false}),
                icon: 'comments',
                name: 'page/81/name'
            }
        ];
        
        function loadMessageSummary() {
            MessageService.getSummary().then(function(data: any) {
                
                self.items[11].count = data.inbox.count;
                self.items[11].newCount = data.inbox.new_count;
                
                self.items[12].count = data.sent.count;
                
                self.items[13].count = data.system.count;
                self.items[13].newCount = data.system.new_count;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
        
        loadMessageSummary();
        
        ForumService.getUserSummary().then(function(data: any) {
            
            self.items[7].count = data.subscriptionsCount;

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        $http({
            method: 'GET',
            url: '/api/picture/user-summary'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items[6].count = response.data.inboxCount;
            self.items[5].count = response.data.acceptedCount;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });

        angular.forEach(self.items, function(item) {
            if (item.pageId) {
                PageService.isActive(item.pageId).then(function(active: boolean) {
                    item.active = active;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
        });
        
        var handler = function() {
            loadMessageSummary();
        };
        
        MessageService.bind('sent', handler);
        MessageService.bind('deleted', handler);
        
        $scope.$on('$destroy', function () {
            MessageService.unbind('sent', handler);
            MessageService.unbind('deleted', handler);
        });
    }
}

class AutowpAccountSidebarDirective implements ng.IDirective {
    public controllerAs = 'ctrl';
    public restrict = 'E';
    public scope = {
            user: '<'
    };
    public template = require('./template.html');
    public controller = AutowpAccountSidebarDirectiveController;
    public bindToController: true;

    static factory(): ng.IDirectiveFactory {
        return () => new AutowpAccountSidebarDirective();
    }
}

angular.module(Module).directive('autowpAccountSidebar', AutowpAccountSidebarDirective.factory());

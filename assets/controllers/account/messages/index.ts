import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { MessageService } from 'services/message';
import { MessageDialogService } from 'services/message-dialog';

const CONTROLLER_NAME = 'AccountMessagesController';
const STATE_NAME = 'account-messages';

export class AccountMessagesController {
    static $inject = ['$scope', '$rootScope', '$http', '$state', 'MessageService', 'MessageDialogService'];
  
    public folder: string;
    public items: any = [];
    public paginator: autowp.IPaginator | null;
    private userId: number = 0;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $rootScope: autowp.IRootControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private MessageService: MessageService, 
        private MessageDialogService: MessageDialogService
    ) {
        var self = this;
        
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.folder = this.$state.params.folder || 'inbox';
        var pageId = null;
        var pageName = null;
        
        switch (this.folder) {
            case 'inbox':
                pageId = 128;
                pageName = 'page/128/name';
                break;
            case 'sent':
                pageId = 80;
                pageName = 'page/80/name';
                break;
            case 'system':
                pageId = 81;
                pageName = 'page/81/name';
                break;
            case 'dialog':
                pageId = 49;
                pageName = 'page/49/name';
                this.userId = this.$state.params.user_id;
                break;
        }
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: pageName,
            pageId: pageId
        });
        
        this.load();
    }
    
    private load() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/message',
            params: {
                folder: this.folder,
                page: this.$state.params.page,
                fields: 'author.avatar,author.gravatar',
                user_id: this.userId ? this.userId : 0
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.paginator = response.data.paginator;
            
            var newFound = false;
            angular.forEach(self.items, function(message: any) {
                if (message.is_new) {
                    newFound = true;
                }
            });
            
            if (newFound) {
                self.$rootScope.refreshNewMessagesCount();
            }
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public deleteMessage(id: number) {
        var self = this;
        this.MessageService.deleteMessage(id).then(function(response: any) {
            for (var i=0; i<self.items.length; i++) {
                if (self.items[i].id == id) {
                    self.items.splice(i, 1);
                    break;
                }
            }
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }

    public clearFolder(folder: string) {
        var self = this;
        this.MessageService.clearFolder(folder).then(function() {
            if (self.folder == folder) {
                self.items = [];
                self.paginator = null;
            }
            
            self.$rootScope.refreshNewMessagesCount();
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public openMessageForm(userId: number) {
        var self = this;
        this.MessageDialogService.showDialog(userId, function() {
            switch (self.folder) {
                case 'sent':
                case 'dialog':
                    self.load();
                    break;
            }
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountMessagesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/messages?folder&user_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


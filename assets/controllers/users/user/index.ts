import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ContactsService } from 'services/contacts';
import { MessageDialogService } from 'services/message-dialog';
import { AclService } from 'services/acl';

import './comments';
import './pictures';

const CONTROLLER_NAME = 'UsersUserController';
const STATE_NAME = 'users-user';

export class UsersUserController {
    static $inject = ['$scope', '$http', '$state', 'ContactsService', 'MessageDialogService', 'AclService'];
    public user: any;
    public banPeriods = {
        1: 'ban/period/hour',
        2: 'ban/period/2-hours',
        4: 'ban/period/4-hours',
        8: 'ban/period/8-hours',
        16: 'ban/period/16-hours',
        24: 'ban/period/day',
        48: 'ban/period/2-days'
    };
    public banPeriod: number = 1;
    public banReason: string|null = null;
    public ip: any;
    public inContacts: boolean = false;
    public comments: any[];
    public pictures: any[];
    public canDeleteUser: boolean = false;
    public isMe: boolean = false;
    public canBeInContacts: boolean = false;
    public canViewIp: boolean = false;
    public canBan: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any, 
        private Contacts: ContactsService,
        private MessageDialogService: MessageDialogService,
        private Acl: AclService
    ) {
        var self = this;
        
        var result = this.$state.params.identity.match(/^user([0-9]+)$/);
        
        var fields = 'identity,gravatar_hash,photo,renames,is_moder,reg_date,last_online,accounts,pictures_added,pictures_accepted_count,last_ip';
        
        if (result) {
            $http({
                method: 'GET',
                url: '/api/user/' + result[1],
                params: {
                    fields: fields
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.user = response.data;
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        } else {
            $http({
                method: 'GET',
                url: '/api/user',
                params: {
                    identity: $state.params.identity,
                    limit: 1,
                    fields: fields
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                if (response.data.items.length <= 0) {
                    $state.go('error-404');
                    return;
                }
                self.user = response.data.items[0];
                self.init();
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
    }
  
    public init() {
        if (this.user.deleted) {
            this.$state.go('error-404');
            return;
        }
    
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/62/name',
            pageId: 62,
            args: {
                USER_NAME: this.user.name,
                USER_IDENTITY: this.user.identity ? this.user.identity : 'user' + this.user.id
            }
        });
      
        var self = this;
        
        this.Acl.isAllowed('user', 'ip').then(function(allow: boolean) {
            self.canViewIp = !!allow;
        }, function() {
            self.canViewIp = false;
        });
                
        this.Acl.isAllowed('user', 'ban').then(function(allow: boolean) {
            self.canBan = !!allow;
        }, function() {
            self.canBan = false;
        });
        
        this.isMe = this.$scope.user && (this.$scope.user.id == this.user.id);
        this.canBeInContacts = this.$scope.user && ! this.user.deleted && ! this.isMe ;
        
        if (this.$scope.user && ! this.isMe) {
            this.Contacts.isInContacts(this.user.id).then(function(inContacts: boolean) {
                self.inContacts = inContacts;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
                
        this.Acl.isAllowed('user', 'delete').then(function(allow: boolean) {
            self.canDeleteUser = !!allow;
        }, function() {
            self.canDeleteUser = false;
        });
        
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: {
                owner_id: this.user.id,
                limit: 12,
                order: 1,
                fields: 'url,name_html'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pictures = response.data.pictures;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
 
        if (! this.user.deleted) {
            this.$http({
                method: 'GET',
                url: '/api/comment',
                params: {
                    user_id: this.user.id,
                    limit: 15,
                    order: 'date_desc',
                    fields: 'preview,url'
                }
            }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                self.comments = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
        
        if (this.user.last_ip) {
            this.loadBan(this.user.last_ip);
        }
    }
  
    private loadBan(ip: string) {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/ip/' + ip,
            params: {
                fields: 'blacklist,rights'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.ip = response.data;
        }, function(response: ng.IHttpResponse<any>) {
            if (response.status == 404) {
                self.ip = null;
            } else {
                notify.response(response);
            }
        });
    }
  
    public openMessageForm() {
        this.MessageDialogService.showDialog(this.user.id);
    }
    
    public toggleInContacts() {
      
        var self = this;
      
        this.$http({
            method: this.inContacts ? 'DELETE' : 'PUT',
            url: '/api/contacts/' + this.user.id
        }).then(function(response: ng.IHttpResponse<any>) {
            switch (response.status) {
                case 204:
                    self.inContacts = false;
                    break;
                case 200:
                    self.inContacts = true;
                    break;
            }
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public deletePhoto() {
        if (! window.confirm("Are you sure?")) {
            return;
        }
      
        var self = this;
        
        this.$http({
            method: 'DELETE',
            url: '/api/user/' + this.user.id + '/photo'
        }).then(function(response: ng.IHttpResponse<any>) {
            self.user.photo = null;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public deleteUser() {
        if (! window.confirm("Are you sure?")) {
            return;
        }
        var self = this;
        this.$http({
            method: 'PUT',
            url: '/api/user/' + this.user.id,
            data: {
                deleted: true
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.user.deleted = true;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public unban() {
        this.$http({
            method: 'DELETE',
            url: '/api/traffic/blacklist/' + this.ip.address
        }).then(function(response: ng.IHttpResponse<any>) {
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public removeFromBlacklist() {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/traffic/blacklist/' + this.ip.address
        }).then(function(response: ng.IHttpResponse<any>) {
            self.ip.blacklist = null;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public addToBlacklist() {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/traffic/blacklist',
            data: {
                ip: this.ip.address,
                period: this.banPeriod,
                reason: this.banReason
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.loadBan(self.user.last_ip);
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UsersUserController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import CONTACTS_SERVICE_NAME from 'services/contacts';
import MessageDialog from 'message';
import MessageServiceName from 'services/message';
import ACL_SERVICE_NAME from 'services/acl';

import './comments';
import './pictures';

const CONTROLLER_NAME = 'UsersUserController';
const STATE_NAME = 'users-user';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', CONTACTS_SERVICE_NAME, MessageServiceName, ACL_SERVICE_NAME,
        function($scope, $http, $state, Contacts, MessageService, Acl) {
            
            var ctrl = this;
            
            ctrl.banPeriods = {
                1: 'ban/period/hour',
                2: 'ban/period/2-hours',
                4: 'ban/period/4-hours',
                8: 'ban/period/8-hours',
                16: 'ban/period/16-hours',
                24: 'ban/period/day',
                48: 'ban/period/2-days'
            };
            ctrl.banPeriod = 1;
            ctrl.banReason = null;
            
            function loadBan(ip) {
                $http({
                    method: 'GET',
                    url: '/api/ip/' + ip,
                    params: {
                        fields: 'blacklist,rights'
                    }
                }).then(function(response) {
                    ctrl.ip = response.data;
                }, function(response) {
                    if (response.status == 404) {
                        ctrl.ip = null;
                    } else {
                        notify.response(response);
                    }
                });
            }
            
            function init() {
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/62/name',
                    pageId: 62,
                    args: {
                        USER_NAME: ctrl.user.name,
                        USER_IDENTITY: ctrl.user.identity ? ctrl.user.identity : 'user' + ctrl.user.id
                    }
                });
                
                ctrl.canViewIp = false;
                Acl.isAllowed('user', 'ip').then(function() {
                    ctrl.canViewIp = true;
                }, function() {
                    ctrl.canViewIp = false;
                });
                
                ctrl.canBan = false;
                Acl.isAllowed('user', 'ban').then(function() {
                    ctrl.canBan = true;
                }, function() {
                    ctrl.canBan = false;
                });
                
                ctrl.isMe = $scope.user && ($scope.user.id == ctrl.user.id);
                ctrl.canBeInContacts = $scope.user && ! ctrl.user.deleted && ! ctrl.isMe ;
                ctrl.inContacts = false;
                if ($scope.user && ! ctrl.isMe) {
                    Contacts.isInContacts(ctrl.user.id).then(function(inContacts) {
                        ctrl.inContacts = inContacts;
                    }, function(response) {
                        notify.response(response);
                    });
                }
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        owner_id: ctrl.user.id,
                        limit: 12,
                        order: 1,
                        fields: 'url,name_html'
                    }
                }).then(function(response) {
                    ctrl.pictures = response.data.pictures;
                }, function(response) {
                    notify.response(response);
                });
 
                $http({
                    method: 'GET',
                    url: '/api/comment',
                    params: {
                        user_id: ctrl.user.id,
                        limit: 15,
                        order: 'date_desc',
                        fields: 'preview,url'
                    }
                }).then(function(response) {
                    ctrl.comments = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
                
                if (ctrl.user.last_ip) {
                    loadBan(ctrl.user.last_ip);
                }
            }
            
            var result = $state.params.identity.match(/^user([0-9]+)$/);
            
            var fields = 'identity,gravatar_hash,photo,renames,is_moder,reg_date,last_online,accounts,pictures_added,pictures_accepted_count,last_ip';
            
            if (result) {
                $http({
                    method: 'GET',
                    url: '/api/user/' + result[1],
                    params: {
                        fields: fields
                    }
                }).then(function(response) {
                    ctrl.user = response.data;
                    init();
                }, function(response) {
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
                }).then(function(response) {
                    if (response.data.items.length <= 0) {
                        $state.go('error-404');
                    }
                    ctrl.user = response.data.items[0];
                    init();
                }, function(response) {
                    notify.response(response);
                });
            }

            ctrl.openMessageForm = function() {
                MessageDialog.showDialog(MessageService, ctrl.user.id);
            };
            
            ctrl.toggleInContacts = function() {
                $http({
                    method: ctrl.inContacts ? 'DELETE' : 'PUT',
                    url: '/api/contacts/' + ctrl.user.id
                }).then(function(response) {
                    switch (response.status) {
                        case 204:
                            ctrl.inContacts = false;
                            break;
                        case 200:
                            ctrl.inContacts = true;
                            break;
                    }
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.deletePhoto = function() {
                if (! window.confirm("Are you sure?")) {
                    return;
                }
                
                $http({
                    method: 'DELETE',
                    url: '/api/user/' + ctrl.user.id + '/photo'
                }).then(function(response) {
                    ctrl.user.photo = null;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.deleteUser = function() {
                if (! window.confirm("Are you sure?")) {
                    return;
                }
                
                $http({
                    method: 'PUT',
                    url: '/api/user/' + ctrl.user.id,
                    data: {
                        deleted: true
                    }
                }).then(function(response) {
                    ctrl.user.deleted = true;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.unban = function() {
                $http({
                    method: 'DELETE',
                    url: '/api/traffic/blacklist/' + ctrl.ip.address
                }).then(function(response) {
                    
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.removeFromBlacklist = function() {
                $http({
                    method: 'DELETE',
                    url: '/api/traffic/blacklist/' + ctrl.ip.address
                }).then(function(response) {
                    ctrl.ip.blacklist = null;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.addToBlacklist = function() {
                $http({
                    method: 'POST',
                    url: '/api/traffic/blacklist',
                    data: {
                        ip: ctrl.ip.address,
                        period: ctrl.banPeriod,
                        reason: ctrl.banReason
                    }
                }).then(function(response) {
                    loadBan(ctrl.user.last_ip);
                }, function(response) {
                    notify.response(response);
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

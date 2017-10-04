import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import MessageServiceName from 'services/message';
import ForumServiceName from 'services/forum';
import notify from 'notify';
import PageServiceName from 'services/page';

angular.module(Module)
    .directive('autowpAccountSidebar', function() {
        return {
            restirct: 'E',
            scope: {
                user: '<'
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ '$state', MessageServiceName, '$http', ForumServiceName, PageServiceName, '$scope',
                function($state, MessageService, $http, ForumService, PageService, $scope) {
                    var ctrl = this;
                    
                    if (! $scope.user) {
                        return;
                    }
                    
                    ctrl.items = [
                        {
                            pageId: 129,
                            url   : $state.href('account-profile', {}, {inherit: false}),
                            icon: 'user',
                            name: 'page/129/name'
                        },
                        {
                            pageId: 198,
                            url: $state.href('account-contacts', {}, {inherit: false}),
                            icon: 'address-book',
                            name: 'page/198/name'
                        },
                        {
                            pageId: 55,
                            url: '/account/email',
                            icon: 'envelope-o',
                            name: 'page/55/name'
                        },
                        {
                            pageId: 133,
                            url: '/account/access',
                            icon: 'lock',
                            name: 'page/133/name'
                        },
                        {
                            pageId: 123,
                            url: '/account/accounts',
                            icon: 'asterisk',
                            name: 'page/123/name'
                        },
                        {
                            pageId: 130,
                            url: '#',
                            icon: 'th',
                            name: 'page/130/name'
                        },
                        {
                            pageId: 94,
                            url: '/account/not-taken-pictures',
                            icon: 'th',
                            name: 'page/94/name'
                        },
                        {
                            pageId: 57,
                            url: $state.href('forums-subscriptions', {}, {inherit: false}),
                            icon: 'bookmark',
                            name: 'page/57/name'
                        },
                        {
                            name: 'catalogue/specifications',
                        },
                        {
                            pageId: 188,
                            url: '/account/specs-conflicts',
                            icon: 'exclamation-triangle',
                            name: 'page/188/name'
                        },
                        {
                            name: 'page/49/name'
                        },
                        {
                            pageId: 128,
                            url: $state.href('account-messages', {}, {inherit: false}),
                            icon: 'comments-o',
                            name: 'page/128/name'
                        },
                        {
                            pageId: 80,
                            url: $state.href('account-messages', {folder: 'sent'}, {inherit: false}),
                            icon: 'comments-o',
                            name: 'page/80/name'
                        },
                        {
                            pageId: 81,
                            url: $state.href('account-messages', {folder: 'system'}, {inherit: false}),
                            icon: 'comments',
                            name: 'page/81/name'
                        }
                    ];
                    
                    function loadMessageSummary() {
                        MessageService.getSummary().then(function(data) {
                            
                            ctrl.items[11].count = data.inbox.count;
                            ctrl.items[11].newCount = data.inbox.new_count;
                            
                            ctrl.items[12].count = data.sent.count;
                            
                            ctrl.items[13].count = data.system.count;
                            ctrl.items[13].newCount = data.system.new_count;
                        }, function(response) {
                            notify.response(response);
                        });
                    }
                    
                    loadMessageSummary();
                    
                    ForumService.getUserSummary().then(function(data) {
                        
                        ctrl.items[7].count = data.subscriptionsCount;

                    }, function(response) {
                        notify.response(response);
                    });
                    
                    $http({
                        method: 'GET',
                        url: '/api/picture/user-summary'
                    }).then(function(response) {
                        ctrl.items[6].count = response.data.inboxCount;
                        ctrl.items[5].count = response.data.acceptedCount;
                        ctrl.items[5].url = response.data.url;
                    }, function(response) {
                        notify.response(response);
                    });

                    angular.forEach(ctrl.items, function(item) {
                        if (item.pageId) {
                            PageService.isActive(item.pageId).then(function(active) {
                                item.active = active;
                            }, function(response) {
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
            ]
        };
    });

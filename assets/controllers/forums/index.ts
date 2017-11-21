import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { AclService } from 'services/acl';

import './message';
import './move-message';
import './move-topic';
import './new-topic';
import './subscriptions';
import './topic';

const CONTROLLER_NAME = 'ForumsController';
const STATE_NAME = 'forums';

export class ForumsController {
    static $inject = ['$scope', '$http', '$state', 'AclService', '$translate'];
    public paginator: autowp.IPaginator;
    public topics: any = [];
    public forumAdmin: boolean = false;
    public theme: any;
    public themes: any[];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any, 
        private Acl: AclService, 
        private $translate: any
    ) {
        var self = this;
            
        Acl.isAllowed('forums', 'moderate').then(function(allow: boolean) {
            self.forumAdmin = !!allow;
        }, function() {
            self.forumAdmin = false;
        });
        
        if (! $state.params.theme_id) {
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/42/name',
                pageId: 42
            });
            
            self.$http({
                url: '/api/forum/themes',
                method: 'GET',
                params: {
                    fields: 'last_message.datetime,last_message.user,last_topic,description',
                    'topics[page]': self.$state.params.page
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.themes = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        } else {
            
            self.$http({
                url: '/api/forum/themes/' + self.$state.params.theme_id,
                method: 'GET',
                params: {
                    fields: 'themes.last_message.user,themes.last_message.datetime,themes.last_topic,themes.description,topics.author,topics.messages,topics.last_message.datetime,topics.last_message.user',
                    'topics[page]': self.$state.params.page
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                
                self.theme = response.data;
                self.themes = response.data.themes;
                self.topics = response.data.topics;
                
                self.$translate(self.theme.name).then(function(translation: string) {
                    $scope.pageEnv({
                        layout: {
                            blankPage: false,
                            needRight: true
                        },
                        name: 'page/43/name',
                        pageId: 43,
                        args: {
                            THEME_NAME: translation,
                            THEME_ID:   self.theme.id
                        }
                    });
                }, function() {
                    $scope.pageEnv({
                        layout: {
                            blankPage: false,
                            needRight: true
                        },
                        name: 'page/43/name',
                        pageId: 43,
                        args: {
                            THEME_NAME: self.theme.name,
                            THEME_ID:   self.theme.id
                        }
                    });
                });
                
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
                
                self.$state.go('error-404');
            });
        }
    }
  
    public openTopic(topic: any) {
        this.$http({
            url: '/api/forum/topic/' + topic.id,
            method: 'PUT',
            data: {
                status: 'normal'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            topic.status = 'normal';
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public closeTopic(topic: any) {
        this.$http({
            url: '/api/forum/topic/' + topic.id,
            method: 'PUT',
            data: {
                status: 'closed'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            topic.status = 'closed';
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public deleteTopic(topic: any) {
      
        var self = this;
      
        this.$http({
            url: '/api/forum/topic/' + topic.id,
            method: 'PUT',
            data: {
                status: 'deleted'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            for (var i=self.topics.items.length-1; i>=0; i--) {
                if (self.topics.items[i].id == topic.id) {
                    self.topics.items.splice(i, 1);
                    break;
                }
            }
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ForumsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/forums/:theme_id?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    theme_id: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    page: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ]);


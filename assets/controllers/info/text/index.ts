import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { UserService } from 'services/user';
import * as $ from 'jquery';

var JsDiff = require('diff');

const CONTROLLER_NAME = 'InfoTextController';
const STATE_NAME = 'info-text';

export class InfoTextController {
    static $inject = ['$scope', '$http', '$state', 'UserService'];

    public prev: any;
    public current: any;
    public next: any;
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any, 
        private UserService: UserService
    ) {
        var self = this;
            
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/197/name',
            pageId: 197
        });
        
        this.$http({
            method: 'GET',
            url: '/api/text/' + this.$state.params.id,
            params: {
                revision: this.$state.params.revision
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.current = response.data.current;
            self.prev = response.data.prev;
            self.next = response.data.next;
            
            if (self.current.user_id) {
                self.UserService.getUser(self.current.user_id).then(function(user: any) {
                    self.current.user = user;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
            
            if (self.prev.user_id) {
                self.UserService.getUser(self.prev.user_id).then(function(user: any) {
                    self.prev.user = user;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
            
            if (self.prev.text) {
                doDiff();
            }
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
        
        function doDiff() {
            
            var diff = JsDiff.diffChars(self.prev.text, self.current.text);
            
            var fragment = document.createDocumentFragment();
            for (var i=0; i < diff.length; i++) {

                if (diff[i].added && diff[i + 1] && diff[i + 1].removed) {
                    var swap = diff[i];
                    diff[i] = diff[i + 1];
                    diff[i + 1] = swap;
                }

                var node;
                if (diff[i].removed) {
                    node = document.createElement('del');
                    node.appendChild(document.createTextNode(diff[i].value));
                } else if (diff[i].added) {
                    node = document.createElement('ins');
                    node.appendChild(document.createTextNode(diff[i].value));
                } else {
                    node = document.createTextNode(diff[i].value);
                }
                fragment.appendChild(node);
            }

            $('pre').eq(1).empty().append(fragment);
        }
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, InfoTextController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/info/text/:id?revision',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'AccountContactsController';
const STATE_NAME = 'account-contacts';

export class AccountContactsController {
    static $inject = ['$scope', '$http', '$state'];
  
    public items: any[] = [];
    public chunks: any[];
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any
    ) {
        if (! this.$scope.user) {
            this.$state.go('login');
            return;
        }
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/198/name',
            pageId: 198
        });
        
        var self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/contacts',
            params: {
                fields: 'avatar,gravatar,last_online',
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.chunks = chunkBy(self.items, 2);
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
  
    public deleteContact(id: number) {
        var self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/contacts/' + id
        }).then(function() {
            for (var i=0; i<self.items.length; i++) {
                if (self.items[i].id == id) {
                    self.items.splice(i, 1);
                    break;
                }
            }
            self.chunks = chunkBy(self.items, 2);
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, AccountContactsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/account/contacts',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


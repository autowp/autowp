import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'CarsSpecsAdminController';
const STATE_NAME = 'cars-specs-admin';

export class CarsSpecsAdminController {
    static $inject = ['$scope', '$http', '$state', '$element'];
  
    public values: any[] = [];
    public paginator: autowp.IPaginator;
    public move = {
        item_id: null
    };
  
    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService, 
        private $state: any,
        private $element: any
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/103/name',
            pageId: 103
        });
        
        this.load();
    }
    
    private load()
    {
        let self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/attr/user-value',
            params: {
                item_id: this.$state.params.item_id,
                fields: 'user,path,unit'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.values = response.data.items;
            self.paginator = response.data.paginator;
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public deleteValue(value: any)
    {
        let self = this;
        this.$http({
            method: 'DELETE',
            url: '/api/attr/user-value/' + value.attribute_id + '/' + value.item_id + '/' + value.user_id
        }).then(function(response: ng.IHttpResponse<any>) {
            
            for (let i=0; i < self.values.length; i++) {
                if (self.values[i] === value) {
                    self.values.splice(i, 1);
                    break;
                }
            }
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
    public moveValues()
    {
        let self = this;
        this.$http({
            method: 'PATCH',
            url: '/api/attr/user-value',
            params: {
                item_id: this.$state.params.item_id
            },
            data: {
                item_id: this.move.item_id
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.load();
            
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CarsSpecsAdminController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/specs-admin?item_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


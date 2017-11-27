import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import "corejs-typeahead";
import * as $ from 'jquery';

const CONTROLLER_NAME = 'CarsSpecsAdminController';
const STATE_NAME = 'cars-specs-admin';

export class CarsSpecsAdminController {
    static $inject = ['$scope', '$http', '$state', '$element'];
  
    public values: any[] = [];
    public paginator: autowp.IPaginator;
  
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
        
        let self = this;
        
        this.$http({
            method: 'GET',
            url: '/api/user-value',
            params: {
                item_id: $state.params.item_id,
                fields: 'user'
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.values = response.data.items;
            
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


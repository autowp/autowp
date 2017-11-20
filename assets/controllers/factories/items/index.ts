import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';

const CONTROLLER_NAME = 'FactoryItemsController';
const STATE_NAME = 'factory-items';

function chunkBy(arr: any[], count: number): any[] {
    var newArr = [];
    var size = Math.ceil(count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}

export class FactoryItemsController {
    static $inject = ['$scope', '$http', '$state'];
    public factory: any;
    public pictures: any[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any
    ) {
      
        var self = this;
      
        $http({
            method: 'GET',
            url: '/api/item/' + this.$state.params.id,
            params: {
                fields: ['name_text', 'name_html', 'lat', 'lng', 'description'].join(',')
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            
            self.factory = response.data;
            
            if (self.factory.item_type_id != 6) {
                self.$state.go('error-404');
                return;
            }
  
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/182/name',
                pageId: 182,
                args: {
                    FACTORY_ID: self.factory.id,
                    FACTORY_NAME: self.factory.name_text
                }
            });
          
            
        }, function(response: ng.IHttpResponse<any>) {
            self.$state.go('error-404');
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, FactoryItemsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/factories/:id/items',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


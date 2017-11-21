import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

const CONTROLLER_NAME = 'FactoryItemsController';
const STATE_NAME = 'factory-items';

export class FactoryItemsController {
    static $inject = ['$scope', '$http', '$state'];
    public factory: any;
    public items: any[];
    public paginator: autowp.IPaginator;
  
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
          
            self.$http({
                method: 'GET',
                url: '/api/item',
                params: {
                    related_groups_of: self.factory.id,
                    page: self.$state.params.page,
                    limit: 10,
                    fields: [
                        'name_html,name_default,description,has_text,produced',
                        'design,engine_vehicles',
                        'url,spec_editor_url,specs_url,upload_url,more_pictures_url',
                        'categories.url,categories.name_html,twins_groups.url',
                        'preview_pictures,childs_count,total_pictures'
                    ].join(',')
                }
            }).then(function(response: ng.IHttpResponse<autowp.IPaginatedCollection<any>>) {
                self.items = response.data.items;
                self.paginator = response.data.paginator;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
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
                url: '/factories/:id/items?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


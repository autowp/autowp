import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';
import { ItemService } from 'services/item';

const CONTROLLER_NAME = 'PersonsController';
const STATE_NAME = 'persons';

export class PersonsController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];
    public links: any[] = [];
    public authorPicturesChunks: any[] = [];
    public authorPicturesPaginator: autowp.IPaginator;
    public contentPicturesChunks: any[] = [];
    public contentPicturesPaginator: autowp.IPaginator;
    public item: any;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService
    ) {
        var self = this;
          
        this.ItemService.getItem(this.$state.params.id, {
            fields: ['name_text', 'name_html', 'description'].join(',')
        }).then(function(item: autowp.IItem) {
            
            self.item = item;
            
            if (self.item.item_type_id != 8) {
                $state.go('error-404');
            }
        
            self.$scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: true
                },
                name: 'page/213/name',
                pageId: 213,
                args: {
                    PERSON_ID: self.item.id,
                    PERSON_NAME: self.item.name_text
                }
            });
            
            self.$http({
                method: 'GET',
                url: '/api/item-link',
                params: {
                    item_id: self.item.id
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.links = response.data.items;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    exact_item_id: self.item.id,
                    exact_item_link_type: 2,
                    fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                    limit: 20,
                    order: 12,
                    page: $state.params.page
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.authorPicturesChunks = chunkBy(response.data.pictures, 4);
                self.authorPicturesPaginator = response.data.paginator; 
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
            self.$http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    status: 'accepted',
                    exact_item_id: self.item.id,
                    exact_item_link_type: 1,
                    fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                    limit: 20,
                    order: 12,
                    page: $state.params.page
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.contentPicturesChunks = chunkBy(response.data.pictures, 4);
                self.contentPicturesPaginator = response.data.paginator;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        }, function() {
            $state.go('error-404');
        });
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, PersonsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/persons/:id?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


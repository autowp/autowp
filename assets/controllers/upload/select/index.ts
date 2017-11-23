import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { chunk } from 'chunk';

const CONTROLLER_NAME = 'UploadSelectController';
const STATE_NAME = 'upload-select';

export class UploadSelectController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];
    
    public brand: autowp.IItem;
    public brands: autowp.IItem[];
    public paginator: autowp.IPaginator;
    public vehicles: autowp.IItem[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/30/name',
            pageId: 30
        });
        
        var self = this;
        
        let brandId = parseInt($state.params.brand_id);
        if (brandId) {
            this.ItemService.getItem(brandId).then(function(item: autowp.IItem) {
                self.brand = item;
                
                self.ItemService.getItems({
                    type_id: 1,
                    order: 'name',
                    limit: 500,
                    fields: 'name_html,childs_count,is_group',
                    parent_id: self.brand.id
                }).then(function(result: autowp.GetItemsResult) {
                    self.vehicles = result.items;
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        } else {
            
            this.ItemService.getItems({
                type_id: 5,
                order: 'name',
                limit: 500,
                fields: 'name_only'
            }).then(function(result: autowp.GetItemsResult) {
                self.brands = chunk(result.items, 6);
                self.paginator = result.paginator;
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
        }
        
        /*
        'cropMsg'      => $this->translate('upload/picture/crop'),
        'croppedToMsg' => $this->translate('upload/picture/cropped-to'),
        'cropSaveUrl'  => $this->url(null, [
            'controller' => 'upload',
            'action'     => 'crop-save',
        ]),
        'perspectives' => $this->perspectives
        */
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UploadSelectController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/upload/select?brand_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


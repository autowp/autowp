import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';

import './select';

const CONTROLLER_NAME = 'UploadController';
const STATE_NAME = 'upload';

export class UploadController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];
    
    public selected: boolean;
    public selectionName: string;
    private replace: any;
  
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
            name: 'page/29/name',
            pageId: 29
        });
        
        let self = this;
        
        let replace = parseInt($state.params.replace);
        if (replace) {
            this.$http({
                method: 'GET',
                url: '/api/picture/' + replace,
                params: {
                    fields: 'name_html'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.replace = response.data;
                
                self.selected = true;
                self.selectionName = self.replace.name_html;
                
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        }
        
        let itemId = parseInt($state.params.item_id);
        if (itemId) {
            this.ItemService.getItem(itemId, {
                fields: 'name_html'
            }).then(function(item: autowp.IItem) {
                self.selected = true;
                self.selectionName = item.name_html;
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
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
    .controller(CONTROLLER_NAME, UploadController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/upload?item_id&perspective_id&replace',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


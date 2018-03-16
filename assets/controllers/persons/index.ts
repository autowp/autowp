import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';
import { ItemService } from 'services/item';

import './authors';
import './person';

const CONTROLLER_NAME = 'PersonsController';
const STATE_NAME = 'persons';

export class PersonsController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];
    public paginator: autowp.IPaginator;
    public items: autowp.IItem[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
        private ItemService: ItemService
    ) {
        var self = this;
        
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: true
            },
            name: 'page/214/name',
            pageId: 214
        });
          
        this.ItemService.getItems({
            type_id: 8,
            fields: ['name_html,name_default,description,has_text',
                     'url,more_pictures_url',
                     'preview_pictures.picture.thumbnail,total_pictures'].join(','),
            'descendant_pictures[status]': 'accepted',
            'descendant_pictures[type_id]': 1,
            'preview_pictures[type_id]': 1, 
            order: 'name',
            limit: 10,
            page: this.$state.params.page
        }).then(function(response: autowp.GetItemsResult) {
            
            self.items = response.items;
            self.paginator = response.paginator;
            
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
                url: '/persons?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


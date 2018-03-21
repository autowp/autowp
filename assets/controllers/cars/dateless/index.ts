import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';

const CONTROLLER_NAME = 'CarsDatelessController';
const STATE_NAME = 'cars-dateless';

export class CarsDatelessController {
    static $inject = ['$scope', '$http', '$state', 'ItemService'];

    public items: any[] = [];
    public paginator: autowp.IPaginator;

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
            name: 'page/103/name',
            pageId: 1
        });

        let self = this;
        this.ItemService.getItems({
            dateless: 1,
            fields: [
                'name_html,name_default,description,has_text,produced',
                'design,engine_vehicles',
                'url,spec_editor_url,specs_url,more_pictures_url',
                'categories.url,categories.name_html,twins_groups',
                'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
            ].join(','),
            order: 'age',
            page: this.$state.params.page,
            limit: 10
        }).then(function(result: autowp.GetItemsResult) {
            self.items = result.items;
            self.paginator = result.paginator;
        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        })
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, CarsDatelessController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/dateless?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


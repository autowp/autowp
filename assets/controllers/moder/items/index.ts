import * as angular from 'angular';
import Module from 'app.module';
import { SpecService } from 'services/spec';
import { VehicleTypeService } from 'services/vehicle-type';
import { PerspectiveService } from 'services/perspective';
import { PictureModerVoteTemplateService } from 'services/picture-moder-vote-template';
import { PictureModerVoteService } from 'services/picture-moder-vote';
import { AclService } from 'services/acl';
import "corejs-typeahead";
import * as $ from 'jquery';
import "./new";
import "./item";

const CONTROLLER_NAME = 'ModerItemsController';
const STATE_NAME = 'moder-items';

function toPlain(options: any[], deep: number): any[] {
    var result: any[] = [];
    angular.forEach(options, function(item: any) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item: any) {
            result.push(item);
        });
    });
    return result;
}

interface IFilter {
    name: string|null,
    name_exclude: string | null,
    item_type_id: number | null,
    vehicle_type_id: number | null,
    vehicle_childs_type_id: number | null,
    spec: any,
    no_parent: boolean,
    text:  string|null,
    from_year: number | null,
    to_year: number | null,
    order: string,
    ancestor_id: number | null,
};

var DEFAULT_ORDER = 'id_desc';

export class ModerItemsController {
    public listMode: boolean;
    static $inject = ['$scope', '$http', '$state', '$q', '$element', 'PerspectiveService', 'PictureModerVoteService', 'PictureModerVoteTemplateService', 'VehicleTypeService', 'SpecService'];

    public loading: number = 0;
    public items: any[] = [];
    public paginator: autowp.IPaginator;
    public vehicleTypeOptions: any[] = [];
    public specOptions: any[] = [];
    public page: number;
    public filter: IFilter = {
        name: null,
        name_exclude: null,
        item_type_id: null,
        vehicle_type_id: null,
        vehicle_childs_type_id: null,
        spec: null,
        no_parent: false,
        text: null,
        from_year: null,
        to_year: null,
        order: DEFAULT_ORDER,
        ancestor_id: null,
    };

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService,
        private $element: any,
        private PerspectiveService: PerspectiveService,
        private PictureModerVoteService: PictureModerVoteService,
        private PictureModerVoteTemplateService: PictureModerVoteTemplateService,
        private VehicleTypeService: VehicleTypeService,
        private SpecService: SpecService
    ) {
        $scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/131/name',
            pageId: 131
        });

        this.filter = {
            name: $state.params.name || null,
            name_exclude: $state.params.name_exclude || null,
            item_type_id: parseInt($state.params.item_type_id) || null,
            vehicle_type_id: $state.params.vehicle_type_id || null,
            vehicle_childs_type_id: parseInt($state.params.vehicle_childs_type_id) || null,
            spec: $state.params.spec || null,
            no_parent: $state.params.no_parent ? true : false,
            text: $state.params.text || null,
            from_year: $state.params.from_year || null,
            to_year: $state.params.to_year || null,
            order: $state.params.order || DEFAULT_ORDER,
            ancestor_id: $state.params.ancestor_id || null,
        };
        this.listMode = !!$state.params.list;

        this.page = $state.params.page;

        var self = this;
        VehicleTypeService.getTypes().then(function(types) {
            self.vehicleTypeOptions = toPlain(types, 0);
        });

        SpecService.getSpecs().then(function(types) {
            self.specOptions = toPlain(types, 0);
        });

        this.load();

        var $itemIdElement = $($element[0]).find(':input[name=ancestor_id]');
        $itemIdElement.val(this.filter.ancestor_id ? '#' + this.filter.ancestor_id : '');
        var itemIdLastValue = $itemIdElement.val();
        $itemIdElement
            .on('typeahead:select', function(ev: any, item: any) {
                itemIdLastValue = item.name_text;
                self.filter.ancestor_id = item.id;
                self.load();
            })
            .bind('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (itemIdLastValue && !curValue) {
                    self.filter.ancestor_id = null;
                    self.load();
                }
                itemIdLastValue = curValue;
            })
            .typeahead({ }, {
                display: function(item: any) {
                    return item.name_text;
                },
                templates: {
                    suggestion: function(item: any) {
                        return $('<div class="tt-suggestion tt-selectable"></div>')
                            .html(item.name_html);
                    }
                },
                source: function(query: string, syncResults: Function, asyncResults: Function) {
                    var params = {
                        limit: 10,
                        fields: 'name_text,name_html',
                        id: '',
                        name: ''
                    };
                    if (query.substring(0, 1) == '#') {
                        params.id = query.substring(1);
                    } else {
                        params.name = query + '%';
                    }

                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });

                }
            });
    }

    public getStateParams() {
        return {
            name: this.filter.name ? this.filter.name : null,
            name_exclude: this.filter.name_exclude ? this.filter.name_exclude : null,
            item_type_id: this.filter.item_type_id,
            vehicle_type_id: this.filter.vehicle_type_id,
            vehicle_childs_type_id: this.filter.vehicle_childs_type_id,
            spec: this.filter.spec,
            order: this.filter.order == DEFAULT_ORDER ? null : this.filter.order,
            no_parent: this.filter.no_parent ? 1 : null,
            text: this.filter.text ? this.filter.text : null,
            from_year: this.filter.from_year ? this.filter.from_year : null,
            to_year: this.filter.to_year ? this.filter.to_year : null,
            ancestor_id: this.filter.ancestor_id ? this.filter.ancestor_id : null,
            page: this.page,
            list: this.listMode ? '1' : ''
        };
    }

    public load() {
        this.loading++;
        this.items = [];

        var stateParams = this.getStateParams();

        this.$state.go(this.$state.current.name, stateParams, {
            reload: false,
            location: 'replace',
            notify: false
        });

        let fields = 'name_html';
        let limit = 500;
        if (! this.listMode) {
            fields = [
                'name_html,name_default,description,has_text,produced',
                'design,engine_vehicles',
                'url,spec_editor_url,specs_url,more_pictures_url',
                'categories.url,categories.name_html,twins_groups',
                'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
            ].join(',');
            limit = 10;
        }

        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item',
            params: {
                name: this.filter.name ? this.filter.name + '%' : null,
                name_exclude: this.filter.name_exclude ? this.filter.name_exclude + '%' : null,
                type_id: this.filter.item_type_id,
                vehicle_type_id: this.filter.vehicle_type_id,
                vehicle_childs_type_id: this.filter.vehicle_childs_type_id,
                spec: this.filter.spec,
                order: this.filter.order,
                no_parent: this.filter.no_parent ? 1 : null,
                text: this.filter.text ? this.filter.text : null,
                from_year: this.filter.from_year ? this.filter.from_year : null,
                to_year: this.filter.to_year ? this.filter.to_year : null,
                ancestor_id: this.filter.ancestor_id ? this.filter.ancestor_id : null,
                page: this.page,
                fields: fields,
                limit: limit
            }
        }).then(function(response: ng.IHttpResponse<any>) {
            self.items = response.data.items;
            self.paginator = response.data.paginator;
            self.loading--;
        }, function() {
            self.loading--;
        });
    }

    public setListModeEnabled(value: boolean) {
        this.listMode = value;
        if (value) {
            this.filter.order = 'name';
        }
        this.load();
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerItemsController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items?name&name_exclude&item_type_id&vehicle_type_id&vehicle_childs_type_id&spec&from_year&to_year&ancestor_id&text&no_parent&order&page&list',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: {
                    name: { dynamic: true },
                    name_exclude: { dynamic: true },
                    item_type_id: { dynamic: true },
                    vehicle_type_id: { dynamic: true },
                    vehicle_childs_type_id: { dynamic: true },
                    spec: { dynamic: true },
                    from_year: { dynamic: true },
                    to_year: { dynamic: true },
                    ancestor_id: { dynamic: true },
                    text: { dynamic: true },
                    no_parent: { dynamic: true },
                    order: { dynamic: true },
                    page: { dynamic: true },
                    list: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);

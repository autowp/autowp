import * as angular from 'angular';
import Module from 'app.module';
import { VehicleTypeService } from 'services/vehicle-type';
import { PerspectiveService } from 'services/perspective';
import { PictureModerVoteTemplateService } from 'services/picture-moder-vote-template';
import { PictureModerVoteService } from 'services/picture-moder-vote';
import { AclService } from 'services/acl';
import { chunkBy } from 'chunk';
import * as $ from 'jquery';
import "corejs-typeahead";

const CONTROLLER_NAME = 'ModerPicturesController';
const STATE_NAME = 'moder-pictures';

function toPlain(options: any[], deep: number): any[] {
    var result: any[] = [];
    angular.forEach(options, function(item) {
        item.deep = deep;
        result.push(item);
        angular.forEach(toPlain(item.childs, deep+1), function(item: any) {
            result.push(item);
        });
    });
    return result;
}

export class ModerPicturesController {
    static $inject = ['$scope', '$http', '$state', '$q', '$element', 'PerspectiveService', 'PictureModerVoteService', 'PictureModerVoteTemplateService', 'VehicleTypeService'];
    
    public loading: number = 0;
    public onPictureSelect: Function;
    public pictures: any[] = [];
    public hasSelectedItem: boolean = false;
    private selected: number[] = [];
    public chunks: any[] = [];
    public paginator: autowp.IPaginator;
    public moderVoteTemplateOptions: any[] = [];
    public vehicleTypeOptions: any[] = [];
    public perspectiveOptions: any[] = [];
    public page: number;
    public order: string;
    public similar: boolean;
    public gps: boolean;
    public lost: boolean;
    public special_name: boolean;
    public requests: any;
    public replace: any;
    private owner_id: any;
    public comments: any;
    private item_id: any;
    public perspective_id: any;
    public car_type_id: any;
    public status: any;

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService, 
        private $element: any, 
        private PerspectiveService: PerspectiveService, 
        private ModerVoteService: PictureModerVoteService, 
        private ModerVoteTemplateService: PictureModerVoteTemplateService, 
        private VehicleTypeService: VehicleTypeService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/73/name',
            pageId: 73
        });
        
        this.status = this.$state.params.status;
        this.car_type_id = this.$state.params.car_type_id;
        this.perspective_id = this.$state.params.perspective_id;
        this.item_id = this.$state.params.item_id;
        this.comments = this.$state.params.comments;
        this.owner_id = this.$state.params.owner_id;
        this.replace = this.$state.params.replace;
        this.requests = this.$state.params.requests;
        this.special_name = this.$state.params.special_name ? true : false;
        this.lost = this.$state.params.lost ? true : false;
        this.gps = this.$state.params.gps ? true : false;
        this.similar = this.$state.params.similar ? true : false;
        this.order = this.$state.params.order || '1';
        
        this.page = this.$state.params.page;
        
        
        
        var self = this;
        
        this.onPictureSelect = function(picture: any, active: boolean) {
            if (active) {
                self.selected.push(picture.id);
            } else {
                var index = self.selected.indexOf(picture.id);
                if (index > -1) {
                    self.selected.splice(index, 1);
                }
            }
            
            self.hasSelectedItem = self.selected.length > 0;
        };
        
        this.VehicleTypeService.getTypes().then(function(types: any) {
            self.vehicleTypeOptions = toPlain(types, 0);
        });
        
        this.PerspectiveService.getPerspectives().then(function(perspectives: any) {
            self.perspectiveOptions = perspectives;
        });
        
        this.ModerVoteTemplateService.getTemplates().then(function(templates: any) {
            self.moderVoteTemplateOptions = templates;
        });
        
        var $userIdElement = $($element[0]).find(':input[name=owner_id]');
        $userIdElement.val(this.owner_id ? '#' + this.owner_id : '');
        var userIdLastValue = $userIdElement.val();
        $userIdElement
            .typeahead({ }, {
                display: function(item: any) {
                    return item.name;
                },
                templates: {
                    suggestion: function(item: any) {
                        return $('<div class="tt-suggestion tt-selectable"></div>')
                            .text(item.name);
                    }
                },
                source: function(query: string, syncResults: Function, asyncResults: Function) {
                    var params = {
                        limit: 10,
                        id: '',
                        search: ''
                    };
                    if (query.substring(0, 1) == '#') {
                        params.id = query.substring(1);
                    } else {
                        params.search = query;
                    }
                    
                    $http({
                        method: 'GET',
                        url: '/api/user',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });
                    
                }
            })
            .on('typeahead:select', function(ev: any, item: any) {
                userIdLastValue = item.name;
                self.owner_id = item.id;
                self.load();
            })
            .on('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (userIdLastValue && !curValue) {
                    self.owner_id = null;
                    self.load();
                }
                userIdLastValue = curValue;
            });
        
        var $itemIdElement = $($element[0]).find(':input[name=item_id]');
        $itemIdElement.val(this.item_id ? '#' + this.item_id : '');
        var itemIdLastValue = $itemIdElement.val();
        $itemIdElement
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
                        params.name = '%' + query + '%';
                    }
                    
                    $http({
                        method: 'GET',
                        url: '/api/item',
                        params: params
                    }).then(function(response: ng.IHttpResponse<any>) {
                        asyncResults(response.data.items);
                    });
                    
                }
            })
            .on('typeahead:select', function(ev: any, item: any) {
                itemIdLastValue = item.name_text;
                self.item_id = item.id;
                self.load();
            })
            .on('change blur', function(ev: any, item: any) {
                var curValue = $(this).val();
                if (itemIdLastValue && !curValue) {
                    self.item_id = null;
                    self.load();
                }
                itemIdLastValue = curValue;
            });
        
        this.load();
    }
    
    public load() {
        this.loading++;
        this.pictures = [];
        
        this.selected = [];
        this.hasSelectedItem = false;
        var params = {
            status: this.status,
            car_type_id: this.car_type_id,
            perspective_id: this.perspective_id,
            item_id: this.item_id,
            comments: this.comments,
            owner_id: this.owner_id,
            replace: this.replace,
            requests: this.requests,
            special_name: this.special_name ? 1 : null,
            lost: this.lost ? 1 : null,
            gps: this.gps ? 1 : null,
            similar: this.similar ? 1 : null,
            order: this.order,
            page: this.page,
            fields: null as null|string,
            limit: null as null|number
        };
        
        this.$state.go(STATE_NAME, params, {
            notify: false,
            reload: false,
            location: 'replace'
        });
        
        params.fields = 'owner,thumbnail,moder_vote,votes,similar,views,comments_count,perspective_item,name_html,name_text';
        params.limit = 24;
        
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/picture',
            params: params
        }).then(function(response: ng.IHttpResponse<any>) {
            self.pictures = response.data.pictures;
            self.chunks = chunkBy(self.pictures, 4);
            self.paginator = response.data.paginator;
            self.loading--;
        }, function() {
            self.loading--;
        });
    }
    
    public votePictures(vote: number, reason: string) {
        var self = this;
        angular.forEach(this.selected, function(id: number) {
            var promises: any[] = [];
            angular.forEach(self.pictures, function(picture) {
                if (picture.id == id) {
                    var q = self.ModerVoteService.vote(picture.id, vote, reason);
                    promises.push(q);
                }
            });
            
            self.$q.all(promises).then(function() { 
                self.load();
            });
        });
        this.selected = [];
        this.hasSelectedItem = false;
    };
    
    public acceptPictures() {
        var self = this;
        angular.forEach(this.selected, function(id: number) {
            var promises: any[] = [];
            angular.forEach(self.pictures, function(picture: any) {
                if (picture.id == id) {
                    var q = self.$http({
                        method: 'PUT',
                        url: '/api/picture/' + picture.id,
                        data: {
                            status: 'accepted'
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        picture.status = 'accepted';
                    });
                    
                    promises.push(q);
                }
            });
            
            self.$q.all(promises).then(function() { 
                self.load();
            });
        });
        this.selected = [];
        this.hasSelectedItem = false;
    };
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPicturesController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures?status&car_type_id&perspective_id&item_id&comments&owner_id&replace&requests&special_name&lost&gps&similar&order&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    status: { dynamic: true },
                    car_type_id: { dynamic: true },
                    perspective_id: { dynamic: true },
                    item_id: { dynamic: true },
                    comments: { dynamic: true },
                    owner_id: { dynamic: true },
                    replace: { dynamic: true },
                    requests: { dynamic: true },
                    special_name: { dynamic: true },
                    lost: { dynamic: true },
                    gps: { dynamic: true },
                    similar: { dynamic: true },
                    order: { dynamic: true },
                    page: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);

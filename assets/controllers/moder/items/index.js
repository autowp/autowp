import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import SPEC_SERVICE from 'services/spec';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import PERSPECTIVE_SERVICE from 'services/perspective';
import MODER_VOTE_TEMPLATE_SERVICE from 'services/picture-moder-vote-template';
import MODER_VOTE_SERVICE from 'services/picture-moder-vote';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerItemsController';
const STATE_NAME = 'moder-items';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/items?name&name_exclude&item_type_id&vehicle_type_id&vehicle_childs_type_id&spec&from_year&to_year&parent_id&text&no_parent&order&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    name: { dynamic: true },
                    name_exclude: { dynamic: true },
                    item_type_id: { dynamic: true },
                    vehicle_type_id: { dynamic: true },
                    vehicle_childs_type_id: { dynamic: true },
                    spec: { dynamic: true },
                    from_year: { dynamic: true },
                    to_year: { dynamic: true },
                    parent_id: { dynamic: true },
                    text: { dynamic: true },
                    no_parent: { dynamic: true },
                    order: { dynamic: true },
                    page: { dynamic: true }
                },
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q', PERSPECTIVE_SERVICE, MODER_VOTE_SERVICE, MODER_VOTE_TEMPLATE_SERVICE, VEHICLE_TYPE_SERVICE, SPEC_SERVICE,
        function($scope, $http, $state, $q, PerspectiveService, ModerVoteService, ModerVoteTemplateService, VehicleTypeService, SpecService) {
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 131
            });
            
            var ctrl = this;
            
            var DEFAULT_ORDER = 'id_desc';
            
            ctrl.filter = {
                name: $state.params.name || null,
                name_exclude: $state.params.name_exclude || null,
                item_type_id: parseInt($state.params.item_type_id) || null,
                vehicle_type_id: $state.params.vehicle_type_id || null,
                vehicle_childs_type_id: parseInt($state.params.vehicle_childs_type_id) || null,
                spec: $state.params.spec || null,
                no_parent: $state.params.no_parent || false,
                text: $state.params.text || null,
                from_year: $state.params.from_year || null,
                to_year: $state.params.to_year || null,
                order: $state.params.order || DEFAULT_ORDER,
                parent: null,
            };
            
            ctrl.parent_search_text = '';
            if ($state.params.parent_id) {
                ctrl.filter.parent = {
                    id: parseInt($state.params.parent_id),
                    name: '#' + $state.params.parent_id
                };
                ctrl.parent_search_text = '#' + $state.params.parent_id;
            }
            
            ctrl.items = [];
            ctrl.paginator = null;
            ctrl.page = $state.params.page;
            
            ctrl.vehicleTypeOptions = [];
            ctrl.specOptions = [];
            
            function toPlain(options, deep) {
                var result = [];
                angular.forEach(options, function(item) {
                    item.deep = deep;
                    result.push(item);
                    angular.forEach(toPlain(item.childs, deep+1), function(item) {
                        result.push(item);
                    });
                });
                return result;
            }
            
            VehicleTypeService.getTypes().then(function(types) {
                ctrl.vehicleTypeOptions = toPlain(types, 0);
            });
            
            SpecService.getSpecs().then(function(types) {
                ctrl.specOptions = toPlain(types, 0);
            });
            
            function getStateParams(params) {
                return {
                    name: ctrl.filter.name ? ctrl.filter.name : null,
                    name_exclude: ctrl.filter.name_exclude ? ctrl.filter.name_exclude : null,
                    item_type_id: ctrl.filter.item_type_id,
                    vehicle_type_id: ctrl.filter.vehicle_type_id,
                    vehicle_childs_type_id: ctrl.filter.vehicle_childs_type_id,
                    spec: ctrl.filter.spec,
                    order: ctrl.filter.order == DEFAULT_ORDER ? null : ctrl.filter.order,
                    no_parent: ctrl.filter.no_parent ? 1 : null,
                    text: ctrl.filter.text ? ctrl.filter.text : null,
                    from_year: ctrl.filter.from_year ? ctrl.filter.from_year : null,
                    to_year: ctrl.filter.to_year ? ctrl.filter.to_year : null,
                    parent_id: ctrl.filter.parent ? ctrl.filter.parent.id : null,
                    page: ctrl.page,
                };
            }
            
            ctrl.load = function() {
                //ctrl.loading = true;
                ctrl.pictures = [];
                
                var stateParams = getStateParams();
                
                $state.go($state.current.name, stateParams, {
                    reload: false,
                    location: 'replace',
                    notify: false
                });
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        name: ctrl.filter.name ? '%' + ctrl.filter.name + '%' : null,
                        name_exclude: ctrl.filter.name_exclude ? '%' + ctrl.filter.name_exclude + '%' : null,
                        type_id: ctrl.filter.item_type_id,
                        vehicle_type_id: ctrl.filter.vehicle_type_id,
                        vehicle_childs_type_id: ctrl.filter.vehicle_childs_type_id,
                        spec: ctrl.filter.spec,
                        order: ctrl.filter.order,
                        no_parent: ctrl.filter.no_parent ? 1 : null,
                        text: ctrl.filter.text ? ctrl.filter.text : null,
                        from_year: ctrl.filter.from_year ? ctrl.filter.from_year : null,
                        to_year: ctrl.filter.to_year ? ctrl.filter.to_year : null,
                        parent_id: ctrl.filter.parent ? ctrl.filter.parent.id : null,
                        page: ctrl.page,
                        fields: [
                            'name_html,name_default,description,has_text,produced',
                            'design,engine_vehicles',
                            'url,moder_url,spec_editor_url,specs_url,upload_url,more_pictures_url',
                            'categories.url,categories.name_html,twins_groups.url',
                            'preview_pictures,childs_count,total_pictures'
                        ].join(','),
                        limit: 10
                    }
                }).then(function(response) {
                    ctrl.items = response.data.items;
                    ctrl.paginator = response.data.paginator;
                    ctrl.loading = false;
                }, function() {
                    ctrl.loading = false;
                });
            };
            
            ctrl.load();
            
            ctrl.queryItemName = function(query) {
                var deferred = $q.defer();
                
                var params = {
                    limit: 10,
                    fields: 'name_text'
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
                }).then(function(response) {
                    deferred.resolve(response.data.items);
                }, function() {
                    deferred.reject(null);
                });
                return deferred.promise;
            };
        }
    ]);

export default CONTROLLER_NAME;

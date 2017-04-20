import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import PERSPECTIVE_SERVICE from 'services/perspective';
import MODER_VOTE_TEMPLATE_SERVICE from 'services/picture-moder-vote-template';
import MODER_VOTE_SERVICE from 'services/picture-moder-vote';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerPicturesController';
const STATE_NAME = 'moder-pictures';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures?status&car_type_id&perspective_id&item_id&comments&owner_id&replace&requests&special_name&lost&gps&similar&order&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q', PERSPECTIVE_SERVICE, MODER_VOTE_SERVICE, MODER_VOTE_TEMPLATE_SERVICE, VEHICLE_TYPE_SERVICE,
        function($scope, $http, $state, $q, PerspectiveService, ModerVoteService, ModerVoteTemplateService, VehicleTypeService) {
            
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 73
            });
            
            $scope.pictures = [];
            $scope.hasSelectedItem = false;
            $scope.paginator = null;
            $scope.status = $state.params.status;
            $scope.car_type_id = $state.params.car_type_id;
            $scope.perspective_id = $state.params.perspective_id;
            $scope.selectedItem = $state.params.item_id ? {
                id: $state.params.item_id,
                name: '#' + $state.params.item_id
            } : null;
            //$scope.item_id = $state.params.item_id;
            $scope.comments = $state.params.comments;
            $scope.selectedOwner = $state.params.owner_id ? {
                id: $state.params.owner_id,
                name: '#' + $state.params.owner_id
            } : null;
            //$scope.owner_id = $state.params.owner_id;
            $scope.replace = $state.params.replace;
            $scope.requests = $state.params.requests;
            $scope.special_name = $state.params.special_name ? 1 : null;
            $scope.lost = $state.params.lost ? 1 : null;
            $scope.gps = $state.params.gps ? 1 : null;
            $scope.similar = $state.params.similar ? 1 : null;
            $scope.order = $state.params.order || 1;
            
            $scope.page = $state.params.page;
            
            $scope.vehicleTypeOptions = [];
            $scope.perspectiveOptions = [];
            $scope.moderVoteTemplateOptions = [];
            
            var selected = [];
            
            $scope.onPictureSelect = function(picture, active) {
                if (active) {
                    selected.push(picture.id);
                } else {
                    var index = selected.indexOf(picture.id);
                    if (index > -1) {
                        selected.splice(index, 1);
                    }
                }
                
                $scope.hasSelectedItem = selected.length > 0;
            };
            
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
                $scope.vehicleTypeOptions = toPlain(types, 0);
            });
            
            PerspectiveService.getPerspectives().then(function(perspectives) {
                $scope.perspectiveOptions = perspectives;
            });
            
            ModerVoteTemplateService.getTemplates().then(function(templates) {
                $scope.moderVoteTemplateOptions = templates;
            });
            
            $scope.load = function() {
                $scope.loading = true;
                $scope.pictures = [];
                
                selected = [];
                $scope.hasSelectedItem = false;
                var params = {
                    status: $scope.status,
                    car_type_id: $scope.car_type_id,
                    perspective_id: $scope.perspective_id,
                    item_id: $scope.selectedItem ? $scope.selectedItem.id : null,
                    comments: $scope.comments,
                    owner_id: $scope.selectedOwner ? $scope.selectedOwner.id : null,
                    replace: $scope.replace,
                    requests: $scope.requests,
                    special_name: $scope.special_name,
                    lost: $scope.lost,
                    gps: $scope.gps,
                    similar: $scope.similar,
                    order: $scope.order,
                    page: $scope.page
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                params.fields = 'owner,thumbnail,moder_vote,votes,similar,comments_count,perspective_item';
                params.limit = 24;
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: params
                }).then(function(response) {
                    $scope.pictures = response.data.pictures;
                    $scope.paginator = response.data.paginator;
                    $scope.loading = false;
                });
            };
            
            $scope.acceptPictures = function() {
                angular.forEach(selected, function(id) {
                    var promises = [];
                    angular.forEach($scope.pictures, function(picture) {
                        if (picture.id == id) {
                            var q = $http({
                                method: 'PUT',
                                url: '/api/picture/' + picture.id,
                                data: {
                                    status: 'accepted'
                                }
                            }).then(function(response) {
                                picture.status = 'accepted';
                            });
                            
                            promises.push(q);
                        }
                    });
                    
                    $q.all(promises).then(function() { 
                        $scope.load();
                    });
                });
                selected = [];
                $scope.hasSelectedItem = false;
            };
            
            $scope.votePictures = function(vote, reason) {
                angular.forEach(selected, function(id) {
                    var promises = [];
                    angular.forEach($scope.pictures, function(picture) {
                        if (picture.id == id) {
                            var q = ModerVoteService.vote(picture.id, vote, reason);
                            promises.push(q);
                        }
                    });
                    
                    $q.all(promises).then(function() { 
                        $scope.load();
                    });
                });
                selected = [];
                $scope.hasSelectedItem = false;
            };
            
            $scope.queryUserName = function(query) { 
                var deferred = $q.defer();
                
                var params = {
                    limit: 10
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
                }).then(function(response) {
                    deferred.resolve(response.data.items);
                }, function() {
                    deferred.reject(null);
                });
                return deferred.promise;
            };
            
            $scope.queryItemName = function(query) {
                var deferred = $q.defer();
                
                var params = {
                    limit: 10
                };
                if (query.substring(0, 1) == '#') {
                    params.id = query.substring(1);
                } else {
                    params.search = query;
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
            
            $scope.load();
        }
    ]);

export default CONTROLLER_NAME;

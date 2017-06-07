import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import VEHICLE_TYPE_SERVICE from 'services/vehicle-type';
import PERSPECTIVE_SERVICE from 'services/perspective';
import MODER_VOTE_TEMPLATE_SERVICE from 'services/picture-moder-vote-template';
import MODER_VOTE_SERVICE from 'services/picture-moder-vote';
import ACL_SERVICE_NAME from 'services/acl';
import "corejs-typeahead";
import $ from 'jquery';

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
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$q', '$element', PERSPECTIVE_SERVICE, MODER_VOTE_SERVICE, MODER_VOTE_TEMPLATE_SERVICE, VEHICLE_TYPE_SERVICE,
        function($scope, $http, $state, $q, $element, PerspectiveService, ModerVoteService, ModerVoteTemplateService, VehicleTypeService) {
            
            var ctrl = this;
            ctrl.loading = 0;
            
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
            $scope.item_id = $state.params.item_id;
            $scope.comments = $state.params.comments;
            $scope.owner_id = $state.params.owner_id;
            $scope.replace = $state.params.replace;
            $scope.requests = $state.params.requests;
            $scope.special_name = $state.params.special_name ? true : false;
            $scope.lost = $state.params.lost ? true : false;
            $scope.gps = $state.params.gps ? true : false;
            $scope.similar = $state.params.similar ? true : false;
            $scope.order = $state.params.order || '1';
            
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
            
            ctrl.load = function() {
                ctrl.loading++;
                $scope.pictures = [];
                
                selected = [];
                $scope.hasSelectedItem = false;
                var params = {
                    status: $scope.status,
                    car_type_id: $scope.car_type_id,
                    perspective_id: $scope.perspective_id,
                    item_id: $scope.item_id,
                    comments: $scope.comments,
                    owner_id: $scope.owner_id,
                    replace: $scope.replace,
                    requests: $scope.requests,
                    special_name: $scope.special_name ? 1 : null,
                    lost: $scope.lost ? 1 : null,
                    gps: $scope.gps ? 1 : null,
                    similar: $scope.similar ? 1 : null,
                    order: $scope.order,
                    page: $scope.page
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                params.fields = 'owner,thumbnail,moder_vote,votes,similar,comments_count,perspective_item,name_html,name_text';
                params.limit = 24;
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: params
                }).then(function(response) {
                    $scope.pictures = response.data.pictures;
                    $scope.paginator = response.data.paginator;
                    ctrl.loading--;
                }, function() {
                    ctrl.loading--;
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
                        ctrl.load();
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
                    	ctrl.load();
                    });
                });
                selected = [];
                $scope.hasSelectedItem = false;
            };
            
            var $userIdElement = $($element[0]).find(':input[name=owner_id]');
            $userIdElement.val($scope.owner_id ? '#' + $scope.owner_id : '');
            var userIdLastValue = $userIdElement.val();
            $userIdElement
                .typeahead({ }, {
                    display: function(item) {
                        return item.name;
                    },
                    templates: {
                        suggestion: function(item) {
                            return $('<div class="tt-suggestion tt-selectable"></div>')
                                .text(item.name);
                        }
                    },
                    source: function(query, syncResults, asyncResults) {
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
                            asyncResults(response.data.items);
                        });
                        
                    }
                })
                .on('typeahead:select', function(ev, item) {
                    userIdLastValue = item.name;
                    $scope.owner_id = item.id;
                    ctrl.load();
                })
                .on('change blur', function(ev, item) {
                    var curValue = $(this).val();
                    if (userIdLastValue && !curValue) {
                    	$scope.owner_id = null;
                        ctrl.load();
                    }
                    userIdLastValue = curValue;
                });
            
            var $itemIdElement = $($element[0]).find(':input[name=item_id]');
            $itemIdElement.val($scope.item_id ? '#' + $scope.item_id : '');
            var itemIdLastValue = $itemIdElement.val();
            $itemIdElement
                .typeahead({ }, {
                    display: function(item) {
                        return item.name_text;
                    },
                    templates: {
                        suggestion: function(item) {
                            return $('<div class="tt-suggestion tt-selectable"></div>')
                                .html(item.name_html);
                        }
                    },
                    source: function(query, syncResults, asyncResults) {
                        var params = {
                            limit: 10,
                            fields: 'name_text,name_html'
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
                            asyncResults(response.data.items);
                        });
                        
                    }
                })
                .on('typeahead:select', function(ev, item) {
                    itemIdLastValue = item.name_text;
                    $scope.item_id = item.id;
                    ctrl.load();
                })
                .on('change blur', function(ev, item) {
                    var curValue = $(this).val();
                    if (itemIdLastValue && !curValue) {
                    	$scope.item_id = null;
                        ctrl.load();
                    }
                    itemIdLastValue = curValue;
                });
            
            ctrl.load();
        }
    ]);

export default CONTROLLER_NAME;

import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import "corejs-typeahead";
import $ from 'jquery';
import notify from 'notify';

const CONTROLLER_NAME = 'ModerCommentsController';
const STATE_NAME = 'moder-comments';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/comments?user&moderator_attention&pictures_of_item_id&page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    user: { dynamic: true },
                    moderator_attention: { dynamic: true },
                    item_id: { dynamic: true },
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
        '$scope', '$http', '$state', '$q', '$element',
        function($scope, $http, $state, $q, $element) {
            
            $scope.title = 'page/119/title';
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                name: 'page/110/name',
                pageId: 110
            });
            
            var ctrl = this;
            
            ctrl.loading = 0;
            
            ctrl.comments = [];
            ctrl.paginator = null;
            ctrl.user = $state.params.user;
            ctrl.moderator_attention = $state.params.moderator_attention;
            ctrl.pictures_of_item_id = $state.params.pictures_of_item_id;
            ctrl.page = $state.params.page;
            
            ctrl.load = function() {
                ctrl.loading++;
                
                var params = {
                    user: ctrl.user,
                    moderator_attention: ctrl.moderator_attention,
                    pictures_of_item_id: ctrl.pictures_of_item_id,
                    page: ctrl.page,
                    order: 'date_desc',
                    fields: 'preview,user,is_new,status,url'
                };
                
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                $http({
                    method: 'GET',
                    url: '/api/comment',
                    params: params
                }).then(function(response) {
                    ctrl.comments = response.data.items;
                    ctrl.paginator = response.data.paginator;
                    ctrl.loading--;
                }, function(response) {
                    notify.response(response);
                    ctrl.loading--;
                });
            };
            
            var $userIdElement = $($element[0]).find(':input[name=user_id]');
            $userIdElement.val(ctrl.user ? '#' + ctrl.user : '');
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
                    ctrl.user = item.id;
                    ctrl.load();
                })
                .on('change blur', function(ev, item) {
                    var curValue = $(this).val();
                    if (userIdLastValue && !curValue) {
                        ctrl.user = null;
                        ctrl.load();
                    }
                    userIdLastValue = curValue;
                });
            
            var $itemIdElement = $($element[0]).find(':input[name=pictures_of_item_id]');
            $itemIdElement.val(ctrl.pictures_of_item_id ? '#' + ctrl.pictures_of_item_id : '');
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
                    ctrl.pictures_of_item_id = item.id;
                    ctrl.load();
                })
                .on('change blur', function(ev, item) {
                    var curValue = $(this).val();
                    if (itemIdLastValue && !curValue) {
                        ctrl.pictures_of_item_id = null;
                        ctrl.load();
                    }
                    itemIdLastValue = curValue;
                });
            
            
            ctrl.load();
        }
    ]);

export default CONTROLLER_NAME;

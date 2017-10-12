import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import "corejs-typeahead";
import $ from 'jquery';

const CONTROLLER_NAME = 'CarsAttrsChangeLogController';
const STATE_NAME = 'cars-attrs-change-log';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/cars/attrs-change-log?page&user_id',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    user_id: { dynamic: true }
                },
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$element',
        function($scope, $http, $state, $element) {
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                pageId: 103
            });
            
            var ctrl = this;
            
            ctrl.items = [];
            ctrl.paginator = null;
            ctrl.user_id = $state.params.user_id;
            ctrl.page = $state.params.page;
            
            ctrl.load = function() {
                
                var params = {
                    user_id: ctrl.user_id,
                    page: ctrl.page
                };
                $state.go(STATE_NAME, params, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                
                params.fields = 'user,item.name_html';
                
                $http({
                    method: 'GET',
                    url: '/api/attr/user-value',
                    params: params
                }).then(function(response) {
                    ctrl.items = response.data.items;
                    ctrl.paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            };
            
            ctrl.load();
            
            var $userIdElement = $($element[0]).find(':input[name=user_id]');
            $userIdElement.val(ctrl.user_id ? '#' + ctrl.user_id : '');
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
                    ctrl.user_id = item.id;
                    ctrl.load();
                })
                .on('change blur', function(ev, item) {
                    var curValue = $(this).val();
                    if (userIdLastValue && !curValue) {
                        ctrl.user_id = null;
                        ctrl.load();
                    }
                    userIdLastValue = curValue;
                });
        }
    ]);

export default CONTROLLER_NAME;

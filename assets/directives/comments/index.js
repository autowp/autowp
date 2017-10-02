import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

angular.module(Module)
    .directive('autowpComments', function() {
        return {
            restirct: 'E',
            scope: {
                itemId: '=',
                typeId: '=',
                user: '=',
                limit: '<',
                page: '<'
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: ['$http', '$scope', '$state',
                function($http, $scope, $state) {
                    var ctrl = this;
                    
                    ctrl.messages = [];
                    ctrl.limit = $scope.limit;
                    
                    ctrl.load = function() {
                        $http({
                            method: 'GET',
                            url: '/api/comment',
                            params: {
                                type_id: $scope.typeId,
                                item_id: $scope.itemId,
                                no_parents: 1,
                                fields: 'user.avatar,user.gravatar,replies,text_html,datetime,vote,user_vote',
                                order: 'date_asc',
                                limit: $scope.limit ? $scope.limit : null,
                                page: $scope.page
                            }
                        }).then(function(response) {
                            ctrl.messages = response.data.items;
                            ctrl.paginator = response.data.paginator;
                        }, function(response) {
                            notify.response(response);
                        });
                    };
                    
                    ctrl.load();
                   
                    ctrl.onSent = function(location) {
                        if ($scope.limit) {
                            $http({
                                method: 'GET',
                                url: location,
                                params: {
                                    fields: 'page',
                                    limit: ctrl.limit
                                }
                            }).then(function(response) {
                                
                                if ($scope.page != response.data.page) {
                                    $state.go('.', {page: response.data.page}); // , { notify: false }
                                } else {
                                    ctrl.load();
                                }
                                
                            }, function(response) {
                                notify.response(response);
                            });
                        } else {
                            ctrl.load();
                        }
                    };
                }
            ]
        };
    });
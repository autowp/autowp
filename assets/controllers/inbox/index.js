import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'InboxController';
const STATE_NAME = 'inbox';

function chunkBy(arr, count) {
    var newArr = [];
    var size = Math.ceil(count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}

const ALL_BRANDS = 'all';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/inbox/:brand/:date/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: {
                    brand: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    date: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    },
                    page: {
                        replace: true,
                        value: '',
                        reload: true,
                        squash: true
                    }
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            if (! $scope.user) {
                $state.go('login');
                return;
            }
            
            if (! $state.params.brand) {
                $state.go(STATE_NAME, {
                    brand: ALL_BRANDS,
                    page: null
                }, {
                    notify: false,
                    reload: false,
                    location: 'replace'
                });
                return;
            }
            
            var ctrl = this;
            
            ctrl.pictures = [];
            ctrl.paginator = null;
            if ($state.params.brand == ALL_BRANDS) {
                ctrl.brand_id = null;
            } else {
                ctrl.brand_id = $state.params.brand ? parseInt($state.params.brand) : null;
            }
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/76/name',
                pageId: 76
            });
            
            $http({
                method: 'GET',
                url: '/api/inbox',
                params: {
                    brand_id: ctrl.brand_id,
                    date: $state.params.date,
                    page: $state.params.page
                }
            }).then(function(response) {
                ctrl.paginator = response.data.paginator;
                ctrl.prev = response.data.prev;
                ctrl.current = response.data.current;
                ctrl.next = response.data.next;
                ctrl.brands = response.data.brands;
                
                if ($state.params.date != ctrl.current.date) {
                    $state.go(STATE_NAME, {
                        date: ctrl.current.date,
                        page: null
                    }, {
                        notify: false,
                        reload: false,
                        location: 'replace'
                    });
                    return;
                }
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        status: 'inbox',
                        fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                        limit: 16,
                        page: $state.params.page,
                        item_id: ctrl.brand_id,
                        add_date: ctrl.current.date,
                        order: 1
                    }
                }).then(function(response) {
                    ctrl.pictures = response.data.pictures;
                    ctrl.chunks = chunkBy(ctrl.pictures, 4);
                    ctrl.paginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.changeBrand = function() {
                $state.go('.', {
                    brand: ctrl.brand_id,
                    page: null
                });
            };
        }
    ]);

export default CONTROLLER_NAME;

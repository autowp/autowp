import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

require("brandicon");

const CONTROLLER_NAME = 'UsersUserPicturesBrandController';
const STATE_NAME = 'users-user-pictures-brand';

function chunkBy(arr, count) {
    var newArr = [];
    var size = Math.ceil(count);
    for (var i=0; i<arr.length; i+=size) {
        newArr.push(arr.slice(i, i+size));
    }
    return newArr;
}

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/users/:identity/pictures/:brand?page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state',
        function($scope, $http, $state) {
            
            var ctrl = this;
            
            function init() {
                
                if (ctrl.user.deleted) {
                    $state.go('error-404');
                    return;
                }
                
                ctrl.identity = ctrl.user.identity ? ctrl.user.identity : 'user' + ctrl.user.id;
                
                $http({
                    method: 'GET',
                    url: '/api/item',
                    params: {
                        type_id: 5,
                        limit: 1,
                        catname: $state.params.brand,
                        fields: 'name_only,catname'
                    }
                }).then(function(response) {
                    if (response.data.items.length <= 0) {
                        $state.go('error-404');
                        return;
                    }
                    ctrl.brand = response.data.items[0];
                    
                    $scope.pageEnv({
                        layout: {
                            blankPage: false,
                            needRight: false
                        },
                        name: 'page/141/name',
                        pageId: 141,
                        args: {
                            USER_NAME: ctrl.user.name,
                            USER_IDENTITY: ctrl.identity,
                            BRAND_NAME: ctrl.brand.name_only,
                            BRAND_CATNAME: ctrl.brand.catname
                        }
                    });
                    
                    $http({
                        method: 'GET',
                        url: '/api/picture',
                        params: {
                            status: 'accepted',
                            fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                            limit: 30,
                            page: $state.params.page,
                            item_id: ctrl.brand.id,
                            owner_id: ctrl.user.id,
                            order: 1
                        }
                    }).then(function(response) {
                        ctrl.pictures = response.data.pictures;
                        ctrl.chunks = chunkBy(ctrl.pictures, 6);
                        ctrl.paginator = response.data.paginator;
                    }, function(response) {
                        notify.response(response);
                    });
                    
                }, function(response) {
                    notify.response(response);
                });
            }
            
            var result = $state.params.identity.match(/^user([0-9]+)$/);
            
            if (result) {
                $http({
                    method: 'GET',
                    url: '/api/user/' + result[1],
                    fields: 'identity'
                }).then(function(response) {
                    ctrl.user = response.data;
                    init();
                }, function(response) {
                    notify.response(response);
                });
                
            } else {
                $http({
                    method: 'GET',
                    url: '/api/user',
                    params: {
                        identity: $state.params.identity,
                        limit: 1,
                        fields: 'identity'
                    }
                }).then(function(response) {
                    if (response.data.items.length <= 0) {
                        $state.go('error-404');
                        return;
                    }
                    ctrl.user = response.data.items[0];
                    init();
                }, function(response) {
                    notify.response(response);
                });
            }

        }
    ]);

export default CONTROLLER_NAME;

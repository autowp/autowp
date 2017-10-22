import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

const CONTROLLER_NAME = 'PersonsController';
const STATE_NAME = 'persons';

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
                url: '/persons/:id?page',
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
            
            ctrl.links = [];
            ctrl.authorPicturesChunks = [];
            ctrl.contentPicturesChunks = [];

            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.id,
                params: {
                    fields: ['name_text', 'name_html', 'description'].join(',')
                }
            }).then(function(response) {
                
                ctrl.item = response.data;
                
                if (ctrl.item.item_type_id != 8) {
                    $state.go('error-404');
                }
            
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: true
                    },
                    name: 'page/213/name',
                    pageId: 213,
                    args: {
                        PERSON_ID: ctrl.item.id,
                        PERSON_NAME: ctrl.item.name_text
                    }
                });
                
                $http({
                    method: 'GET',
                    url: '/api/item-link',
                    params: {
                        item_id: ctrl.item.id
                    }
                }).then(function(response) {
                    ctrl.links = response.data.items;
                }, function(response) {
                    notify.response(response);
                });
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        status: 'accepted',
                        exact_item_id: ctrl.item.id,
                        exact_item_link_type: 2,
                        fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                        limit: 20,
                        order: 12,
                        page: $state.params.page
                    }
                }).then(function(response) {
                    ctrl.authorPicturesChunks = chunkBy(response.data.pictures, 4);
                    ctrl.authorPicturesPaginator = response.data.paginator; 
                }, function(response) {
                    notify.response(response);
                });
                
                $http({
                    method: 'GET',
                    url: '/api/picture',
                    params: {
                        status: 'accepted',
                        exact_item_id: ctrl.item.id,
                        exact_item_link_type: 1,
                        fields: 'owner,thumbnail,votes,views,comments_count,name_html,name_text',
                        limit: 20,
                        order: 12,
                        page: $state.params.page
                    }
                }).then(function(response) {
                    ctrl.contentPicturesChunks = chunkBy(response.data.pictures, 4);
                    ctrl.contentPicturesPaginator = response.data.paginator;
                }, function(response) {
                    notify.response(response);
                });
                
            }, function() {
                $state.go('error-404');
            });
            
            
        }
    ]);

export default CONTROLLER_NAME;

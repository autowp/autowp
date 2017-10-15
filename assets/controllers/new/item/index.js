import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';
import moment from 'moment';

const CONTROLLER_NAME = 'NewItemController';
const STATE_NAME = 'new-item';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/new/:date/item/:item_id/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: {
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
            
            var ctrl = this;
            
            ctrl.paginator = null;
            ctrl.chunks = [];
            ctrl.item = null;
            
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.item_id,
                params: {
                    fields: 'name_html,name_text'
                }
            }).then(function(response) {
                ctrl.item = response.data;
                
                $scope.pageEnv({
                    layout: {
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/210/name',
                    pageId: 210,
                    args: {
                        DATE: moment($state.params.date).format('LL'),
                        DATE_STR: $state.params.date,
                        ITEM_NAME: ctrl.item.name_text
                    }
                });
                
            }, function(response) {
                notify.response(response);
            });
            
            $http({
                method: 'GET',
                url: '/api/picture',
                params: {
                    fields: 'owner,thumbnail,moder_vote,votes,views,comments_count,name_html,name_text',
                    limit: 24,
                    accept_date: $state.params.date,
                    item_id: $state.params.item_id,
                    page: $state.params.page
                }
            }).then(function(response) {
                ctrl.chunks = ctrl.chunkBy(response.data.pictures, 6);
                ctrl.paginator = response.data.paginator;
            }, function(response) {
                notify.response(response);
            });
            
            ctrl.chunkBy = function(arr, count) {
                var newArr = [];
                var size = Math.ceil(count);
                for (var i=0; i<arr.length; i+=size) {
                    newArr.push(arr.slice(i, i+size));
                }
                return newArr;
            };
        }
    ]);

export default CONTROLLER_NAME;

import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import notify from 'notify';

import './item';
import './list-item';

const CONTROLLER_NAME = 'NewController';
const STATE_NAME = 'new';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/new/:date/:page',
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
            ctrl.groups = [];
            ctrl.date = $state.params.date;
            
            $scope.pageEnv({
                layout: {
                    blankPage: false,
                    needRight: false
                },
                name: 'page/51/name',
                pageId: 51
            });
            
            $http({
                method: 'GET',
                url: '/api/new',
                params: {
                    date: $state.params.date,
                    page: $state.params.page,
                    fields: 'pictures.owner,pictures.thumbnail,pictures.votes,pictures.views,pictures.comments_count,pictures.name_html,pictures.name_text,' +
                            'item_pictures.thumbnail,item_pictures.name_html,item_pictures.name_text,' +
                            'item.name_html,item.name_default,item.description,item.produced,' +
                            'item.design,item.url,item.spec_editor_url,item.specs_url,item.upload_url,' +
                            'item.categories.url,item.categories.name_html,item.twins_groups.url'
                }
            }).then(function(response) {
                ctrl.paginator = response.data.paginator;
                ctrl.prev = response.data.prev;
                ctrl.current = response.data.current;
                ctrl.next = response.data.next;
                ctrl.groups = [];
                
                var repackedGroups = [];
                angular.forEach(response.data.groups, function(group) {
                    
                    var repackedGroup = {
                        type: group.type
                    };
                    
                    switch (group.type) {
                        case 'item':
                            repackedGroup = group;
                            break;
                        case 'pictures':
                            repackedGroup.chunks = ctrl.chunkBy(group.pictures, 6);
                            break;
                    }
                    
                    repackedGroups.push(repackedGroup);
                });
                ctrl.groups = repackedGroups;
                
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

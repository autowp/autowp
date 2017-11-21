import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { chunkBy } from 'chunk';

import './item';
import './list-item';

const CONTROLLER_NAME = 'NewController';
const STATE_NAME = 'new';

export class NewController {
    static $inject = ['$scope', '$http', '$state'];
    public paginator: autowp.IPaginator;
    public groups: any[] = [];
    public date: string;
    public prev: any;
    public next: any;
    public current: any;
    public chunks: any[] = [];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any
    ) {
        var ctrl = this;
            
        this.date = $state.params.date;
        
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
        }).then(function(response: ng.IHttpResponse<any>) {
            ctrl.paginator = response.data.paginator;
            ctrl.prev = response.data.prev;
            ctrl.current = response.data.current;
            ctrl.next = response.data.next;
            ctrl.groups = [];
            
            var repackedGroups: any = [];
            angular.forEach(response.data.groups, function(group) {
                
                var repackedGroup: any;
                
                switch (group.type) {
                    case 'item':
                        repackedGroup = group;
                        break;
                    case 'pictures':
                        repackedGroup = {
                            type: group.type,
                            chunks: chunkBy(group.pictures, 6)
                        };
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

        }, function(response: ng.IHttpResponse<any>) {
            notify.response(response);
        });
    }
    
}

angular.module(Module)
    .controller(CONTROLLER_NAME, NewController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/new/:date/:page',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
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
    ]);

export default CONTROLLER_NAME;

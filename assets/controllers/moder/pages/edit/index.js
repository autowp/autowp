import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';

const CONTROLLER_NAME = 'ModerPagesEditController';
const STATE_NAME = 'moder-pages-edit';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pages/edit/{id}',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', ACL_SERVICE_NAME,
        function($scope, $http, $state, Acl) {
            $scope.pageEnv({
                layout: {
                    isAdminPage: true,
                    blankPage: false,
                    needRight: false
                },
                pageId: 70
            });
            
            var ctrl = this;
            ctrl.item = null;
            
            ctrl.loading = 0;
            
            $http({
                method: 'GET',
                url: '/api/page/' + $state.params.id
            }).then(function(response) {
                ctrl.item = response.data;
            }, function() {
                $state.go('error-404');
            });
            
            function toPlainArray(pages, level) {
                var result = [];
                angular.forEach(pages, function(page, i) {
                    page.level = level;
                    page.moveUp = i > 0;
                    page.moveDown = i < pages.length-1;
                    result.push(page);
                    angular.forEach(toPlainArray(page.childs, level+1), function(child) {
                        result.push(child);
                    });
                });
                return result;
            }
            
            $http({
                method: 'GET',
                url: '/api/page'
            }).then(function(response) {
                ctrl.pages = toPlainArray(response.data.items, 0);
            });
            
            ctrl.save = function() {
                ctrl.loading++;
                $http({
                    method: 'PUT',
                    url: '/api/page/' + $state.params.id,
                    data: {
                        parent_id: ctrl.item.parent_id,
                        name: ctrl.item.name,
                        title: ctrl.item.title,
                        breadcrumbs: ctrl.item.breadcrumbs,
                        url: ctrl.item.url,
                        is_group_node: ctrl.item.is_group_node ? 1 : 0,
                        registered_only: ctrl.item.registered_only ? 1 : 0,
                        guest_only: ctrl.item.guest_only ? 1 : 0,
                        'class': ctrl.item['class']
                    }
                }).then(function(response) {
                    ctrl.loading--;
                }, function() {
                    ctrl.loading--;
                });
            };
            
        }
    ]);

export default CONTROLLER_NAME;

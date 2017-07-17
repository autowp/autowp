import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import ACL_SERVICE_NAME from 'services/acl';
import CONTENT_LANGUAGE_SERVICE from 'services/content-language';

const STATE_NAME = 'moder-item-parent';
const CONTROLLER_NAME = 'ModerItemParentController';

angular.module(Module)
    .config(['$stateProvider',
        function config($stateProvider) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/item-parent/{item_id}/{parent_id}',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: template,
                params: { 
                    tab: { dynamic: true }
                },
                resolve: {
                    access: [ACL_SERVICE_NAME, function (Acl) {
                        return Acl.isAllowed('car', 'move', 'unauthorized');
                    }]
                }
            });
        }
    ])
    .controller(CONTROLLER_NAME, [
        '$scope', '$http', '$state', '$translate', '$q', ACL_SERVICE_NAME, CONTENT_LANGUAGE_SERVICE,
        function($scope, $http, $state, $translate, $q, Acl, ContentLanguage) {
            
            var ctrl = this;
            
            ctrl.item = null;
            ctrl.parent = null;
            ctrl.itemParent = {};
            ctrl.languages = [];
            
            ctrl.typeOptions = [
                {
                    value: 0,
                    name: 'catalogue/stock-model'
                },
                {
                    value: 1,
                    name: 'catalogue/related'
                },
                {
                    value: 2,
                    name: 'catalogue/sport'
                },
                {
                    value: 3,
                    name: 'catalogue/design'
                }
            ];
            
            var promises = [];
            
            promises.push(
                $http({
                    method: 'GET',
                    url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id
                })
            );
            
            promises.push(
                $http({
                    method: 'GET',
                    url: '/api/item/' + $state.params.item_id,
                    params: {
                        fields: ['name_text', 'name_html'].join(',')
                    }
                })
            );
            
            promises.push(
                $http({
                    method: 'GET',
                    url: '/api/item/' + $state.params.parent_id,
                    params: {
                        fields: ['name_text', 'name_html'].join(',')
                    }
                })
            );
            
            promises.push(
                ContentLanguage.getList()
            );
            
            promises.push(
                $http({
                    method: 'GET',
                    url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id + '/language'
                })
            );
            
            $q.all(promises).then(function(responses) {
                ctrl.itemParent = responses[0].data;
                ctrl.item = responses[1].data;
                ctrl.parent = responses[2].data;
                
                angular.forEach(responses[3], function(language) {
                    ctrl.languages.push({
                        language: language,
                        name: null
                    });
                });
                
                angular.forEach(responses[4].data.items, function(languageData) {
                    angular.forEach(ctrl.languages, function(item) {
                        if (item.language == languageData.language) {
                            item.name = languageData.name;
                        }
                    });
                });
                
                $translate('item/type/'+ctrl.item.item_type_id+'/name').then(function(translation) {
                    $scope.pageEnv({
                        layout: {
                            isAdminPage: true,
                            blankPage: false,
                            needRight: false
                        },
                        pageId: 78,
                        args: {
                            CAR_ID: ctrl.item.id,
                            CAR_NAME: translation + ': ' + ctrl.item.name_text
                        }
                    });
                });
                
            }, function() {
                $state.go('error-404');
            });
            
            function reloadItemParent() {
                $http({
                    method: 'GET',
                    url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id
                }).then(function(response) {
                    ctrl.itemParent = response.data;
                });
            }
            
            ctrl.save = function() {
                
                var promises = [
                    $http({
                        method: 'PUT',
                        url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id,
                        data: {
                            catname: ctrl.itemParent.catname,
                            type_id: ctrl.itemParent.type_id
                        }
                    })
                ];
                
                angular.forEach(ctrl.languages, function(language) {
                    language.invalidParams = null;
                    promises.push(
                        $http({
                            method: 'PUT',
                            url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id + '/language/' + language.language,
                            data: {
                                name: language.name
                            }
                        }).then(function() {}, function(response) {
                            language.invalidParams = response.data.invalid_params;
                        })
                    );
                });
                
                $q.all(promises).then(reloadItemParent);
            };
        }
    ]);

export default STATE_NAME;

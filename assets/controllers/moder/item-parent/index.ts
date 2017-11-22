import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import { ContentLanguageService } from 'services/content-language';

const STATE_NAME = 'moder-item-parent';
const CONTROLLER_NAME = 'ModerItemParentController';

export class ModerItemParentController {
    static $inject = ['$scope', '$http', '$state', '$translate', '$q', 'AclService', 'ContentLanguageService'];
    
    public item: any = null;
    public parent: any = null;
    public itemParent: any;
    public languages: any[] = [];
    public typeOptions = [
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
    private promises: any[] = [];

    constructor(
        private $scope: autowp.IControllerScope, 
        private $http: ng.IHttpService,
        private $state: any,
        private $translate: ng.translate.ITranslateService,
        private $q: ng.IQService,
        private Acl: AclService,
        private ContentLanguage: ContentLanguageService
    ) {
        var self = this;

        this.promises.push(
            $http({
                method: 'GET',
                url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id
            })
        );
        
        this.promises.push(
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.item_id,
                params: {
                    fields: ['name_text', 'name_html'].join(',')
                }
            })
        );
        
        this.promises.push(
            $http({
                method: 'GET',
                url: '/api/item/' + $state.params.parent_id,
                params: {
                    fields: ['name_text', 'name_html'].join(',')
                }
            })
        );
        
        this.promises.push(
            this.ContentLanguage.getList()
        );
        
        this.promises.push(
            this.$http({
                method: 'GET',
                url: '/api/item-parent/' + $state.params.item_id + '/' + $state.params.parent_id + '/language'
            })
        );
        
        $q.all(this.promises).then(function(responses: ng.IHttpResponse<any>[]) {
            self.itemParent = responses[0].data;
            self.item = responses[1].data;
            self.parent = responses[2].data;
            
            angular.forEach(responses[3], function(language) {
                self.languages.push({
                    language: language,
                    name: null
                });
            });
            
            angular.forEach(responses[4].data.items, function(languageData: any) {
                angular.forEach(self.languages, function(item: any) {
                    if (item.language == languageData.language) {
                        item.name = languageData.name;
                    }
                });
            });
            
            self.$translate('item/type/'+self.item.item_type_id+'/name').then(function(translation: string) {
                self.$scope.pageEnv({
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/78/name',
                    pageId: 78,
                    args: {
                        CAR_ID: self.item.id,
                        CAR_NAME: translation + ': ' + self.item.name_text
                    }
                });
            });
            
        }, function() {
            self.$state.go('error-404');
        });
    }
    
    public reloadItemParent() {
        var self = this;
        this.$http({
            method: 'GET',
            url: '/api/item-parent/' + this.$state.params.item_id + '/' + this.$state.params.parent_id
        }).then(function(response: ng.IHttpResponse<any>) {
            self.itemParent = response.data;
        });
    }
    
    public save() {
        
        var promises: any[]  = [
            this.$http({
                method: 'PUT',
                url: '/api/item-parent/' + this.$state.params.item_id + '/' + this.$state.params.parent_id,
                data: {
                    catname: this.itemParent.catname,
                    type_id: this.itemParent.type_id
                }
            })
        ];
        
        var self = this;
        angular.forEach(this.languages, function(language: any) {
            language.invalidParams = null;
            var promise = self.$http({
                method: 'PUT',
                url: '/api/item-parent/' + self.$state.params.item_id + '/' + self.$state.params.parent_id + '/language/' + language.language,
                data: {
                    name: language.name
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                
            }, function(response: ng.IHttpResponse<any>) {
                language.invalidParams = response.data.invalid_params;
            });
            promises.push(promise);
        });
        
        this.$q.all(promises).then(function() {
            self.reloadItemParent();
        });
    };
}

angular.module(Module)
.controller(CONTROLLER_NAME, ModerItemParentController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/item-parent/{item_id}/{parent_id}',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                params: { 
                    tab: { dynamic: true }
                },
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.isAllowed('car', 'move', 'unauthorized');
                    }]
                }
            });
        }
    ]);


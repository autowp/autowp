import * as angular from 'angular';
import Module from 'app.module';
import './styles.less';

interface IAutowpModerPictureMoveItemDirectiveScope extends ng.IScope {
    loading: boolean;
    childs: any[];
    toggleItem: Function;
}


class AutowpModerPictureMoveItemDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        item: '=',
        selectItem: '='
    };
    template = require('./template.html');

    constructor(private $http: ng.IHttpService) {
    }

    link = (scope: IAutowpModerPictureMoveItemDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        
        var self = this;
        
        scope.childs = [];
        scope.loading = false;
        
        scope.toggleItem = function(item: any) {
            item.expanded = !item.expanded;
            
            if (item.expanded) {
                scope.loading = true;
                self.$http({
                    method: 'GET',
                    url: '/api/item-parent',
                    params: {
                        type_id: 1,
                        parent_id: item.item_id,
                        fields: 'item.name_html,item.childs_count',
                        limit: 500
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    scope.loading = false;
                    scope.childs = response.data.items;
                });
            }
        };
        
    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($http: ng.IHttpService) => new AutowpModerPictureMoveItemDirective($http);
        directive.$inject = ['$http'];
        return directive;
    }
}

angular.module(Module).directive('autowpModerPictureMoveItem', AutowpModerPictureMoveItemDirective.factory());

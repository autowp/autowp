import * as angular from "angular";
import Module from 'app.module';
import { PageService } from 'services/page';

function replaceArgs(str: string, args: any) {
    angular.forEach(args, function(value, key) {
        str = str.replace(key, ''+value);
    });
    return str;
}

class AutowpBreadcrumbsDirective implements ng.IDirective {
    restrict = 'E';
    scope = {};
    template = require('./template.html');
    items: any[] = [];

    constructor(private PageService: PageService, private $translate: any) {
    }

    link = (scope: ng.IScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
      
        var self = this;
        
        var handler = function() {
            var current = self.PageService.getCurrent();
            self.items = [];
            if (current) {
                var args = self.PageService.getCurrentArgs();
                self.PageService.getPath(current).then(function(path: any) {
                    angular.forEach(path, function(item: any) {
                        item.url = replaceArgs(item.url, args);
                        self.items.push(item);
                        self.$translate('page/' + item.id + '/name').then(function (translation: string) {
                            item.name_translated = replaceArgs(translation, args);
                        });
                    });
                });
            }
        };
        
        self.PageService.bind('currentChanged', handler);
        
        scope.$on('$destroy', function () {
            self.PageService.unbind('sent', handler);
        });
    }

    static factory(): ng.IDirectiveFactory {
        const directive = (PageService: PageService, $translate: any) => new AutowpBreadcrumbsDirective(PageService, $translate);
        directive.$inject = ['PageService', '$translate'];
        return directive;
    }
}

angular.module(Module).directive('autowpBreadcrumbs', AutowpBreadcrumbsDirective.factory());

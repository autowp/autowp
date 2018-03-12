import * as angular from "angular";
import Module from 'app.module';
import { PageService } from 'services/page';

function replaceArgs(str: string, args: any) {
    angular.forEach(args, function(value, key) {
        str = str.replace(String(key), String(value));
    });
    return str;
}

interface IAutowpBreadcrumbsDirectiveScope extends ng.IScope {
    items: any[];
}


class AutowpBreadcrumbsDirective implements ng.IDirective {
    restrict = 'E';
    scope = {};
    template = require('./template.html');

    constructor(
        private PageService: PageService,
        private $translate: ng.translate.ITranslateService
    ) {
    }

    link = (scope: IAutowpBreadcrumbsDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {

        var self = this;

        var handler = function() {
            var current = self.PageService.getCurrent();
            scope.items = [];
            if (current) {
                var args = self.PageService.getCurrentArgs();
                self.PageService.getPath(current).then(function(path: any) {
                    angular.forEach(path, function(item: any) {
                        item.url = replaceArgs(item.url, args);
                        scope.items.push(item);
                        self.$translate('page/' + item.id + '/breadcrumbs').then(function (translation: string) {
                            item.name_translated = replaceArgs(translation, args);
                        }, function() {
                            self.$translate('page/' + item.id + '/name').then(function (translation: string) {
                                item.name_translated = replaceArgs(translation, args);
                            });
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
        const directive = (PageService: PageService, $translate: ng.translate.ITranslateService) => new AutowpBreadcrumbsDirective(PageService, $translate);
        directive.$inject = ['PageService', '$translate'];
        return directive;
    }
}

angular.module(Module).directive('autowpBreadcrumbs', AutowpBreadcrumbsDirective.factory());

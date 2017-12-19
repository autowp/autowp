import * as angular from "angular";
import Module from '../../app.module';
import * as showdown from 'showdown';

class AutowpMarkdownDirective implements ng.IDirective {
    public restrict = 'AE';
    
    private markdownConverter: showdown.Converter;
    
    constructor(
        private $sanitize: ng.sanitize.ISanitizeService
    ) {
        this.markdownConverter = new showdown.Converter({});
    }

    static factory(): ng.IDirectiveFactory {
        const directive = ($sanitize: ng.sanitize.ISanitizeService) => new AutowpMarkdownDirective($sanitize);
        directive.$inject = ['$sanitize'];
        return directive;
    }
    
    link = (scope: ng.IScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
        if (attrs.autowpMarkdown) {
            var self = this;
            scope.$watch(attrs.autowpMarkdown, function (newVal: string) {
                var html = newVal ? self.$sanitize(self.markdownConverter.makeHtml(newVal)) : '';
                element.html(html);
            });
        } else {
            var html = this.$sanitize(this.markdownConverter.makeHtml(element.text()));
            element.html(html);
        }
    }
}

angular.module(Module).directive('autowpMarkdown', AutowpMarkdownDirective.factory());

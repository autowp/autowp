import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import PageServiceName from 'services/page';

function replaceArgs(str, args) {
    angular.forEach(args, function(value, key) {
        str = str.replace(key, value);
    });
    return str;
}

angular.module(Module)
    .directive('autowpBreadcrumbs', function() {
        return {
            restirct: 'E',
            scope: {
                
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [PageServiceName, '$scope', '$translate',
                function(PageService, $scope, $translate) {
                    var ctrl = this;
                    
                    ctrl.items = [];
                    
                    var handler = function() {
                        var current = PageService.getCurrent();
                        ctrl.items = [];
                        if (current) {
                            var args = PageService.getCurrentArgs();
                            PageService.getPath(current).then(function(path) {
                                console.log(path);
                                angular.forEach(path, function(item) {
                                    ctrl.items.push(item);
                                    $translate('page/' + item.id + '/name').then(function (translation) {
                                        item.name_translated = replaceArgs(translation, args);
                                    });
                                });
                            });
                        }
                    };
                    
                    PageService.bind('currentChanged', handler);
                    
                    $scope.$on('$destroy', function () {
                        PageService.unbind('sent', handler);
                    });
                }
            ]
        };
    });
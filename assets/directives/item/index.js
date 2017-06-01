import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import './styles.less';

angular.module(Module)
    .directive('autowpItem', function() {
        return {
            restirct: 'E',
            scope: {
                item: '=',
                disableTitle: '=',
                disableDescription: '=',
                disableDetailsLink: '='
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [
                function() {
                    var ctrl = this;
                    
                    ctrl.havePhoto = function(item) {
                        var found = false;
                        angular.forEach(item.preview_pictures, function(picture) {
                            if (picture.thumbnail) {
                                found = true;
                                return false;
                            }
                        });
                        return found;
                    };
                    
                    ctrl.canHavePhoto = function(item) {
                        return [1, 2, 5, 6, 7].indexOf(item.item_type_id) != -1;
                    };
                    
                    ctrl.thumbnailClasses = function(picture, $index) {
                        
                        var thumbColumns = 4;
                        var singleThumbPart = Math.round(12 / thumbColumns);
                        
                        var classes = {};
                        var col = picture.largeFormat && $index === 0  ? 2*singleThumbPart : singleThumbPart;
                        var colSm = picture.largeFormat && $index === 0  ? 12 : 6;
                        
                        classes['col-md-'+col] = true;
                        classes['col-sm-'+colSm] = true;
                        
                        return classes;
                    };
                }
            ]
        };
    });
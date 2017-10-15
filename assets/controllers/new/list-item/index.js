import angular from 'angular';
import Module from 'app.module';
import template from './template.html';
import './styles.less';
import ACL_SERVICE_NAME from 'services/acl';

angular.module(Module)
    .directive('autowpNewItem', function() {
        return {
            restirct: 'E',
            scope: {
                item: '<',
                pictures: '<',
                totalPictures: '<',
                date: '<'
            },
            template: template,
            transclude: true,
            controllerAs: 'ctrl',
            controller: [ACL_SERVICE_NAME, '$scope',
                function(Acl, $scope) {
                    var ctrl = this;
                    
                    ctrl.havePhoto = function() {
                        var found = false;
                        angular.forEach($scope.pictures, function(picture) {
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
                        
                        var thumbColumns = 6;
                        var singleThumbPart = Math.round(12 / thumbColumns);
                        
                        var classes = {};
                        var col = picture.large && $index === 0  ? 2*singleThumbPart : singleThumbPart;
                        var colSm = picture.large && $index === 0  ? 12 : 6;
                        
                        classes['col-md-'+col] = true;
                        classes['col-sm-'+colSm] = true;
                        
                        return classes;
                    };
                    
                    ctrl.is_moder = false;
                    Acl.inheritsRole('moder').then(function() {
                        ctrl.is_moder = true;
                    }, function() {
                        ctrl.is_moder = false;
                    });
                }
            ]
        };
    });
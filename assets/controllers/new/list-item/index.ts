import * as angular from 'angular';
import Module from 'app.module';
import './styles.scss';
import { AclService } from 'services/acl';

interface NewItemDirectiveScope extends ng.IScope {
    pictures: any[];
}

export class NewItemDirectiveController {
    static $inject = ['AclService', '$scope'];

    public is_moder: boolean = false;

    constructor(
        private Acl: AclService,
        private $scope: NewItemDirectiveScope
    ) {
        var self = this;

        this.Acl.inheritsRole('moder').then(function(isModer: boolean) {
            self.is_moder = isModer;
        }, function() {
            self.is_moder = false;
        });
    }

    public canHavePhoto(item: any) {
        return [1, 2, 5, 6, 7].indexOf(item.item_type_id) != -1;
    }

    public thumbnailClasses(picture: any, $index: number) {

        var thumbColumns = 6;
        var singleThumbPart = Math.round(12 / thumbColumns);

        var classes: any = {};
        var col: number = picture.large && $index === 0  ? 2*singleThumbPart : singleThumbPart;
        var colSm: number = picture.large && $index === 0  ? 12 : 6;

        classes['col-md-'+col] = true;
        classes['col-sm-'+colSm] = true;

        return classes;
    }

    public havePhoto() {
        var found = false;
        angular.forEach(this.$scope.pictures, function(picture: any) {
            if (picture.thumbnail) {
                found = true;
                return false;
            }
        });
        return found;
    };
};

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
            template: require('./template.html'),
            transclude: true,
            controllerAs: 'ctrl',
            controller: NewItemDirectiveController
        };
    });

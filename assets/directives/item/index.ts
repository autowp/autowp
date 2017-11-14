import * as angular from "angular";
import Module from 'app.module';
import './styles.less';
import { AclService } from 'services/acl';


interface IAutowpItemDirectiveScope extends ng.IScope {
    item: any;
}

class AutowpItemDirective implements ng.IDirective {
    restrict = 'E';
    scope = {
        item: '=',
        disableTitle: '=',
        disableDescription: '=',
        disableDetailsLink: '='
    };
    template = require('./template.html');
    public is_moder: boolean;

    constructor(private AclService: AclService) {
    }
  
    public havePhoto(item: any) {
        var found = false;
        angular.forEach(item.preview_pictures, function(picture: any) {
            if (picture.thumbnail) {
                found = true;
                return false;
            }
        });
        return found;
    }
  
    public canHavePhoto(item: any) {
        return [1, 2, 5, 6, 7].indexOf(item.item_type_id) != -1;
    };
  
    public thumbnailClasses(picture: any, $index: number) {
        
        var thumbColumns = 4;
        var singleThumbPart = Math.round(12 / thumbColumns);
        
        var classes: any = {};
        var col = picture.large && $index === 0  ? 2*singleThumbPart : singleThumbPart;
        var colSm = picture.large && $index === 0  ? 12 : 6;
        
        classes['col-md-'+col] = true;
        classes['col-sm-'+colSm] = true;
        
        return classes;
    };

    link = (scope: IAutowpItemDirectiveScope, element: ng.IAugmentedJQuery, attrs: ng.IAttributes, ctrl: any) => {
      
        var self = this;
        
        this.is_moder = false;
        this.AclService.inheritsRole('moder').then(function(inherits) {
            self.is_moder = !!inherits;
        }, function() {
            self.is_moder = false;
        });
    }

    static factory(): ng.IDirectiveFactory {
        const directive = (AclService: AclService) => new AutowpItemDirective(AclService);
        directive.$inject = ['AclService'];
        return directive;
    }
}

angular.module(Module).directive('autowpItem', AutowpItemDirective.factory());

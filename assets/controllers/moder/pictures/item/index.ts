import * as angular from 'angular';
import Module from 'app.module';
import { PerspectiveService } from 'services/perspective';
import { PictureItemService } from 'services/picture-item';
import { AclService } from 'services/acl';
import { sprintf } from "sprintf-js";
import './crop';
import './move';
import './area';

const CONTROLLER_NAME = 'ModerPicturesItemController';
const STATE_NAME = 'moder-pictures-item';

export class ModerPicturesItemController {
    static $inject = ['$scope', '$http', '$state', '$q', '$translate', '$element', 'PerspectiveService', 'PictureItemService'];

    public picture: any = null;
    public replaceLoading: boolean = false;
    public pictureItemLoading: boolean = false;
    public similarLoading: boolean = false;
    public repairLoading: boolean = false;
    public statusLoading: boolean = false;
    public copyrightsLoading: boolean = false;
    public specialNameLoading: boolean = false;
    public last_item: any = null;
    public banPeriods = {
        1: 'ban/period/hour',
        2: 'ban/period/2-hours',
        4: 'ban/period/4-hours',
        8: 'ban/period/8-hours',
        16: 'ban/period/16-hours',
        24: 'ban/period/day',
        48: 'ban/period/2-days'
    };
    public banPeriod: number = 1;
    public banReason: string|null = null;
    public perspectives: any[] = [];
    public pictureVoted: Function;

    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService,
        private $translate: ng.translate.ITranslateService,
        private $element: any,
        private PerspectiveService: PerspectiveService,
        private PictureItemService: PictureItemService
    ) {
        var self = this;

        this.loadPicture( function() {
            self.$translate( 'moder/picture/picture-n-%s' ).then( function( translation ) {
                $scope.pageEnv( {
                    layout: {
                        isAdminPage: true,
                        blankPage: false,
                        needRight: false
                    },
                    name: 'page/72/name',
                    pageId: 72,
                    args: {
                        PICTURE_ID: self.picture.id,
                        PICTURE_NAME: sprintf( translation, self.picture.id )
                    }
                } );
            } );
        } );


        this.PerspectiveService.getPerspectives().then( function( perspectives: any[] ) {
            self.perspectives = perspectives;
        } );

        this.pictureVoted = function() {
            self.loadPicture();
        };
    }

    public loadPicture( callback?: Function ) {
        var self = this;
        this.$http( {
            method: 'GET',
            url: '/api/picture/' + this.$state.params.id,
            params: {
                fields: ['owner', 'thumb', 'add_date', 'iptc', 'exif', 'image',
                    'items.item.name_html', 'items.item.brands.name_html', 'items.area',
                    'special_name', 'copyrights', 'change_status_user',
                    'rights', 'moder_votes', 'moder_voted', 'is_last', 'views',
                    'accepted_count', 'similar.picture.thumb',
                    'replaceable', 'siblings.name_text', 'ip.rights', 'ip.blacklist'].join( ',' )
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.picture = response.data;

            if ( callback ) {
                callback();
            }
        }, function() {
            self.$state.go( 'error-404' );
        } );

        this.$http( {
            method: 'GET',
            url: '/api/item',
            params: {
                last_item: 1,
                fields: 'name_html',
                limit: 1
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.last_item = response.data.items.length ? response.data.items[0] : null;
        } );
    }

    public hasItem( itemId: number ): boolean {
        var found = false;
        angular.forEach( this.picture.items, function( item ) {
            if ( item.item_id == itemId ) {
                found = true;
            }
        } );

        return found;
    }

    public addItem( item: any, type: number ) {
        this.pictureItemLoading = true;
        var self = this;
        this.PictureItemService.create( this.$state.params.id, item.id, type, {} ).then( function() {
            self.loadPicture( function() {
                self.pictureItemLoading = false;
            } );
        }, function() {
            self.pictureItemLoading = false;
        } );
    }

    public moveItem( type: number, srcItemId: number, dstItemId: number ) {
        this.pictureItemLoading = true;
        var self = this;
        this.PictureItemService.changeItem( this.$state.params.id, type, srcItemId, dstItemId ).then( function() {
            self.loadPicture( function() {
                self.pictureItemLoading = false;
            } );
        }, function() {
            self.pictureItemLoading = false;
        } );
    }

    public saveSpecialName() {
        this.specialNameLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                special_name: this.picture.special_name
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.specialNameLoading = false;
        }, function() {
            self.specialNameLoading = false;
        } );
    }

    public saveCopyrights() {
        this.copyrightsLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                copyrights: this.picture.copyrights
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.copyrightsLoading = false;
        }, function() {
            self.copyrightsLoading = false;
        } );
    }

    public unacceptPicture() {
        this.statusLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                status: 'inbox'
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.statusLoading = false;
            } );
        }, function() {
            self.statusLoading = false;
        } );
    };

    public acceptPicture() {
        this.statusLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                status: 'accepted'
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.statusLoading = false;
            } );
        }, function() {
            self.statusLoading = false;
        } );
    }

    public deletePicture() {
        this.statusLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                status: 'removing'
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.statusLoading = false;
            } );
        }, function() {
            self.statusLoading = false;
        } );
    }

    public restorePicture() {
        this.statusLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                status: 'inbox'
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.statusLoading = false;
            } );
        }, function() {
            self.statusLoading = false;
        } );
    }

    public normalizePicture() {
        this.repairLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id + '/normalize'
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.repairLoading = false;
            } );
        }, function() {
            self.repairLoading = false;
        } );
    }

    public flopPicture() {
        this.repairLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id + '/flop'
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.repairLoading = false;
            } );
        }, function() {
            self.repairLoading = false;
        } );
    }

    public repairPicture() {
        this.repairLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id + '/repair'
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.repairLoading = false;
            } );
        }, function() {
            self.repairLoading = false;
        } );
    }

    public correctFileNames() {
        this.repairLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id + '/correct-file-names'
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.repairLoading = false;
            } );
        }, function() {
            self.repairLoading = false;
        } );
    }

    public cancelSimilar() {
        this.similarLoading = true;
        var self = this;
        this.$http( {
            method: 'DELETE',
            url: '/api/picture/' + this.$state.params.id + '/similar/' + this.picture.similar.picture_id
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.similarLoading = false;
            } );
        }, function() {
            self.similarLoading = false;
        } );
    }

    public savePerspective(item: any) {
        this.PictureItemService.setPerspective(
            item.picture_id,
            item.item_id,
            item.type,
            item.perspective_id
        );
    }

    public deletePictureItem( item: any ) {
        this.pictureItemLoading = true;
        var self = this;
        this.PictureItemService.remove( item.picture_id, item.item_id, item.type ).then( function() {
            self.loadPicture( function() {
                self.pictureItemLoading = false;
            } );
        }, function() {
            self.pictureItemLoading = false;
        } );
    }

    public cancelReplace() {
        this.replaceLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id,
            data: {
                replace_picture_id: ''
            }
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.replaceLoading = false;
            } );
        }, function() {
            self.replaceLoading = false;
        } );
    }

    public acceptReplace() {
        this.replaceLoading = true;
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.$state.params.id + '/accept-replace'
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture( function() {
                self.replaceLoading = false;
            } );
        }, function() {
            self.replaceLoading = false;
        } );
    }

    public removeFromBlacklist( ip: string ) {
        var self = this;
        this.$http( {
            method: 'DELETE',
            url: '/api/traffic/blacklist/' + ip
        } ).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture();
        } );
    }

    public addToBlacklist( ip: string ) {
        var self = this;
        this.$http({
            method: 'POST',
            url: '/api/traffic/blacklist',
            data: {
                ip: ip,
                period: self.banPeriod,
                reason: self.banReason
            }
        }).then( function( response: ng.IHttpResponse<any> ) {
            self.loadPicture();
        });
    }
}

angular.module( Module )
    .controller( CONTROLLER_NAME, ModerPicturesItemController )
    .config( ['$stateProvider',
        function config( $stateProvider: any ) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require( './template.html' ),
                resolve: {
                    access: ['AclService', function( Acl: AclService ) {
                        return Acl.inheritsRole( 'moder', 'unauthorized' );
                    }]
                }
            } );
        }
    ] );

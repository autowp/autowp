import * as angular from 'angular';
import Module from 'app.module';
import { AclService } from 'services/acl';
import * as $ from "jquery";
require( 'jcrop-0.9.12/css/jquery.Jcrop.css' );
require( 'jcrop-0.9.12/js/jquery.Jcrop' );
import { sprintf } from "sprintf-js";

const CONTROLLER_NAME = 'ModerPicturesItemCropController';
const STATE_NAME = 'moder-pictures-item-crop';

interface Crop {
    w: number,
    h: number,
    x: number,
    y: number
}

export class ModerPicturesItemCropController {
    static $inject = ['$scope', '$element', '$http', '$state'];

    public aspect: string = '';
    public resolution: string = '';
    private jcrop: any;
    private currentCrop: Crop = {
        w: 0,
        h: 0,
        x: 0,
        y: 0
    };
    private minSize = [400, 300];
    public picture: any;

    constructor(
        private $scope: autowp.IControllerScope,
        private $element: any,
        private $http: ng.IHttpService,
        private $state: any
    ) {
        this.$scope.pageEnv( {
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/148/name',
            pageId: 148
        } );

        var self = this;

        this.$http( {
            method: 'GET',
            url: '/api/picture/' + this.$state.params.id,
            params: {
                fields: 'crop,image'
            }
        } ).then( function( response ) {
            self.picture = response.data;

            var $body = $( $element[0] ).find( '.crop-area' );
            var $img = $body.find( 'img' );

            self.jcrop = null;
            if ( self.picture.crop ) {
                self.currentCrop = {
                    w: self.picture.crop.width,
                    h: self.picture.crop.height,
                    x: self.picture.crop.left,
                    y: self.picture.crop.top
                };
            } else {
                self.currentCrop = {
                    w: self.picture.width,
                    h: self.picture.height,
                    x: 0,
                    y: 0
                };
            }
            
            let bWidth = $body.width() || 1;

            var scale = self.picture.width / bWidth,
                width = self.picture.width / scale,
                height = self.picture.height / scale;

            $img.css( {
                width: width,
                height: height
            } ).on( 'load', function() {

                // sometimes Jcrop fails without delay
                setTimeout( function() {

                    self.jcrop = $.Jcrop( $img[0], {
                        onSelect: function( c: Crop ) {
                            self.currentCrop = c;
                            self.updateSelectionText();
                        },
                        setSelect: [
                            self.currentCrop.x,
                            self.currentCrop.y,
                            self.currentCrop.x + self.currentCrop.w,
                            self.currentCrop.y + self.currentCrop.h
                        ],
                        minSize: self.minSize,
                        boxWidth: width,
                        boxHeight: height,
                        trueSize: [self.picture.width, self.picture.height],
                        keySupport: false
                    } );

                }, 100 );
            } );
        } );


    }
    
    public selectAll() {
        this.jcrop.setSelect( [0, 0, this.picture.width, this.picture.height] );
    };
    
    public saveCrop() {
        var self = this;
        this.$http( {
            method: 'PUT',
            url: '/api/picture/' + this.picture.id,
            data: {
                crop: {
                    left: Math.round( this.currentCrop.x ),
                    top: Math.round( this.currentCrop.y ),
                    width: Math.round( this.currentCrop.w ),
                    height: Math.round( this.currentCrop.h )
                }
            }
        } ).then( function() {
            self.$state.go( 'moder-pictures-item', {
                id: self.picture.id
            } );
        } );

    }

    private updateSelectionText() {
        var text = Math.round( this.currentCrop.w ) + '×' + Math.round( this.currentCrop.h );
        var pw = 4;
        var ph = pw * this.currentCrop.h / this.currentCrop.w;
        var phRound = Math.round( ph * 10 ) / 10;

        this.aspect = pw + ':' + phRound;
        this.resolution = text;
    }
}

angular.module( Module )
    .controller( CONTROLLER_NAME, ModerPicturesItemCropController )
    .config( ['$stateProvider',
        function config( $stateProvider: any ) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/crop',
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
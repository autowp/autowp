import * as angular from 'angular';
import Module from 'app.module';
import * as $ from "jquery";
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');
import { sprintf } from "sprintf-js";
import { PictureItemService } from 'services/picture-item';
import { AclService } from 'services/acl';

const CONTROLLER_NAME = 'ModerPicturesItemAreaController';
const STATE_NAME = 'moder-pictures-item-area';

interface Crop {
    w: number,
    h: number,
    x: number,
    y: number
}

export class ModerPicturesItemAreaController {
    static $inject = ['$scope', '$element', '$http', '$state', '$q', 'PictureItemService'];

    public aspect: string = '';
    public resolution: string = '';
    private jcrop: any;
    private currentCrop: Crop = {
        w: 0,
        h: 0,
        x: 0,
        y: 0
    };
    private minSize = [50, 50];
    public picture: any;

    constructor(
        private $scope: autowp.IControllerScope,
        private $element: any,
        private $http: ng.IHttpService,
        private $state: any,
        private $q: ng.IQService,
        private PictureItemService: PictureItemService
    ) {
        this.$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/148/name',
            pageId: 148
        });
        
        
        var getPicturePromise = this.$http({
            method: 'GET',
            url: '/api/picture/' + $state.params.id,
            params: {
                fields: 'crop,image'
            }
        });
        
        var getPictureItemPromise = this.PictureItemService.get($state.params.id, $state.params.item_id, $state.params.type, {
            fields: 'area'
        });
        
        var self = this;
        
        $q.all([getPicturePromise, getPictureItemPromise]).then(function(data: any[]) {
            var area = data[1].data.area;
            
            var response = data[0];
            
            self.picture = response.data;
            
            var $body = $($element[0]).find('.crop-area');
            var $img = $body.find('img');
            
            self.jcrop = null;
            if (area) {
                self.currentCrop = {
                    w: area.width,
                    h: area.height,
                    x: area.left,
                    y: area.top
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
            
            $img.css({
                width: width,
                height: height
            }).on('load', function() {
                
                // sometimes Jcrop fails without delay
                setTimeout(function() {
    
                    self.jcrop = $.Jcrop($img[0], {
                        onSelect: function(c: Crop) {
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
                    });
                    
                }, 100);
            });
        }, function() {
            self.$state.go('error-404');
        });
    }
    
    public selectAll() {
        this.jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
    };
    
    private updateSelectionText() {
        var text = Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
        var pw = 4;
        var ph = pw * this.currentCrop.h / this.currentCrop.w;
        var phRound = Math.round(ph * 10) / 10;
        
        this.aspect = pw+':'+phRound;
        this.resolution = text;
    }
    
    public saveCrop() {
        
        var area = {
            left:   Math.round(this.currentCrop.x),
            top:    Math.round(this.currentCrop.y),
            width:  Math.round(this.currentCrop.w),
            height: Math.round(this.currentCrop.h)
        };
        
        var self = this;
        this.PictureItemService.setArea(this.$state.params.id, this.$state.params.item_id, this.$state.params.type, area).then(function() {
            self.$state.go('moder-pictures-item', {
                id: self.picture.id
            });
        }, function() {
            
        });
    }
}

angular.module(Module)
    .controller(CONTROLLER_NAME, ModerPicturesItemAreaController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/moder/pictures/{id}/area?item_id&type',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html'),
                resolve: {
                    access: ['AclService', function (Acl: AclService) {
                        return Acl.inheritsRole('moder', 'unauthorized');
                    }]
                }
            });
        }
    ]);


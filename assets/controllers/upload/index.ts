import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';
import { chunkBy } from 'chunk';

import './select';

import { CropDialog } from 'crop-dialog';

const CONTROLLER_NAME = 'UploadController';
const STATE_NAME = 'upload';

export class UploadController {
    static $inject = ['$scope', '$http', '$state', '$q', 'ItemService', 'Upload'];
    
    public selected: boolean;
    public selectionName: string;
    private replace: any;
    public file: any;
    public note: string;
    public progress: any[] = [];
    public pictures: any[] = [];
    public picturesChunks: any[] = [];
    public item: autowp.IItem;
    public formHidden: boolean = false;
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
        private $q: ng.IQService,
        private ItemService: ItemService,
        private Upload: ng.angularFileUpload.IUploadService
    ) {
        this.$scope.pageEnv({
            layout: {
                blankPage: false,
                needRight: false
            },
            name: 'page/29/name',
            pageId: 29
        });
        
        let self = this;
        
        let replace = parseInt($state.params.replace);
        if (replace) {
            this.$http({
                method: 'GET',
                url: '/api/picture/' + replace,
                params: {
                    fields: 'name_html'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                self.replace = response.data;
                
                self.selected = true;
                self.selectionName = self.replace.name_html;
                
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        }
        
        let itemId = parseInt($state.params.item_id);
        if (itemId) {
            this.ItemService.getItem(itemId, {
                fields: 'name_html'
            }).then(function(item: autowp.IItem) {
                self.selected = true;
                self.item = item;
                self.selectionName = item.name_html;
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        }
    }
    
    public submit()
    {
        this.progress = [];
        
        this.formHidden = true;

        var xhrs: any[] = [];
        
        if (this.replace) {
            
            let promise = this.uploadFile(this.file);
            
            xhrs.push(promise);
            
        } else {
            for (let file of this.file) {
                
                let promise = this.uploadFile(file);
                
                xhrs.push(promise);
            }
        }

        let self = this;
        this.$q.all(xhrs).then(function() {
            self.formHidden = false;
            self.file = undefined;
        }, function() {
            self.formHidden = false;
            self.file = undefined;
        });
    }
    
    private uploadFile(file: any)
    {
        let progress = {
            filename: file.fileName || file.name,
            percentage: 0,
            success: false,
            failed: false,
            invalidParams: {}
        };
        
        this.progress.push(progress);
        
        var self = this;
        
        let itemId = this.item ? this.item.id : undefined;
        let perspectiveId = self.$state.params.perspective_id;
        if (!perspectiveId) {
            perspectiveId = undefined;
        }
        
        var promise = this.Upload.upload({
            method: 'POST',
            url: '/api/picture',
            data: {
                file: file, 
                comment: this.note,
                item_id: itemId,
                replace_picture_id: self.replace ? self.replace.id : undefined,
                perspective_id: perspectiveId
            }
        }).then(function (response: ng.IHttpResponse<any>) {
            progress.percentage = 100;
            progress.success = true;
            
            let location = response.headers('Location');
            
            self.$http({
                method: 'GET',
                url: location,
                params: {
                    fields: 'crop,image_gallery_full,thumbnail,votes,views,comments_count,perspective_item,name_html,name_text'
                }
            }).then(function(response: ng.IHttpResponse<any>) {
                let picture = response.data;
                self.pictures.push(picture);
                self.picturesChunks = chunkBy(self.pictures, 6);
                
            }, function(response: ng.IHttpResponse<any>) {
                notify.response(response);
            });
            
        }, function (response: ng.IHttpResponse<any>) {
            
            progress.percentage = 100;
            progress.failed = true;
            
            progress.invalidParams = response.data.invalid_params;
        }, function (evt) {
            progress.percentage = Math.round(100.0 * evt.loaded / evt.total);
        }); 
        
        return promise;
    }
    
    public crop(picture: any)
    {
        let self = this;
        let cropDialog = new CropDialog({
            sourceUrl: picture.image_gallery_full.src,
            crop: {
                x: picture.crop ? picture.crop.left : 0, 
                y: picture.crop ? picture.crop.top : 0, 
                w: picture.crop ? picture.crop.width : picture.width, 
                h: picture.crop ? picture.crop.height : picture.height
            },
            width: picture.width,
            height: picture.height,
            onSave: function(crop: any, callback: Function) {
                self.$http({
                    method: 'PUT',
                    url: '/api/picture/' + picture.id,
                    data: {
                        crop: {
                            left: crop.x,
                            top: crop.y,
                            width: crop.w,
                            height: crop.h
                        }
                    }
                }).then(function() {
                    
                    self.$http({
                        method: 'GET',
                        url: '/api/picture/' + picture.id,
                        params: {
                            fields: 'crop,thumbnail'
                        }
                    }).then(function(response: ng.IHttpResponse<any>) {
                        
                        picture.crop = response.data.crop;
                        picture.thumbnail = response.data.thumbnail;
                        
                    }, function(response: ng.IHttpResponse<any>) {
                        notify.response(response);
                    });
                    
                    cropDialog.hide();
                    
                    callback();
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
            }
        });
        
        cropDialog.show();
    }
};

angular.module(Module)
    .controller(CONTROLLER_NAME, UploadController)
    .config(['$stateProvider',
        function config($stateProvider: any) {
            $stateProvider.state( {
                name: STATE_NAME,
                url: '/upload?item_id&perspective_id&replace',
                controller: CONTROLLER_NAME,
                controllerAs: 'ctrl',
                template: require('./template.html')
            });
        }
    ]);


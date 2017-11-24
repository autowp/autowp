import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';

import './select';

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
                self.selectionName = item.name_html;
            }, function(response: ng.IHttpResponse<any>) {
                self.$state.go('error-404');
            });
        }
        
        /*
        'cropMsg'      => $this->translate('upload/picture/crop'),
        'croppedToMsg' => $this->translate('upload/picture/cropped-to'),
        'cropSaveUrl'  => $this->url(null, [
            'controller' => 'upload',
            'action'     => 'crop-save',
        ]),
        'perspectives' => $this->perspectives
        */
    }
    
    public submit()
    {
        console.log(this.file);
        
        this.progress = [];
        
        /*var $progress = $('.progress-area');
        this.$pictures = $('.pictures');
        
        $progress.empty();

        $form.hide();*/

        var xhrs: any[] = [];
        
        let self = this;
        
        for (let file of this.file) {
            
            let progress = {
                filename: file.fileName || file.name,
                percentage: 0,
                success: false,
                failed: false,
                invalidParams: {}
            };
            
            this.progress.push(progress);
            
            var promise = this.Upload.upload({
                method: 'POST',
                url: '/api/picture',
                data: {
                    file: file, 
                    note: this.note
                }
            }).then(function (response: ng.IHttpResponse<any>) {
                console.log('Success ' + response.config.data.file.name + 'uploaded. Response: ' + response.data);
                console.log(progress);
                progress.percentage = 100;
                progress.success = true;
                
                let location = response.headers('Location');

                self.$http({
                    method: 'GET',
                    url: location,
                    params: {
                        fields: 'thumbnail,votes,views,comments_count,perspective_item,name_html,name_text'
                    }
                }).then(function(response: ng.IHttpResponse<any>) {
                    self.insertPicture(response.data);
                }, function(response: ng.IHttpResponse<any>) {
                    notify.response(response);
                });
                /*if (data) {
                    $.map(data, function(picture) {
                        
                    });
                }*/
            }, function (response: ng.IHttpResponse<any>) {
                console.log('Error status: ' + response.status);
                
                progress.percentage = 100;
                progress.failed = true;
                
                progress.invalidParams = response.data.invalid_params;
            }, function (evt) {
                console.log('progress');
                progress.percentage = Math.round(100.0 * evt.loaded / evt.total);
            }); 
            
            xhrs.push(promise);
        }

        this.$q.all(xhrs).then(function() {
            self.file = undefined;
        }, function() {
            self.file = undefined;
        });
    }
    
    public insertPicture(picture: any) {
        this.pictures.push(picture);
        
        /*var cropDialog;
        
        var $cropBtn = $('<a href="#"></a>')
            .text(this.cropMsg)
            .prepend('<i class="fa fa-crop"></i> ')
            .on('click', function(e) {
                e.preventDefault();
                
                if (!cropDialog) {
                    cropDialog = new CropDialog.CropDialog({
                        sourceUrl: picture.src,
                        crop: {
                            x: 0, 
                            y: 0, 
                            w: picture.width, 
                            h: picture.height
                        },
                        width: picture.width,
                        height: picture.height,
                        onSave: function(crop, callback) {
                            var params = $.extend(crop, {
                                id: picture.id
                            });
                            $.post(self.cropSaveUrl, params, function(json) {
                                var cropStr = Math.round(crop.w) + '×' +
                                              Math.round(crop.h) + '+' +
                                              Math.round(crop.x) + '+' +
                                              Math.round(crop.y);
                                
                                $cropBtn
                                    .text(self.croppedToMsg.replace('%s', cropStr))
                                    .prepend('<i class="fa fa-crop"></i> ');
                                
                                $picture.find('img').attr('src', json.src + '?' + Math.random());
                                
                                cropDialog.hide();
                                
                                callback();
                            });
                        }
                    });
                }
                
                cropDialog.show();
            });
        
        $picture.append($cropBtn);
        
        if (picture.perspectiveUrl) {
            var $perspective = $('<select class="form-control input-sm"><option value="">--</option></select>');
            $.map(this.perspectives, function(perspective) {
                $perspective.append(
                    $("<option></option>")
                        .attr("value", perspective.id).text(perspective.name)
                );
            });
            
            $perspective.on('change', function(e) {
                $.post(picture.perspectiveUrl, {perspective_id: $(this).val()});
            });
            
            $picture.append($perspective);
            
            $perspective.val(picture.perspectiveId ? picture.perspectiveId : '');
        }*/
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


import * as angular from 'angular';
import Module from 'app.module';
import notify from 'notify';
import { ItemService } from 'services/item';

import './select';

const CONTROLLER_NAME = 'UploadController';
const STATE_NAME = 'upload';

export class UploadController {
    static $inject = ['$scope', '$http', '$state', 'ItemService', 'Upload'];
    
    public selected: boolean;
    public selectionName: string;
    private replace: any;
    public file: any;
    public note: string;
    public progress: any[];
  
    constructor(
        private $scope: autowp.IControllerScope,
        private $http: ng.IHttpService, 
        private $state: any,
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
        
        /*var $progress = $('.progress-area');
        this.$pictures = $('.pictures');
        
        $progress.empty();

        $form.hide();

        var xhrs = [];*/

        for (let file of this.file) {
            
            var progress = {
                filename: file.fileName || file.name,
                percentage: 0,
                success: false,
                failed: false
            };
            
            this.Upload.upload({
                method: 'POST',
                url: '/api/picture',
                data: {
                    file: file, 
                    note: this.note
                }
            }).then(function (response) {
                console.log('Success ' + response.config.data.file.name + 'uploaded. Response: ' + response.data);
                
                progress.percentage = 100;
                progress.success = true;

                /*if (data) {
                    $.map(data, function(picture) {
                        self.insertPicture(picture);
                    });
                }*/
            }, function (response) {
                console.log('Error status: ' + response.status);
                
                progress.percentage = 100;
                progress.failed = true;
                
                /*var errorMessage;
                if (data.responseJSON) {
                    var errors = [];
                    $.map(data.responseJSON, function(field) {
                        $.map(field, function(error) {
                            errors.push(error);
                        });
                    });
                    errorMessage = errors.join("\n");
                } else {
                    errorMessage = data.statusText;
                }
                $bar.find('.percentage').text("Error: " + errorMessage);*/
            }, function (evt) {
                progress.percentage = Math.round(100.0 * evt.loaded / evt.total);
            });
        }

        /*$.when.apply($, xhrs).then(function() {
            $form.show();
            $form[0].reset();
        }, function() {
            $form.show();
            $form[0].reset();
        });*/
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


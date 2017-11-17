import * as angular from "angular";
import Module from 'app.module';
import * as $ from 'jquery';
import { MessageService } from 'services/message';

const SERVICE_NAME = 'MessageDialogService';

export class MessageDialogService {
    static $inject = ['MessageService', '$translate'];
  
    constructor(
        private MessageService: MessageService,
        private $translate: ng.translate.ITranslateService
    ) {
      
    }
  
    public showDialog(userId: number, sentCallback?: Function, cancelCallback?: Function) {
        
        var self = this;
        
        var $modal = $(require('message/modal.html'));
        
        var $form = $modal.find('form');
        
        var $btnSend = $form.find('.btn-primary').button();
        var $btnCancel = $form.find('.cancel').button();
        var $textarea = $form.find('textarea');
      
        var names = [
            "personal-message-dialog/title",
            "personal-message-dialog/sending",
            "personal-message-dialog/sent",
            "personal-message-dialog/send",
            "personal-message-dialog/cancel",
            "personal-message-dialog/placeholder"
        ];
        
        self.$translate(names).then(function (translations: any) {
            $modal.find('.modal-title').text(translations["personal-message-dialog/title"]);
            $btnSend.attr('data-loading-text', translations["personal-message-dialog/sending"]);
            $btnSend.attr('data-complete-text', translations["personal-message-dialog/sent"]);
            $btnSend.attr('data-send-text', translations["personal-message-dialog/send"]);
            $btnSend.text(translations["personal-message-dialog/send"]);
            $btnCancel.text(translations["personal-message-dialog/cancel"]);
            $textarea.attr('placeholder', translations["personal-message-dialog/placeholder"]);
        
            $modal.modal({
                show: true
            });
    
            $modal.on('hidden.bs.modal', function () {
                $modal.remove();
                if (cancelCallback) {
                    cancelCallback();
                }
            });
            $modal.on('shown.bs.modal', function () {
                $textarea.focus();
            });
            
            
            $textarea.bind('change keyup click', function() {
                var val: any = $(this).val();
                $textarea.parent().removeClass('error');
                $btnSend.text(translations["personal-message-dialog/send"])
                    .removeClass('btn-success')
                    .prop('disabled', val.length <= 0);
            }).triggerHandler('change');
            
            $form.find('button.cancel, a.close').on('click', function(e) {
                e.preventDefault();
                $modal.modal('hide');
            });
            
            $form.submit(function(e) {
                e.preventDefault();
                
                let text: any = $textarea.val();
              
                if (text.length <= 0) {
                    $textarea.parent().addClass('error');
                } else {
                    $btnSend.button('loading');
                    $btnCancel.prop("disabled", 1);
                    $textarea.prop("disabled", 1);
                    
                    self.MessageService.send(userId, text).then(function() {
                        $textarea.val('');
                        
                        $btnSend.button('reset').button('complete').addClass('btn-success disabled').prop("disabled", 1);
                        
                        $textarea.prop("disabled", 0);
                        $btnCancel.prop("disabled", 0);
                        
                        if (sentCallback) {
                            sentCallback();
                        }
                    });
                }
            });
          
        });
    }
}

angular.module(Module).service(SERVICE_NAME, MessageDialogService);

import * as $ from 'jquery';
import * as i18next from 'i18next';
import { sprintf } from "sprintf-js";
require('jcrop-0.9.12/css/jquery.Jcrop.css');
require('jcrop-0.9.12/js/jquery.Jcrop');

export interface Crop {
    x: number;
    y: number;
    w: number;
    h: number;
}

export class CropDialog {
    private onSave: (currentCrop: any, callback: () => void) => void;
    private width: number;
    private height: number;
    private sourceUrl: string;
    private minSize: number[];
    private $modal: JQuery;
    private $body: JQuery;
    private $selection: JQuery;
    private currentCrop: Crop;
    private jcrop: any;

    constructor(
        private options: any
    ) {
        var self = this;

        this.onSave = options.onSave;
        this.width = options.width;
        this.height = options.height;
        this.sourceUrl = options.sourceUrl;
        this.minSize = options.minSize ? options.minSize : [400, 300];

        this.$modal = $(require('./crop-dialog.html'));

        this.$modal.find('.modal-title').text(i18next.t("crop-dialog/title"));
        this.$modal.find('.btn-primary').text(i18next.t("crop-dialog/save"));
        this.$modal.find('.btn-close').text(i18next.t("crop-dialog/close"));
        this.$modal.find('.select-all span').text(i18next.t("crop-dialog/select-all"));

        this.$body = this.$modal.find('.modal-body');
        this.$selection = this.$modal.find('.selection');

        this.jcrop = null;
        this.currentCrop = options.crop;

        this.$modal.on('shown.bs.modal', function() {
            self.afterShown();
        });

        this.$modal.on('hidden.bs.modal', function() {
            self.afterHidden();
        });

        this.$modal.find('.btn-primary').click(function() {
            // var $btn = $(this).button('loading');
            self.onSave(self.currentCrop, function() {
                // $btn.button('reset');
            });
        });

        this.$modal.find('.select-all').on('click', function() {
            self.jcrop.setSelect([0, 0, self.width, self.height]);
        });

        this.$modal.modal({
            show: false
        });
    }

    public updateSelectionText() {
        var text = Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
        var pw = 4;
        var ph = pw * this.currentCrop.h / this.currentCrop.w;
        var phRound = Math.round(ph * 10) / 10;
        this.$selection.text(
            sprintf(
                i18next.t("crop-dialog/resolution-%s-aspect-%s"),
                text, pw+':'+phRound
            )
        );
    }

    public afterShown() {
        let bodyWidth = this.$body.width();

        if (! bodyWidth) {
            bodyWidth = 1;
        }

        let scale = this.width / bodyWidth;
        let width = this.width / scale;
        let height = this.height / scale;

        var self = this;

        var $img = $('<img />', {
            src: this.sourceUrl,
            css: {
                width: width,
                height: height
            },
            appendTo: this.$body
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
                    trueSize: [self.width, self.height],
                    keySupport: false
                });

            }, 100);
        });

    }

    public afterHidden() {
        if (this.jcrop) {
            this.jcrop.destroy();
            this.jcrop = null;
        }
        this.$body.empty();
    }

    public show() {
        this.$modal.modal('show');
    }

    public hide() {
        this.$modal.modal('hide');
    }
}

import * as $ from 'jquery';
import * as i18next from 'i18next';
import { sprintf } from 'sprintf-js';
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

  constructor(private options: any) {
    this.onSave = options.onSave;
    this.width = options.width;
    this.height = options.height;
    this.sourceUrl = options.sourceUrl;
    this.minSize = options.minSize ? options.minSize : [400, 300];

    this.$modal = $(require('./crop-dialog.html'));

    this.$modal.find('.modal-title').text(i18next.t('crop-dialog/title'));
    this.$modal.find('.btn-primary').text(i18next.t('crop-dialog/save'));
    this.$modal.find('.btn-close').text(i18next.t('crop-dialog/close'));
    this.$modal
      .find('.select-all span')
      .text(i18next.t('crop-dialog/select-all'));

    this.$body = this.$modal.find('.modal-body');
    this.$selection = this.$modal.find('.selection');

    this.jcrop = null;
    this.currentCrop = options.crop;

    this.$modal.on('shown.bs.modal', () => {
      this.afterShown();
    });

    this.$modal.on('hidden.bs.modal', () => {
      this.afterHidden();
    });

    this.$modal.find('.btn-primary').click(() => {
      // var $btn = $(this).button('loading');
      this.onSave(this.currentCrop, () => {
        // $btn.button('reset');
      });
    });

    this.$modal.find('.select-all').on('click', () => {
      this.jcrop.setSelect([0, 0, this.width, this.height]);
    });

    /*this.$modal.modal({
            show: false
        });*/
  }

  public updateSelectionText() {
    const text =
      Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = pw * this.currentCrop.h / this.currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;
    this.$selection.text(
      sprintf(
        i18next.t('crop-dialog/resolution-%s-aspect-%s'),
        text,
        pw + ':' + phRound
      )
    );
  }

  public afterShown() {
    let bodyWidth = this.$body.width();

    if (!bodyWidth) {
      bodyWidth = 1;
    }

    const scale = this.width / bodyWidth;
    const width = this.width / scale;
    const height = this.height / scale;

    const $img = $('<img />', {
      src: this.sourceUrl,
      css: {
        width: width,
        height: height
      },
      appendTo: this.$body
    }).on('load', function() {
      // sometimes Jcrop fails without delay
      setTimeout(function() {
        /*this.jcrop = $.Jcrop($img[0], {
          onSelect: (c: Crop) => {
            this.currentCrop = c;
            this.updateSelectionText();
          },
          setSelect: [
            this.currentCrop.x,
            this.currentCrop.y,
            this.currentCrop.x + this.currentCrop.w,
            this.currentCrop.y + this.currentCrop.h
          ],
          minSize: this.minSize,
          boxWidth: width,
          boxHeight: height,
          trueSize: [this.width, this.height],
          keySupport: false
        });*/
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
    // this.$modal.modal('show');
  }

  public hide() {
    // this.$modal.modal('hide');
  }
}

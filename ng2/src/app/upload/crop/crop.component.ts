import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges,
  OnInit
} from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';

@Component({
  selector: 'app-upload-crop',
  templateUrl: './crop.component.html'
})
@Injectable()
export class UploadCropComponent implements OnChanges, OnInit {
  @Input() width: number;
  @Input() height: number;
  @Input() url: number;

  private minSize = [400, 300];

  private jcrop = null;
  @Input() currentCrop = {
    w: 0,
    h: 0
  };

  constructor(
    public activeModal: NgbActiveModal
  ) {}

  ngOnInit(): void {

  }

  public selectAll() {
    this.jcrop.setSelect([0, 0, this.width, this.height]);
  }

  ngOnChanges(changes: SimpleChanges): void {

  }

  public updateSelectionText() {
    const text =
      Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = (pw * this.currentCrop.h) / this.currentCrop.w;
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
      src: this.url,
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
}

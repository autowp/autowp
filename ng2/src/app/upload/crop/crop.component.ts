import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges,
  OnInit,
  EventEmitter,
  Output,
  OnDestroy
} from '@angular/core';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { BehaviorSubject, combineLatest, Subscription } from 'rxjs';
import { APIPicture } from '../../services/picture';
import * as $ from 'jquery';
import Jcrop from '../../jcrop/jquery.Jcrop';

interface JcropCrop {
  x: number;
  y: number;
  w: number;
  h: number;
}

@Component({
  selector: 'app-upload-crop',
  templateUrl: './crop.component.html'
})
@Injectable()
export class UploadCropComponent implements OnChanges, OnInit, OnDestroy {

  @Input() picture: APIPicture;
  @Output() changed = new EventEmitter();

  private picture$ = new BehaviorSubject<APIPicture>(null);

  private minSize = [400, 300];

  private jcrop = null;
  public aspect: string;
  public resolution: string;
  public img$ = new BehaviorSubject<HTMLElement>(null);
  private currentCrop: JcropCrop = {
    w: 0,
    h: 0,
    x: 0,
    y: 0
  };
  private sub: Subscription;

  constructor(public activeModal: NgbActiveModal) {}

  ngOnInit(): void {
    this.picture$.next(this.picture);
    this.sub = combineLatest(this.img$, this.picture$, (img, picture) => ({
      img,
      picture
    })).subscribe(data => {
      if (data.img && data.picture) {

        const $img = $(data.img);
        const $body = $img.parent();

        this.jcrop = null;
        if (data.picture.crop) {
          this.currentCrop = {
            w: data.picture.crop.width,
            h: data.picture.crop.height,
            x: data.picture.crop.left,
            y: data.picture.crop.top
          };
        } else {
          this.currentCrop = {
            w: data.picture.width,
            h: data.picture.height,
            x: 0,
            y: 0
          };
        }

        const bWidth = $body.width() || 1;



        const scale = data.picture.width / bWidth,
          width = data.picture.width / scale,
          height = data.picture.height / scale;


        $img.css({
          width: width,
          height: height
        });

        this.jcrop = Jcrop($img[0], {
          onSelect: (c: JcropCrop) => {
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
          trueSize: [data.picture.width, data.picture.height],
          keySupport: false
        });
      }
    });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public selectAll() {
    this.jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.picture) {
      this.picture$.next(changes.picture.currentValue);
    }
  }

  private updateSelectionText() {
    const text =
      Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = (pw * this.currentCrop.h) / this.currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;

    this.aspect = pw + ':' + phRound;
    this.resolution = text;
  }

  public onLoad(e) {
    this.img$.next(e.target);
  }

  public onSave() {
    if (!this.picture.crop) {
      this.picture.crop = {
        left: 0,
        top: 0,
        width: 0,
        height: 0
      };
    }
    this.picture.crop.left = this.currentCrop.x;
    this.picture.crop.top = this.currentCrop.y;
    this.picture.crop.width = this.currentCrop.w;
    this.picture.crop.height = this.currentCrop.h;

    this.changed.emit();
    this.activeModal.close();
  }
}

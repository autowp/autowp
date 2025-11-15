import {AsyncPipe, DOCUMENT} from '@angular/common';
import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  inject,
  input,
  OnDestroy,
  OnInit,
  output,
} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {APIImage, Picture} from '@grpc/spec.pb';
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {BehaviorSubject, combineLatest, Subscription} from 'rxjs';

// @ts-expect-error Legacy
import Jcrop from '../../jcrop/jquery.Jcrop';

interface JcropCrop {
  h: number;
  w: number;
  x: number;
  y: number;
}

@Component({
  selector: 'app-upload-crop',
  imports: [AsyncPipe],
  templateUrl: './crop.component.html',
  // eslint-disable-next-line @angular-eslint/prefer-on-push-component-change-detection
  changeDetection: ChangeDetectionStrategy.Default,
})
export class UploadCropComponent implements OnDestroy, OnInit {
  protected readonly activeModal = inject(NgbActiveModal);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #document = inject(DOCUMENT);

  readonly changed = output<void>();

  readonly picture = input.required<Picture>();
  readonly picture$ = toObservable(this.picture);

  readonly #minSize = [400, 300];

  #jcrop: Jcrop = null;
  protected aspect = '';
  protected resolution = '';
  protected readonly img$ = new BehaviorSubject<HTMLImageElement | null>(null);
  private currentCrop: JcropCrop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  #sub?: Subscription;

  ngOnInit(): void {
    this.#sub = combineLatest([this.img$, this.picture$]).subscribe(([img, picture]) => {
      if (img && picture) {
        const body = img.parentElement;

        if (!body) {
          return;
        }

        this.#jcrop = null;
        if (picture.image && picture.image.cropWidth && picture.image.cropHeight) {
          this.currentCrop = {
            h: picture.image.cropHeight ?? 0,
            w: picture.image.cropWidth ?? 0,
            x: picture.image.cropLeft ?? 0,
            y: picture.image.cropTop ?? 0,
          };
        } else {
          this.currentCrop = {
            h: picture.height,
            w: picture.width,
            x: 0,
            y: 0,
          };
        }

        const styles = this.#document.defaultView?.getComputedStyle(body, null);
        const bWidth =
          body.clientWidth - parseFloat(styles?.paddingLeft ?? '0') - parseFloat(styles?.paddingRight ?? '0') || 1;

        const scale = picture.width / bWidth;
        const width = picture.width / scale;
        const height = picture.height / scale;

        img.style.width = width + 'px';
        img.style.height = height + 'px';

        this.#jcrop = Jcrop(img, {
          boxHeight: height,
          boxWidth: width,
          keySupport: false,
          minSize: this.#minSize,
          onSelect: (c: JcropCrop) => {
            this.currentCrop = c;
            if (this.currentCrop.y < 0) {
              this.currentCrop.y = 0;
            }
            if (this.currentCrop.x < 0) {
              this.currentCrop.x = 0;
            }
            this.updateSelectionText();
          },
          setSelect: [
            this.currentCrop.x,
            this.currentCrop.y,
            this.currentCrop.x + this.currentCrop.w,
            this.currentCrop.y + this.currentCrop.h,
          ],
          trueSize: [picture.width, picture.height],
        });

        this.#cdr.markForCheck();
      }
    });
  }

  ngOnDestroy(): void {
    if (this.#sub) {
      this.#sub.unsubscribe();
    }
  }

  protected selectAll(picture: Picture) {
    if (this.#jcrop) {
      this.#jcrop.setSelect([0, 0, picture.width, picture.height]);
    }
  }

  private updateSelectionText() {
    const text = Math.round(this.currentCrop.w) + '×' + Math.round(this.currentCrop.h);
    const pw = 4;
    const ph = (pw * this.currentCrop.h) / this.currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;

    this.aspect = pw + ':' + phRound;
    this.resolution = text;

    this.#cdr.markForCheck();
  }

  protected onLoad(e: Event) {
    if (e.target && e.target instanceof HTMLImageElement) {
      this.img$.next(e.target);
    }
  }

  protected onSave(picture: Picture) {
    if (!picture.image) {
      picture.image = new APIImage();
    }
    picture.image.cropLeft = this.currentCrop.x;
    picture.image.cropTop = this.currentCrop.y;
    picture.image.cropWidth = this.currentCrop.w;
    picture.image.cropHeight = this.currentCrop.h;

    this.changed.emit(void 0);
    this.activeModal.close();
  }
}

import type {OnDestroy, OnInit} from '@angular/core';
import type {Subscription} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Picture,
  PictureCrop,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
  UpdatePictureRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {browserWindow} from '@utils/browser-window';
import {BehaviorSubject, catchError, debounceTime, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import type {JcropCrop as Crop, JcropInstance} from '../../../../jcrop/jquery.Jcrop.js';

import Jcrop from '../../../../jcrop/jquery.Jcrop.js';
import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-pictures-item-crop',
  imports: [RouterLink],
  templateUrl: './crop.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemCropComponent implements OnDestroy, OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #window = browserWindow();

  #routeSub?: Subscription;
  protected aspect = '';
  protected resolution = '';
  #jcrop: JcropInstance | null = null;
  #currentCrop: Crop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  readonly #minSize = [400, 300];
  protected picture?: Picture;
  protected readonly img$ = new BehaviorSubject<HTMLImageElement | null>(null);

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 148,
    });
    this.#routeSub = this.#route.paramMap
      .pipe(
        map((params) => params.get('id') ?? ''),
        distinctUntilChanged(),
        debounceTime(10),
        switchMap((id) =>
          this.#picturesClient.getPicture(
            new PicturesRequest({
              fields: new PictureFields({image: true}),
              options: new PictureListOptions({id}),
            }),
          ),
        ),
        switchMap((picture) => this.img$.pipe(map((img) => ({img, picture})))),
      )
      .subscribe((data) => {
        this.picture = data.picture;

        if (data.img) {
          const body = data.img.parentElement;
          if (!body) {
            return;
          }

          this.#jcrop = null;
          if (this.picture.image && this.picture.image.cropHeight > 0 && this.picture.image.cropWidth > 0) {
            this.#currentCrop = {
              h: this.picture.image.cropHeight,
              w: this.picture.image.cropWidth,
              x: this.picture.image.cropLeft,
              y: this.picture.image.cropTop,
            };
          } else {
            this.#currentCrop = {
              h: this.picture.height,
              w: this.picture.width,
              x: 0,
              y: 0,
            };
          }

          const styles = this.#window?.getComputedStyle(body, null);
          const bWidth =
            body.clientWidth - parseFloat(styles?.paddingLeft ?? '0') - parseFloat(styles?.paddingRight ?? '0') || 1;

          const scale = this.picture.width / bWidth;
          const width = this.picture.width / scale;
          const height = this.picture.height / scale;

          data.img.style.width = width + 'px';
          data.img.style.height = height + 'px';

          this.#jcrop = Jcrop(data.img, {
            boxHeight: height,
            boxWidth: width,
            keySupport: false,
            minSize: this.#minSize,
            onSelect: (c: Crop) => {
              this.#currentCrop = c;
              this.updateSelectionText();
            },
            setSelect: [
              this.#currentCrop.x,
              this.#currentCrop.y,
              this.#currentCrop.x + this.#currentCrop.w,
              this.#currentCrop.y + this.#currentCrop.h,
            ],
            trueSize: [this.picture.width, this.picture.height],
          });
        }

        this.#cdr.markForCheck();
      });
  }

  ngOnDestroy(): void {
    if (this.#routeSub) {
      this.#routeSub.unsubscribe();
    }
  }

  protected selectAll() {
    if (this.picture && this.#jcrop) {
      this.#jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
    }
  }

  protected saveCrop() {
    if (this.picture) {
      this.#picturesClient
        .updatePicture(
          new UpdatePictureRequest({
            picture: new Picture({
              crop: new PictureCrop({
                height: Math.round(this.#currentCrop.h),
                left: this.#currentCrop.x > 0 ? Math.round(this.#currentCrop.x) : 0,
                top: this.#currentCrop.y > 0 ? Math.round(this.#currentCrop.y) : 0,
                width: Math.round(this.#currentCrop.w),
              }),
              id: this.picture.id,
            }),
            updateMask: new FieldMask({paths: ['crop']}),
          }),
        )
        .pipe(
          catchError((error: unknown) => {
            this.#toastService.handleError(error);
            return EMPTY;
          }),
        )
        .subscribe(() => {
          if (this.picture) {
            void this.#router.navigate(['/moder/pictures', this.picture.id]);
          }
        });
    }
  }

  private updateSelectionText() {
    const text = Math.round(this.#currentCrop.w) + '×' + Math.round(this.#currentCrop.h);
    const pw = 4;
    const ph = (pw * this.#currentCrop.h) / this.#currentCrop.w;
    const phRound = Math.round(ph * 10) / 10;

    this.aspect = pw + ':' + phRound;
    this.resolution = text;
    this.#cdr.markForCheck();
  }

  protected onLoad(e: Event) {
    if (e.target instanceof HTMLImageElement) {
      this.img$.next(e.target);
    }
  }
}

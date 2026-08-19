import type {OnDestroy, OnInit} from '@angular/core';
import type {Picture} from '@grpc/spec.pb';
import type {Subscription} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  PictureFields,
  PictureItem,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureListOptions,
  PicturesRequest,
  UpdatePictureItemRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {browserWindow} from '@utils/browser-window';
import {BehaviorSubject, catchError, distinctUntilChanged, EMPTY, map, switchMap, tap} from 'rxjs';

import type {JcropCrop as Crop, JcropInstance} from '../../../../jcrop/jquery.Jcrop.js';

import Jcrop from '../../../../jcrop/jquery.Jcrop.js';
import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-pictures-item-area',
  imports: [RouterLink],
  templateUrl: './area.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemAreaComponent implements OnDestroy, OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #cdr = inject(ChangeDetectorRef);
  readonly #window = browserWindow();

  #id = '';
  #itemID = '';
  #type = 0;
  #sub?: Subscription;
  protected aspect = '';
  protected resolution = '';
  #jcrop: JcropInstance | null = null;
  #currentCrop: Crop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  readonly #minSize = [50, 50];
  protected picture: null | Picture = null;
  protected readonly img$ = new BehaviorSubject<HTMLImageElement | null>(null);

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 148,
    });

    this.#sub = this.#route.paramMap
      .pipe(
        map((params) => params.get('id') ?? ''),
        distinctUntilChanged(),
        switchMap((id) =>
          this.#picturesClient.getPicture(
            new PicturesRequest({
              fields: new PictureFields({image: true}),
              options: new PictureListOptions({id}),
            }),
          ),
        ),
        tap((picture) => {
          this.#id = picture.id;
          this.picture = picture;
          this.#cdr.markForCheck();
        }),
        switchMap((picture) =>
          this.#route.queryParamMap.pipe(
            map((params) => ({
              item_id: params.get('item_id') ?? '',
              type: parseInt(params.get('type') ?? '', 10),
            })),
            distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
            map((params) => ({params, picture})),
          ),
        ),
        tap((data) => {
          this.#itemID = data.params.item_id;
          this.#type = data.params.type;
          this.#cdr.markForCheck();
        }),
        switchMap(({params, picture}) =>
          this.#picturesClient.getPictureItem(
            new PictureItemsRequest({
              options: new PictureItemListOptions({
                itemId: params.item_id,
                pictureId: picture.id,
                typeId: params.type,
              }),
            }),
          ),
        ),
        switchMap((data) => this.img$.pipe(map((img) => ({img, pictureItem: data})))),
      )
      .subscribe({
        error: () => {
          void this.#router.navigate(['/error-404'], {
            skipLocationChange: true,
          });
        },
        next: (data) => {
          if (data.img && this.picture) {
            const body = data.img.parentElement;
            if (!body) {
              this.#cdr.markForCheck();
              return;
            }

            this.#jcrop = null;
            if (data.pictureItem.cropHeight > 0 && data.pictureItem.cropWidth > 0) {
              this.#currentCrop = {
                h: data.pictureItem.cropHeight,
                w: data.pictureItem.cropWidth,
                x: data.pictureItem.cropLeft,
                y: data.pictureItem.cropTop,
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

            this.#cdr.markForCheck();
          }
        },
      });
  }

  ngOnDestroy(): void {
    if (this.#sub) {
      this.#sub.unsubscribe();
    }
  }

  protected selectAll() {
    if (this.picture && this.#jcrop) {
      this.#jcrop.setSelect([0, 0, this.picture.width, this.picture.height]);
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

  protected saveCrop() {
    if (this.picture) {
      this.#picturesClient
        .updatePictureItem(
          new UpdatePictureItemRequest({
            pictureItem: new PictureItem({
              cropHeight: Math.round(this.#currentCrop.h),
              cropLeft: this.#currentCrop.x > 0 ? Math.round(this.#currentCrop.x) : 0,
              cropTop: this.#currentCrop.y > 0 ? Math.round(this.#currentCrop.y) : 0,
              cropWidth: Math.round(this.#currentCrop.w),
              itemId: this.#itemID,
              pictureId: this.#id,
              type: this.#type,
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

  protected onLoad(e: Event) {
    if (e.target && e.target instanceof HTMLImageElement) {
      this.img$.next(e.target);
    }
  }
}

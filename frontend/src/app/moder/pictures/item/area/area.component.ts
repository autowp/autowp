import type {OnDestroy, OnInit} from '@angular/core';
import type {Picture} from '@grpc/spec.pb';
import type {Subscription} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, viewChild} from '@angular/core';
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
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {catchError, distinctUntilChanged, EMPTY, map, switchMap, tap} from 'rxjs';

import type {JcropCrop} from '../../../../jcrop/jcrop.component.js';

import {cropSummary, JcropComponent} from '../../../../jcrop/jcrop.component.js';
import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-pictures-item-area',
  imports: [RouterLink, JcropComponent],
  templateUrl: './area.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemAreaComponent implements OnDestroy, OnInit {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);
  readonly #cdr = inject(ChangeDetectorRef);

  #id = '';
  #itemID = '';
  #type = 0;
  #sub?: Subscription;
  protected aspect = '';
  protected resolution = '';
  #currentCrop: JcropCrop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  protected readonly minSize: [number, number] = [50, 50];
  protected picture: null | Picture = null;
  protected readonly jcrop = viewChild(JcropComponent);

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_PICTURE_AREA,
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
      )
      .subscribe({
        error: () => {
          this.#notFound.report();
        },
        next: (pictureItem) => {
          if (!this.picture) {
            return;
          }

          if (pictureItem.cropHeight > 0 && pictureItem.cropWidth > 0) {
            this.#currentCrop = {
              h: pictureItem.cropHeight,
              w: pictureItem.cropWidth,
              x: pictureItem.cropLeft,
              y: pictureItem.cropTop,
            };
          } else {
            this.#currentCrop = {
              h: this.picture.height,
              w: this.picture.width,
              x: 0,
              y: 0,
            };
          }

          this.#cdr.markForCheck();
        },
      });
  }

  ngOnDestroy(): void {
    if (this.#sub) {
      this.#sub.unsubscribe();
    }
  }

  protected selectAll() {
    this.jcrop()?.selectAll();
  }

  protected onCropChange(crop: JcropCrop): void {
    this.#currentCrop = crop;
    const summary = cropSummary(crop);
    this.aspect = summary.aspect;
    this.resolution = summary.resolution;
    this.#cdr.markForCheck();
  }

  protected get initialCrop(): JcropCrop {
    return this.#currentCrop;
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
}

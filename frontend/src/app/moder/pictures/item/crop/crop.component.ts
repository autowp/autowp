import type {OnDestroy, OnInit} from '@angular/core';
import type {Subscription} from 'rxjs';

import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, viewChild} from '@angular/core';
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
import {catchError, distinctUntilChanged, EMPTY, map, switchMap} from 'rxjs';

import type {JcropCrop} from '../../../../jcrop/Jcrop.js';

import {cropSummary} from '../../../../jcrop/crop-summary.js';
import {JcropComponent} from '../../../../jcrop/jcrop.component.js';
import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-moder-pictures-item-crop',
  imports: [RouterLink, JcropComponent],
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

  #routeSub?: Subscription;
  protected aspect = '';
  protected resolution = '';
  #currentCrop: JcropCrop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  protected readonly minSize: [number, number] = [400, 300];
  protected picture?: Picture;
  protected readonly jcrop = viewChild(JcropComponent);

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 148,
    });
    this.#routeSub = this.#route.paramMap
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
      )
      .subscribe((picture) => {
        this.picture = picture;
        this.#currentCrop = this.initialCropFor(picture);
        this.#cdr.markForCheck();
      });
  }

  ngOnDestroy(): void {
    if (this.#routeSub) {
      this.#routeSub.unsubscribe();
    }
  }

  protected initialCropFor(picture: Picture): JcropCrop {
    if (picture.image && picture.image.cropHeight > 0 && picture.image.cropWidth > 0) {
      return {
        h: picture.image.cropHeight,
        w: picture.image.cropWidth,
        x: picture.image.cropLeft,
        y: picture.image.cropTop,
      };
    }

    return {h: picture.height, w: picture.width, x: 0, y: 0};
  }

  protected onCropChange(crop: JcropCrop): void {
    this.#currentCrop = crop;
    const summary = cropSummary(crop);
    this.aspect = summary.aspect;
    this.resolution = summary.resolution;
    this.#cdr.markForCheck();
  }

  protected selectAll() {
    this.jcrop()?.selectAll();
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
}

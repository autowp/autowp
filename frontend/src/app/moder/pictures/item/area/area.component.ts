import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, OnDestroy, OnInit} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemsRequest,
  PictureListOptions,
  PicturesRequest,
  SetPictureItemAreaRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, EMPTY, Subscription} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap, tap} from 'rxjs/operators';

// @ts-expect-error Legacy
import Jcrop from '../../../../jcrop/jquery.Jcrop.js';
import {ToastsService} from '../../../../toasts/toasts.service';

interface Crop {
  h: number;
  w: number;
  x: number;
  y: number;
}

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
  readonly #document = inject(DOCUMENT);

  #id = '';
  #itemID = '';
  #type = 0;
  #sub?: Subscription;
  protected aspect = '';
  protected resolution = '';
  #jcrop: Jcrop;
  #currentCrop: Crop = {
    h: 0,
    w: 0,
    x: 0,
    y: 0,
  };
  #minSize = [50, 50];
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
        debounceTime(30),
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
              item_id: params.get('item_id') || '',
              type: parseInt(params.get('type') ?? '', 10),
            })),
            distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
            debounceTime(30),
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
          this.#router.navigate(['/error-404'], {
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

            const styles = this.#document.defaultView?.getComputedStyle(body, null);
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
    if (this.picture) {
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
        .setPictureItemArea(
          new SetPictureItemAreaRequest({
            cropHeight: Math.round(this.#currentCrop.h),
            cropLeft: Math.round(this.#currentCrop.x),
            cropTop: Math.round(this.#currentCrop.y),
            cropWidth: Math.round(this.#currentCrop.w),
            itemId: this.#itemID,
            pictureId: this.#id,
            type: this.#type,
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
            this.#router.navigate(['/moder/pictures', this.picture.id]);
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

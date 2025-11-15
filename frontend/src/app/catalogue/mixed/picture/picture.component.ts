import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {Meta} from '@angular/platform-browser';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  CommentsType,
  ItemFields,
  ItemListOptions,
  ItemsRequest,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {GrpcStatusEvent} from '@ngx-grpc/common';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, combineLatest, EMPTY, Observable, of, throwError} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {StatusCode} from '../../../../grpc-web-client/statuscode';
import {CommentsComponent} from '../../../comments/comments/comments.component';
import {PictureComponent} from '../../../picture/picture.component';
import {BrandPerspectivePageData} from '../../catalogue.module';

@Component({
  selector: 'app-catalogue-mixed-picture',
  imports: [RouterLink, CommentsComponent, AsyncPipe, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CatalogueMixedPictureComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #router = inject(Router);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly changed$ = new BehaviorSubject<void>(void 0);

  protected readonly CommentsType = CommentsType;

  protected readonly brand$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('brand')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) => {
      if (!catname) {
        return EMPTY;
      }

      return this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            nameHtml: true,
            nameText: true,
          }),
          language: this.#languageService.language,
          limit: 1,
          options: new ItemListOptions({
            catname,
          }),
        }),
      );
    }),
    map((response) => (response.items?.length ? response.items[0] : null)),
    switchMap((brand) => {
      if (!brand) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(brand);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly data$ = (this.#route.data as Observable<BrandPerspectivePageData>).pipe(
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((identity) => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(identity);
    }),
  );

  protected readonly picture$: Observable<Picture> = combineLatest([
    this.brand$,
    this.data$,
    this.identity$,
    this.changed$,
  ]).pipe(
    switchMap(([brand, data, identity]) =>
      this.#picturesClient
        .getPicture(
          new PicturesRequest({
            fields: new PictureFields({
              copyrights: true,
              image: true,
              moderVoted: true,
              nameHtml: true,
              nameText: true,
              paginator: new PicturesRequest({
                options: new PictureListOptions({
                  pictureItem: new PictureItemListOptions({
                    excludePerspectiveId: data.perspective_exclude_id || [],
                    itemId: brand.id,
                    perspectiveId: data.perspective_id,
                    typeId: PictureItemType.PICTURE_ITEM_CONTENT,
                  }),
                }),
                order: PicturesRequest.Order.ORDER_RESOLUTION_DESC,
              }),
              pictureModerVotes: new PictureModerVoteRequest(),
              previewLarge: true,
              replaceable: new PicturesRequest({
                fields: new PictureFields({nameHtml: true}),
              }),
              rights: true,
              subscribed: true,
              votes: true,
            }),
            language: this.#languageService.language,
            options: new PictureListOptions({
              identity,
              pictureItem: new PictureItemListOptions({
                excludePerspectiveId: data.perspective_exclude_id || [],
                itemId: brand.id,
                perspectiveId: data.perspective_id,
                typeId: PictureItemType.PICTURE_ITEM_CONTENT,
              }),
            }),
          }),
        )
        .pipe(
          catchError((error: unknown) => {
            if (error instanceof GrpcStatusEvent && error.statusCode == StatusCode.NOT_FOUND) {
              // NOT_FOUND
              return of(null);
            }
            console.error(error);
            return throwError(() => error);
          }),
          switchMap((picture) => {
            if (!picture) {
              this.#router.navigate(['/error-404'], {
                skipLocationChange: true,
              });
              return EMPTY;
            }
            return of(picture);
          }),
          tap((picture) => {
            this.#meta.addTag({property: 'og:title', content: picture.nameText});
            if (picture.previewLarge) {
              this.#meta.addTag({property: 'og:image', content: picture.previewLarge.src});
            }
            this.#pageEnv.set({
              pageId: data.picture_page.id,
              title: picture.nameText,
            });
          }),
        ),
    ),
  );

  protected reloadPicture() {
    this.changed$.next();
  }
}

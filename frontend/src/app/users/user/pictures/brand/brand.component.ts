import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  APIItem,
  APIUser,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../../../toasts/toasts.service';

@Component({
  selector: 'app-users-user-pictures-brand',
  imports: [RouterLink, PaginatorComponent, AsyncPipe, ThumbnailComponent],
  templateUrl: './brand.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserPicturesBrandComponent {
  readonly #userService = inject(UserService);
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly user$: Observable<APIUser> = this.#route.paramMap.pipe(
    map((params) => params.get('identity')),
    switchMap((identity) => (identity ? of(identity) : EMPTY)),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((identity) => this.#userService.getByIdentity$(identity, undefined)),
    switchMap((user) => {
      if (!user || user.deleted) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(user);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #brand$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('brand') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((catname) =>
      this.#itemsClient
        .list(
          new ItemsRequest({
            fields: new ItemFields({
              nameOnly: true,
            }),
            language: this.#languageService.language,
            limit: 1,
            options: new ItemListOptions({
              catname,
              typeId: ItemType.ITEM_TYPE_BRAND,
            }),
          }),
        )
        .pipe(map((response) => (response.items?.length ? response.items[0] : null))),
    ),
    switchMap((brand) => {
      if (!brand) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      this.#pageEnv.set({
        pageId: 141,
        title: $localize`${brand.nameOnly} pictures`,
      });

      return of(brand);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly title$ = this.#brand$.pipe(map((brand) => $localize`${brand.nameOnly} pictures`));

  protected readonly data$ = combineLatest([
    this.user$,
    this.#brand$,
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
      debounceTime(10),
    ),
  ]).pipe(
    switchMap(([user, brand, page]) =>
      this.#picturesClient.getPictures(
        new PicturesRequest({
          fields: new PictureFields({
            commentsCount: true,
            moderVote: true,
            nameHtml: true,
            nameText: true,
            thumbMedium: true,
            views: true,
            votes: true,
          }),
          language: this.#languageService.language,
          limit: 30,
          options: new PictureListOptions({
            ownerId: user.id,
            pictureItem: new PictureItemListOptions({
              itemParentCacheAncestor: new ItemParentCacheListOptions({parentId: brand.id}),
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          page,
          paginator: true,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
  );
}

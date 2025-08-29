import {AsyncPipe, NgClass, NgStyle} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  APIItem,
  ItemFields,
  ItemListOptions,
  ItemParentCacheListOptions,
  ItemsRequest,
  ItemType,
  PictureItemListOptions,
  PictureListOptions,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {AutowpService} from '@rest/api/autowp.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {UserService} from '@services/user';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {ToastsService} from '../../../toasts/toasts.service';

function addCSS(url: string) {
  const cssId = 'brands-css';
  if (!document.getElementById(cssId)) {
    const head = document.getElementsByTagName('head')[0];
    const link = document.createElement('link');
    link.id = cssId;
    link.rel = 'stylesheet';
    link.type = 'text/css';
    link.href = url;
    link.media = 'all';
    head.appendChild(link);
  }
}

@Component({
  selector: 'app-users-user-pictures',
  imports: [RouterLink, NgClass, NgStyle, AsyncPipe],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UsersUserPicturesComponent implements OnInit {
  readonly #userService = inject(UserService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #autowp = inject(AutowpService);
  readonly #toastService = inject(ToastsService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly icons$ = this.#autowp.autowpGetBrandIcons().pipe(
    tap((icons) => {
      addCSS(icons.css);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  readonly #userId$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('identity') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly user$ = this.#userId$.pipe(
    switchMap((identity) => this.#userService.getByIdentity$(identity, undefined)),
    switchMap((user) => {
      if (!user) {
        return EMPTY;
      }
      return of(user);
    }),
    map((user) => ({
      id: user.id,
      identity: user.identity ? user.identity : 'user' + user.id,
      name: user.name,
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly brands$: Observable<APIItem[]> = this.user$.pipe(
    switchMap((user) =>
      this.#itemsClient.list(
        new ItemsRequest({
          fields: new ItemFields({
            descendantPicturesCount: true,
            nameOnly: true,
          }),
          language: this.#languageService.language,
          limit: 3000,
          options: new ItemListOptions({
            descendant: new ItemParentCacheListOptions({
              pictureItemsByItemId: new PictureItemListOptions({
                pictures: new PictureListOptions({
                  ownerId: '' + user.id,
                  status: PictureStatus.PICTURE_STATUS_ACCEPTED,
                }),
              }),
            }),
            typeId: ItemType.ITEM_TYPE_BRAND,
          }),
          order: ItemsRequest.Order.NAME_NAT,
        }),
      ),
    ),
    catchError((response: unknown) => {
      this.#toastService.handleError(response);
      return EMPTY;
    }),
    map((brands) => (brands.items ? brands.items : [])),
  );

  ngOnInit(): void {
    setTimeout(() => {
      this.#pageEnv.set({pageId: 63});
    }, 0);
  }

  protected cssClass(item: APIItem) {
    return item.catname.replace(/\./g, '_');
  }
}

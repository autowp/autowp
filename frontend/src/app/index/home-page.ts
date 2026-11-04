import type {OnInit} from '@angular/core';
import type {ItemOfDay} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {GetTopPersonsListRequest, ItemOfDayRequest, PictureItemType} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {UserService} from '@services/user';
import {errorMessage} from 'app/grpc';
import {RemarkModule} from 'ngx-remark';
import {catchError, EMPTY, of, shareReplay, switchMap} from 'rxjs';

import {ItemOfDayCard} from '../item-of-day/item-of-day/item-of-day-card';
import {ToastsService} from '../toasts/toasts.service';
import {IndexBrands} from './brands/index-brands';
import {IndexCategories} from './categories/index-categories';
import {IndexDonate} from './donate/index-donate';
import {IndexFactories} from './factories/index-factories';
import {IndexPictures} from './pictures/index-pictures';
import {IndexSpecsCars} from './specs-cars/index-specs-cars';
import {IndexTwins} from './twins/index-twins';

@Component({
  selector: 'app-index',
  imports: [
    ItemOfDayCard,
    IndexDonate,
    IndexBrands,
    IndexPictures,
    IndexTwins,
    IndexCategories,
    RouterLink,
    IndexFactories,
    IndexSpecsCars,
    RemarkModule,
  ],
  templateUrl: './home-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HomePage implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #userService = inject(UserService);
  readonly #toastService = inject(ToastsService);

  protected readonly mosts = [
    {
      name: $localize`Most fastest roadsters`,
      route: '/mosts/fastest/roadster',
    },
    {
      name: $localize`Most mighty sedans today`,
      route: '/mosts/mighty/sedan/today',
    },
    {
      name: $localize`Most dynamic universals in 2000's`,
      route: '/mosts/dynamic/universal/2000-09',
    },
    {
      name: $localize`Most heavy trucks`,
      route: '/mosts/heavy/truck',
    },
  ];

  readonly #itemOfDay$: Observable<ItemOfDay> = this.#itemsClient
    .getItemOfDay(new ItemOfDayRequest({language: this.#languageService.language}))
    .pipe(
      catchError((error: unknown) => {
        this.#toastService.handleError(error);
        return EMPTY;
      }),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  protected readonly itemOfDayItem$ = this.#itemOfDay$.pipe(
    switchMap((itemOfDay) => (itemOfDay.item ? of(itemOfDay.item) : EMPTY)),
  );
  protected readonly itemOfDayUser$ = this.#itemOfDay$.pipe(
    switchMap((itemOfDay) => this.#userService.getUser$(itemOfDay.userId)),
  );

  protected readonly contentPersonsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'index-content-persons',
    stream: () =>
      this.#itemsClient.getTopPersonsList(
        new GetTopPersonsListRequest({
          language: this.#languageService.language,
          pictureItemType: PictureItemType.PICTURE_ITEM_CONTENT,
        }),
      ),
  });
  protected readonly authorPersonsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'index-author-persons',
    stream: () =>
      this.#itemsClient.getTopPersonsList(
        new GetTopPersonsListRequest({
          language: this.#languageService.language,
          pictureItemType: PictureItemType.PICTURE_ITEM_AUTHOR,
        }),
      ),
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}

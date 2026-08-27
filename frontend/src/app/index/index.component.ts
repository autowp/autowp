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

import {ItemOfDayComponent} from '../item-of-day/item-of-day/item-of-day.component';
import {ToastsService} from '../toasts/toasts.service';
import {IndexBrandsComponent} from './brands/brands.component';
import {IndexCategoriesComponent} from './categories/categories.component';
import {IndexDonateComponent} from './donate/donate.component';
import {IndexFactoriesComponent} from './factories/factories.component';
import {IndexPicturesComponent} from './pictures/pictures.component';
import {IndexSpecsCarsComponent} from './specs-cars/specs-cars.component';
import {IndexTwinsComponent} from './twins/twins.component';

@Component({
  selector: 'app-index',
  imports: [
    ItemOfDayComponent,
    IndexDonateComponent,
    IndexBrandsComponent,
    IndexPicturesComponent,
    IndexTwinsComponent,
    IndexCategoriesComponent,
    RouterLink,
    IndexFactoriesComponent,
    IndexSpecsCarsComponent,
    RemarkModule,
  ],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class IndexComponent implements OnInit {
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

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }

  protected readonly errorMessage = errorMessage;
}

import {AsyncPipe, DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {FormControl, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  Inbox,
  InboxRequest,
  ItemParentCacheListOptions,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesList,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {formatGrpcDate, parseGrpcDate, parseStringToGrpcDate} from '@services/utils';
import Keycloak from 'keycloak-js';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../toasts/toasts.service';

const ALL_BRANDS = 'all';

interface InboxData {
  brandCatname: string;
  inbox: Inbox;
  pictures$: Observable<PicturesList>;
}

@Component({
  selector: 'app-inbox',
  imports: [RouterLink, FormsModule, PaginatorComponent, AsyncPipe, DatePipe, ThumbnailComponent, ReactiveFormsModule],
  templateUrl: './inbox.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class InboxComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #languageService = inject(LanguageService);
  readonly #keycloak = inject(Keycloak);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);

  protected readonly inbox$: Observable<InboxData> = this.#auth.authenticated$.pipe(
    switchMap((authenticated) => {
      if (!authenticated) {
        this.#keycloak.login({
          locale: this.#languageService.language,
          redirectUri: window.location.href,
        });
        return EMPTY;
      }

      return this.#route.paramMap;
    }),
    map((params) => ({
      brand: params.get('brand'),
      date: params.get('date') ?? '',
    })),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    debounceTime(30),
    switchMap((params) => {
      if (!params.brand) {
        this.#router.navigate(['/inbox', ALL_BRANDS]);
        return EMPTY;
      }

      let brandID = '';
      if (params.brand !== ALL_BRANDS) {
        brandID = params.brand ? params.brand : '';
      }

      this.brandID.setValue(brandID);

      return combineLatest([
        of(params.date),
        this.#picturesClient
          .getInbox(
            new InboxRequest({
              brandId: brandID,
              date: parseStringToGrpcDate(params.date),
              language: this.#languageService.language,
            }),
          )
          .pipe(
            catchError((err: unknown) => {
              this.#toastService.handleError(err);
              return EMPTY;
            }),
          ),
        of(brandID),
      ]);
    }),
    switchMap(([date, inbox, brandID]) => {
      const currentDate = inbox.currentDate;
      if (!currentDate) {
        return EMPTY;
      }

      const currentDateStr = formatGrpcDate(currentDate);
      if (date !== currentDateStr) {
        this.#router.navigate(['/inbox', brandID ? brandID : 'all', currentDateStr]);
        return EMPTY;
      }

      return of({
        brandCatname: brandID ? brandID.toString() : 'all',
        inbox: inbox,
        pictures$: this.#route.queryParamMap.pipe(
          map((params) => parseInt(params.get('page') ?? '', 10)),
          distinctUntilChanged(),
          switchMap((page) =>
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
                limit: 24,
                options: new PictureListOptions({
                  addDate: currentDate,
                  pictureItem: new PictureItemListOptions({
                    itemParentCacheAncestor: brandID
                      ? new ItemParentCacheListOptions({parentId: '' + brandID})
                      : undefined,
                  }),
                  status: PictureStatus.PICTURE_STATUS_INBOX,
                }),
                order: PicturesRequest.Order.ORDER_ADD_DATE_DESC,
                page,
                paginator: true,
              }),
            ),
          ),
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
      });
    }),
  );

  protected readonly brandID = new FormControl<string>('', {nonNullable: true});

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 76}), 0);
  }

  protected changeBrand() {
    this.#router.navigate(['/inbox', this.brandID.value ? this.brandID.value : 'all']);
  }

  protected readonly formatGrpcDate = formatGrpcDate;
  protected readonly parseGrpcDate = parseGrpcDate;
}

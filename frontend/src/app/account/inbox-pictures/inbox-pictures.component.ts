import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute} from '@angular/router';
import {PictureFields, PictureListOptions, PicturesList, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {AuthService} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap} from 'rxjs/operators';

import {PaginatorComponent} from '../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';

@Component({
  selector: 'app-account-inbox-pictures',
  imports: [PaginatorComponent, AsyncPipe, ThumbnailComponent],
  templateUrl: './inbox-pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountInboxPicturesComponent implements OnInit {
  readonly #auth = inject(AuthService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  protected readonly data$: Observable<PicturesList> = combineLatest([
    this.#route.queryParamMap.pipe(
      map((params) => parseInt(params.get('page') ?? '', 10)),
      distinctUntilChanged(),
      debounceTime(10),
    ),
    this.#auth.user$,
  ]).pipe(
    switchMap(([page, user]) =>
      user
        ? this.#picturesClient.getPictures(
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
              limit: 15,
              options: new PictureListOptions({
                ownerId: user.id,
                status: PictureStatus.PICTURE_STATUS_INBOX,
              }),
              order: PicturesRequest.Order.ORDER_ADD_DATE_DESC,
              page: page,
              paginator: true,
            }),
          )
        : EMPTY,
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 94}), 0);
  }
}

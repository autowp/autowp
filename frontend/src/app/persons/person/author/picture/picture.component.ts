import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router} from '@angular/router';
import {
  CommentsType,
  Picture,
  PictureFields,
  PictureItemListOptions,
  PictureItemType,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, combineLatest, EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../../../../comments/comments/comments.component';
import {PictureComponent} from '../../../../picture/picture.component';

@Component({
  selector: 'app-persons-person-author-picture',
  imports: [CommentsComponent, AsyncPipe, PictureComponent],
  templateUrl: './picture.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PersonsPersonAuthorPictureComponent {
  readonly #pageEnv = inject(PageEnvService);
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #changed$ = new BehaviorSubject<void>(void 0);

  readonly #identity$ = this.#route.paramMap.pipe(
    map((route) => route.get('identity')),
    distinctUntilChanged(),
    switchMap((identity) => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(identity);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly personID$ = this.#route.parent!.parent!.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
  );

  protected readonly picturesRouterLink$ = this.personID$.pipe(map((personID) => ['/persons', personID, 'author']));

  protected readonly galleryRouterLink$: Observable<string[]> = combineLatest([this.personID$, this.#identity$]).pipe(
    map(([personID, identity]) => ['/persons', personID, 'author', 'gallery', identity]),
  );

  protected readonly picture$: Observable<Picture> = combineLatest([
    this.#identity$,
    this.personID$,
    this.#changed$,
  ]).pipe(
    switchMap(([identity, itemID]) =>
      this.#picturesClient.getPicture(
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
                  itemId: itemID,
                  typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
                }),
              }),
              order: PicturesRequest.Order.ORDER_LIKES,
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
              itemId: itemID,
              typeId: PictureItemType.PICTURE_ITEM_AUTHOR,
            }),
          }),
        }),
      ),
    ),
    tap((picture) => {
      this.#pageEnv.set({
        pageId: 34,
        title: picture ? picture.nameText : '',
      });
    }),
  );

  protected readonly CommentsType = CommentsType;

  protected reloadPicture() {
    this.#changed$.next();
  }
}

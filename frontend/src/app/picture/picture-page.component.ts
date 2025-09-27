import {AsyncPipe} from '@angular/common';
import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {
  CanonicalRouteRequest,
  CommentsType,
  Picture,
  PictureFields,
  PictureListOptions,
  PictureModerVoteRequest,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, EMPTY, Observable, of, throwError} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../comments/comments/comments.component';
import {PictureComponent} from './picture.component';

@Component({
  selector: 'app-picture-page',
  imports: [RouterLink, CommentsComponent, AsyncPipe, PictureComponent],
  templateUrl: './picture-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PicturePageComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #changed$ = new BehaviorSubject<void>(void 0);

  protected readonly picture$: Observable<null | Picture> = this.#route.paramMap.pipe(
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

      return this.#picturesClient.getCanonicalRoute(new CanonicalRouteRequest({identity})).pipe(
        catchError((response: unknown) => {
          if (response instanceof HttpErrorResponse && response.status === 404) {
            this.#router.navigate(['/error-404'], {
              skipLocationChange: true,
            });
            return EMPTY;
          }
          return throwError(() => response);
        }),
        switchMap(({route}) => {
          if (route && route.length > 0) {
            this.#router.navigate(route, {
              replaceUrl: true,
            });
            return EMPTY;
          }

          return this.#changed$;
        }),
        switchMap(() =>
          this.#picturesClient.getPicture(
            new PicturesRequest({
              fields: new PictureFields({
                copyrights: true,
                image: true,
                moderVoted: true,
                nameHtml: true,
                nameText: true,
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
              options: new PictureListOptions({identity}),
            }),
          ),
        ),
        switchMap((picture) => {
          if (!picture) {
            this.#router.navigate(['/error-404'], {
              skipLocationChange: true,
            });
            return EMPTY;
          }
          return of(picture);
        }),
      );
    }),
    tap((picture) => {
      this.#pageEnv.set({
        pageId: 187,
        title: picture ? picture.nameText : '',
      });
    }),
  );

  protected readonly CommentsType = CommentsType;

  protected reloadPicture() {
    this.#changed$.next();
  }
}

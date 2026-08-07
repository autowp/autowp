import {HttpErrorResponse} from '@angular/common/http';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {Meta} from '@angular/platform-browser';
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
import {Observable, of, throwError} from 'rxjs';
import {catchError, distinctUntilChanged, map, switchMap, tap} from 'rxjs/operators';

import {CommentsComponent} from '../comments/comments/comments.component';
import {PictureComponent} from './picture.component';

@Component({
  selector: 'app-picture-page',
  imports: [RouterLink, CommentsComponent, PictureComponent],
  templateUrl: './picture-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PicturePageComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #meta = inject(Meta);
  readonly #router = inject(Router);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);

  readonly #identity = toSignal(
    this.#route.paramMap.pipe(
      map((route) => route.get('identity')),
      distinctUntilChanged(),
    ),
    {requireSync: true},
  );

  // Chained off a signal rather than a raw Observable pipe with debounceTime(10): Angular's actual
  // SSR whenStable() (used by platform-server's renderApplication) tracks only
  // PendingTasksInternal, not zone macrotasks, so a setTimeout-based delay before this chain's
  // first HTTP call isn't tracked as pending by anything — if some other resource on the page
  // happened to resolve during that window, SSR could serialize before this chain, and everything
  // depending on it (the whole page), had even started. distinctUntilChanged already prevents
  // redundant refetches for the same identity.
  protected readonly pictureResource = rxResource({
    id: 'picture-page',
    params: () => this.#identity(),
    stream: ({params: identity}): Observable<Picture | undefined> => {
      if (!identity) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return of(undefined);
      }

      return this.#picturesClient.getCanonicalRoute(new CanonicalRouteRequest({identity})).pipe(
        catchError((response: unknown) => {
          if (response instanceof HttpErrorResponse && response.status === 404) {
            this.#router.navigate(['/error-404'], {
              skipLocationChange: true,
            });
            return of(undefined);
          }
          return throwError(() => response);
        }),
        switchMap((canonical) => {
          if (!canonical) {
            return of(undefined);
          }
          if (canonical.route && canonical.route.length > 0) {
            this.#router.navigate(canonical.route, {
              replaceUrl: true,
            });
            return of(undefined);
          }

          return this.#picturesClient.getPicture(
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
          );
        }),
        switchMap((picture) => {
          if (!picture) {
            this.#router.navigate(['/error-404'], {
              skipLocationChange: true,
            });
            return of(undefined);
          }
          return of(picture);
        }),
        tap((picture) => {
          if (!picture) {
            return;
          }
          this.#meta.updateTag({property: 'og:title', content: picture.nameText});
          if (picture.previewLarge) {
            this.#meta.updateTag({property: 'og:image', content: picture.previewLarge.src});
          }
          this.#pageEnv.set({
            pageId: 187,
            title: picture.nameText,
          });
        }),
      );
    },
  });

  protected readonly CommentsType = CommentsType;

  protected reloadPicture() {
    this.pictureResource.reload();
  }
}

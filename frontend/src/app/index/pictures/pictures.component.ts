import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Picture, PictureFields, PictureListOptions, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PicturesWebSocketService} from '@services/pictures-ws.service';
import {ThumbnailComponent} from 'app/thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from 'app/toasts/toasts.service';
import {catchError, EMPTY, map, Observable, startWith, switchMap, throttleTime} from 'rxjs';

// Reload cadence for live "new picture accepted" notifications: the first accept in a
// burst reloads right away (leading edge), and if more accepts arrive within the window
// a single trailing reload catches them up, capping reloads to once per 15s.
const RELOAD_THROTTLE_MS = 15000;

@Component({
  selector: 'app-index-pictures',
  imports: [RouterLink, AsyncPipe, ThumbnailComponent],
  templateUrl: './pictures.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class IndexPicturesComponent {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesWs = inject(PicturesWebSocketService);

  protected readonly items$: Observable<Picture[]> = this.#picturesWs.pictureAccepted$.pipe(
    // The initial load must render immediately too, so it shares the same leading edge.
    startWith(void 0),
    throttleTime(RELOAD_THROTTLE_MS, undefined, {leading: true, trailing: true}),
    switchMap(() =>
      this.#picturesClient
        .getPictures(
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
            limit: 4,
            options: new PictureListOptions({
              acceptedInDays: 3,
              status: PictureStatus.PICTURE_STATUS_ACCEPTED,
            }),
            order: PicturesRequest.Order.ORDER_ACCEPT_DATETIME_DESC,
            paginator: false,
          }),
        )
        // Scoped to this request: a transient error must not kill the whole live-reload
        // stream, since (unlike the old one-shot fetch) this now re-emits over time.
        .pipe(
          catchError((err: unknown) => {
            this.#toastService.handleError(err);
            return EMPTY;
          }),
        ),
    ),
    map((response) => response.items || []),
  );
}

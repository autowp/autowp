import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Picture, PictureFields, PictureListOptions, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {ThumbnailComponent} from '../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../toasts/toasts.service';

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

  protected readonly items$: Observable<Picture[]> = this.#picturesClient
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
    .pipe(
      catchError((err: unknown) => {
        this.#toastService.handleError(err);
        return EMPTY;
      }),
      map((response) => response.items || []),
    );
}

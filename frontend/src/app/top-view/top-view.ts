import type {OnInit} from '@angular/core';
import type {RouteParam} from '@utils/route-param';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PictureFields, PictureItemListOptions, PictureListOptions, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';

import {Paginator} from '../paginator/paginator/paginator';
import {Thumbnail} from '../thumbnail/thumbnail/thumbnail';

@Component({
  selector: 'app-top-view',
  imports: [RouterLink, Paginator, Thumbnail],
  templateUrl: './top-view.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TopView implements OnInit {
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #pageEnv = inject(PageEnvService);

  readonly page = input(NaN, {
    transform: (value: RouteParam) => parseInt((Array.isArray(value) ? value[0] : value) ?? '', 10),
  });

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'top-view-page',
    params: () => this.page(),
    stream: ({params: page}) =>
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
          limit: 18,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({
              perspectiveId: 18,
            }),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_ACCEPT_DATETIME_DESC,
          page,
          paginator: true,
        }),
      ),
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.GALLERIES});
  }
}

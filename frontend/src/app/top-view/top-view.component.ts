import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {PictureFields, PictureItemListOptions, PictureListOptions, PicturesRequest, PictureStatus} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {errorMessage} from 'app/grpc';
import {map} from 'rxjs';

import {PaginatorComponent} from '../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';

@Component({
  selector: 'app-top-view',
  imports: [RouterLink, PaginatorComponent, ThumbnailComponent],
  templateUrl: './top-view.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TopViewComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #pageEnv = inject(PageEnvService);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly dataResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'top-view-page',
    params: () => this.#page(),
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

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 201});
  }

  protected readonly errorMessage = errorMessage;
}

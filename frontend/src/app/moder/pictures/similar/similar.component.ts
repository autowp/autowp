import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {
  DeleteSimilarRequest,
  DfDistance,
  DfDistanceFields,
  DfDistanceListOptions,
  DfDistanceRequest,
  Picture,
  PictureFields,
  PictureListOptions,
  PicturesRequest,
} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

import {PaginatorComponent} from '../../../paginator/paginator/paginator.component';
import {ThumbnailComponent} from '../../../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../../../toasts/toasts.service';

const similarPictureFields = () =>
  new PictureFields({
    nameHtml: true,
    replaceable: new PicturesRequest({fields: new PictureFields({thumbMedium: true})}),
    thumbMedium: true,
  });

@Component({
  selector: 'app-moder-pictures-similar',
  imports: [PaginatorComponent, RouterLink, ThumbnailComponent],
  templateUrl: './similar.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesSimilarComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  readonly #page = toSignal(this.#route.queryParamMap.pipe(map((params) => parseInt(params.get('page') ?? '', 10))), {
    requireSync: true,
  });

  protected readonly hideLoadingKey = signal<null | string>(null);

  protected readonly picturesResource = rxResource({
    params: () => this.#page(),
    stream: ({params: page}) =>
      this.#picturesClient
        .getPictures(
          new PicturesRequest({
            fields: Object.assign(similarPictureFields(), {
              dfDistance: new DfDistanceRequest({
                fields: new DfDistanceFields({
                  dstPicture: new PicturesRequest({fields: similarPictureFields()}),
                }),
                limit: 5,
              }),
            }),
            limit: 12,
            options: new PictureListOptions({
              dfDistance: new DfDistanceListOptions({}),
            }),
            order: PicturesRequest.Order.ORDER_DF_DISTANCE_SIMILARITY,
            page,
            paginator: true,
          }),
        )
        .pipe(
          map((response) => ({
            paginator: response.paginator,
            pictures: response.items || [],
          })),
        ),
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 216,
      title: $localize`Similar pictures`,
    });
  }

  protected hideSimilar(picture: Picture, dfDistance: DfDistance) {
    const key = `${picture.id}-${dfDistance.dstPictureId}`;
    this.hideLoadingKey.set(key);

    this.#picturesClient
      .deleteSimilar(new DeleteSimilarRequest({id: picture.id, similarPictureId: dfDistance.dstPictureId}))
      .pipe(
        catchError((error: unknown) => {
          this.hideLoadingKey.set(null);
          this.#toastService.handleError(error);
          return EMPTY;
        }),
      )
      .subscribe({
        next: () => {
          this.hideLoadingKey.set(null);
          this.picturesResource.reload();
        },
      });
  }
}

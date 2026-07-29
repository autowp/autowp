import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit, signal} from '@angular/core';
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
import {BehaviorSubject, EMPTY} from 'rxjs';
import {catchError, map, shareReplay, switchMap} from 'rxjs/operators';

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
  imports: [AsyncPipe, PaginatorComponent, RouterLink, ThumbnailComponent],
  templateUrl: './similar.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesSimilarComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  readonly #change$ = new BehaviorSubject<void>(void 0);

  protected readonly hideLoadingKey = signal<null | string>(null);

  protected readonly data$ = this.#route.queryParamMap.pipe(
    switchMap((params) => {
      const qParams = new PicturesRequest({
        fields: new PictureFields({
          ...similarPictureFields(),
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
        page: parseInt(params.get('page') ?? '', 10),
        paginator: true,
      });

      return this.#change$.pipe(
        switchMap(() => this.#picturesClient.getPictures(qParams)),
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      );
    }),
    map((response) => ({
      paginator: response.paginator,
      pictures: response.items || [],
    })),
    shareReplay({bufferSize: 1, refCount: false}),
  );

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
          this.#change$.next();
        },
      });
  }
}

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {
  APIItem,
  ItemFields,
  ItemRequest,
  ItemType,
  PictureFields,
  PictureItemListOptions,
  PictureListOptions,
  PicturesRequest,
  PictureStatus,
} from '@grpc/spec.pb';
import {ItemsClient, PicturesClient} from '@grpc/spec.pbsc';
import {AuthService, Role} from '@services/auth.service';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {icon, latLng, Marker, marker, tileLayer} from 'leaflet';
import {RemarkModule} from 'ngx-remark';
import {EMPTY, Observable, of} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {ThumbnailComponent} from '../thumbnail/thumbnail/thumbnail.component';
import {ToastsService} from '../toasts/toasts.service';

@Component({
  selector: 'app-factories',
  imports: [RouterLink, LeafletModule, AsyncPipe, ThumbnailComponent, RemarkModule],
  templateUrl: './factories.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FactoryComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #auth = inject(AuthService);
  readonly #toastService = inject(ToastsService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #languageService = inject(LanguageService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);

  protected readonly item$: Observable<APIItem> = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((id) =>
      this.#itemsClient.item(
        new ItemRequest({
          fields: new ItemFields({
            description: true,
            fullText: true,
            location: true,
            nameHtml: true,
            nameText: true,
            relatedGroupPictures: true,
          }),
          id,
          language: this.#languageService.language,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    switchMap((factory) => {
      if (!factory || factory.itemTypeId !== ItemType.ITEM_TYPE_FACTORY) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }

      return of(factory);
    }),
    tap((factory) => {
      this.#pageEnv.set({
        pageId: 181,
        title: factory.nameText,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly pictures$ = this.item$.pipe(
    switchMap((factory) =>
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
          limit: 24,
          options: new PictureListOptions({
            pictureItem: new PictureItemListOptions({itemId: '' + factory.id}),
            status: PictureStatus.PICTURE_STATUS_ACCEPTED,
          }),
          order: PicturesRequest.Order.ORDER_CREATED_AT_DESC,
          paginator: false,
        }),
      ),
    ),
    catchError((err: unknown) => {
      this.#toastService.handleError(err);
      return EMPTY;
    }),
  );

  protected readonly map$ = this.item$.pipe(
    map((factory) => {
      if (!factory.location?.longitude || !factory.location.latitude) {
        return null;
      }

      const center = latLng([factory.location.latitude, factory.location.longitude]);
      const markers: Marker[] = [
        marker(center, {
          icon: icon({
            iconAnchor: [13, 41],
            iconSize: [25, 41],
            iconUrl: 'assets/marker-icon.png',
            shadowUrl: 'assets/marker-shadow.png',
          }),
        }),
      ];
      const options = {
        center,
        layers: [
          tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
          }),
        ],
        zoom: 17,
      };

      return {markers, options};
    }),
  );
}

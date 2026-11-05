import type {OnInit} from '@angular/core';
import type {FieldTree} from '@angular/forms/signals';
import type {LatLng, LeafletMouseEvent, Map, Marker, TileLayer} from 'leaflet';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, signal} from '@angular/core';
import {toObservable} from '@angular/core/rxjs-interop';
import {disabled, form, FormField, maxLength, submit, validate} from '@angular/forms/signals';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {LatLng as grpcLatLng} from '@grpc/google/type/latlng.pb';
import {Picture, PictureListOptions, PicturesRequest, UpdatePictureRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {NotFoundService} from '@services/not-found';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {icon, latLng, marker, tileLayer} from 'leaflet';
import {catchError, distinctUntilChanged, EMPTY, firstValueFrom, map, shareReplay, switchMap} from 'rxjs';

import {ToastsService} from '../../../../toasts/toasts.service';

interface MapOptions {
  center: LatLng;
  leafletOptions: {center: LatLng; layers: TileLayer[]; zoom: number};
  markers: Marker[];
}

interface PointForm {
  lat: string;
  lng: string;
}

function createMarker(lat: number, lng: number): Marker {
  return marker([lat, lng], {
    icon: icon({
      iconAnchor: [13, 41],
      iconSize: [25, 41],
      iconUrl: 'assets/marker-icon.png',
      shadowUrl: 'assets/marker-shadow.png',
    }),
  });
}

function normalizeLat(lat: number) {
  return Math.max(-90, Math.min(lat, 90));
}

function normalizeLng(lng: number) {
  lng = ((lng - 180) % 360) + 180;
  return ((lng + 180) % 360) - 180;
}

@Component({
  selector: 'app-moder-pictures-place',
  imports: [RouterLink, FormField, LeafletModule, AsyncPipe],
  templateUrl: './moder-pictures-item-place.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemPlace implements OnInit {
  readonly #router = inject(Router);
  readonly #notFound = inject(NotFoundService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  readonly #pointModel = signal({lat: '', lng: ''});
  readonly #form = form(this.#pointModel, (path) => {
    disabled(path, {when: ({state}) => state.submitting()});
    for (const coordinate of [path.lat, path.lng]) {
      maxLength(coordinate, 20);
      validate(coordinate, ({value}) =>
        value().trim() !== '' && !Number.isFinite(Number(value())) ? {kind: 'coordinate'} : undefined,
      );
    }
  });
  readonly #pointValues$ = toObservable(this.#pointModel);

  protected readonly picture$: Observable<Picture> = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    switchMap((id) =>
      this.#picturesClient.getPicture(
        new PicturesRequest({
          options: new PictureListOptions({id}),
        }),
      ),
    ),
    catchError(() => {
      this.#notFound.report();
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly form$ = this.picture$.pipe(
    map((picture) => {
      let lat = '',
        lng = '';
      if (picture.point) {
        lat = picture.point.latitude + '';
        lng = picture.point.longitude + '';
      }

      this.#pointModel.set({lat, lng});

      return this.#form;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly map$ = this.form$.pipe(
    switchMap(() => this.#pointValues$),
    map((formValues) => {
      let center = latLng(55.7423627, 37.6786422);

      const leafletOptions = {
        center: center,
        layers: [
          tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
          }),
        ],
        zoom: 8,
      };

      let lat = formValues.lat.trim() ? Number(formValues.lat) : NaN;
      let lng = formValues.lng.trim() ? Number(formValues.lng) : NaN;

      const markers: Marker[] = [];

      let ll = null;
      if (Number.isFinite(lat) && Number.isFinite(lng)) {
        lat = normalizeLat(lat);
        lng = normalizeLng(lng);
        ll = latLng([lat, lng]);
      }
      if (ll) {
        markers.push(createMarker(ll.lat, ll.lng));
        center = ll;
        leafletOptions.center = ll;
      }

      return {center, leafletOptions, markers};
    }),
  );

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_PICTURE,
    });
  }

  protected onMapReady(mapOptions: MapOptions, lmap: Map, form: FieldTree<PointForm>) {
    lmap.on('click', (event: LeafletMouseEvent) => {
      if (form().submitting()) return;
      const ll: LatLng = event.latlng;
      const lat = normalizeLat(ll.lat);
      const lng = normalizeLng(ll.lng);
      mapOptions.markers = [createMarker(lat, lng)];
      form().value.set({
        lat: lat + '',
        lng: lng + '',
      });
    });
  }

  protected doSubmit(field: FieldTree<PointForm>, picture: Picture): void {
    void submit(field, async () => {
      const value = field().value();
      try {
        await firstValueFrom(
          this.#picturesClient.updatePicture(
            new UpdatePictureRequest({
              picture: new Picture({
                id: picture.id,
                point: new grpcLatLng({
                  latitude: normalizeLat(Number(value.lat)),
                  longitude: normalizeLng(Number(value.lng)),
                }),
              }),
              updateMask: new FieldMask({paths: ['point']}),
            }),
          ),
        );
        void this.#router.navigate(['/moder/pictures', picture.id]);
      } catch (response: unknown) {
        this.#toastService.handleError(response);
      }
    });
  }
}

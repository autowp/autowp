import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, NgZone, OnInit} from '@angular/core';
import {FormControl, FormGroup, FormsModule, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LeafletModule} from '@bluehalo/ngx-leaflet';
import {LatLng as grpcLatLng} from '@grpc/google/type/latlng.pb';
import {Picture, PictureListOptions, PicturesRequest, UpdatePictureRequest} from '@grpc/spec.pb';
import {PicturesClient} from '@grpc/spec.pbsc';
import {FieldMask} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {icon, LatLng, latLng, LeafletMouseEvent, Map, Marker, marker, TileLayer, tileLayer} from 'leaflet';
import {EMPTY, Observable} from 'rxjs';
import {catchError, debounceTime, distinctUntilChanged, map, shareReplay, startWith, switchMap} from 'rxjs/operators';

import {ToastsService} from '../../../../toasts/toasts.service';

interface MapOptions {
  center: LatLng;
  leafletOptions: {center: LatLng; layers: TileLayer[]; zoom: number};
  markers: Marker[];
}

interface PointForm {
  lat: FormControl<null | string>;
  lng: FormControl<null | string>;
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
  return Math.max(lat, Math.min(lat, 90), -90);
}

function normalizeLng(lng: number) {
  lng = ((lng - 180) % 360) + 180;
  return ((lng + 180) % 360) - 180;
}

@Component({
  selector: 'app-moder-pictures-place',
  imports: [RouterLink, FormsModule, ReactiveFormsModule, LeafletModule, AsyncPipe],
  templateUrl: './place.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerPicturesItemPlaceComponent implements OnInit {
  readonly #router = inject(Router);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #zone = inject(NgZone);
  readonly #picturesClient = inject(PicturesClient);
  readonly #toastService = inject(ToastsService);

  readonly #form = new FormGroup<PointForm>({
    lat: new FormControl<string>(''),
    lng: new FormControl<string>(''),
  });

  protected readonly picture$: Observable<Picture> = this.#route.paramMap.pipe(
    map((params) => params.get('id') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
    switchMap((id) =>
      this.#picturesClient.getPicture(
        new PicturesRequest({
          options: new PictureListOptions({id}),
        }),
      ),
    ),
    catchError(() => {
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly form$ = this.picture$.pipe(
    map((picture) => {
      let lat = null,
        lng = null;
      if (picture?.point?.latitude && picture.point.longitude) {
        lat = picture.point.latitude + '';
        lng = picture.point.longitude + '';
      }

      this.#form.setValue({lat, lng});

      return this.#form;
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly map$ = this.form$.pipe(
    switchMap((form) => form.valueChanges.pipe(startWith(form.value))),
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

      let lat = formValues.lat ? parseFloat(formValues.lat) : NaN;
      let lng = formValues.lng ? parseFloat(formValues.lng) : NaN;

      const markers: Marker[] = [];

      let ll = null;
      if (!isNaN(lat) && !isNaN(lng)) {
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
      pageId: 72,
    });
  }

  protected onMapReady(mapOptions: MapOptions, lmap: Map, form: FormGroup<PointForm>) {
    lmap.on('click', (event: LeafletMouseEvent) => {
      this.#zone.run(() => {
        const ll: LatLng = event.latlng;
        const lat = normalizeLat(ll.lat);
        const lng = normalizeLng(ll.lng);
        mapOptions.markers = [createMarker(lat, lng)];
        form.setValue({
          lat: lat + '',
          lng: lng + '',
        });
      });
    });
  }

  protected doSubmit(form: FormGroup<PointForm>, picture: Picture) {
    this.#picturesClient
      .updatePicture(
        new UpdatePictureRequest({
          picture: new Picture({
            id: picture.id,
            point: new grpcLatLng({
              latitude: form.controls.lat.value ? parseFloat(form.controls.lat.value) : 0,
              longitude: form.controls.lng.value ? parseFloat(form.controls.lng.value) : 0,
            }),
          }),
          updateMask: new FieldMask({paths: ['point']}),
        }),
      )
      .pipe(
        catchError((response: unknown) => {
          this.#toastService.handleError(response);
          return EMPTY;
        }),
      )
      .subscribe(() => {
        this.#router.navigate(['/moder/pictures', picture.id]);
      });
  }
}

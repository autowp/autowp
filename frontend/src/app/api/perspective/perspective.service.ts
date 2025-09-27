import {inject, Injectable} from '@angular/core';
import {PicturesService} from '@rest/api/pictures.service';
import {GoautowpPerspective} from '@rest/model/goautowpPerspective';
import {Observable} from 'rxjs';
import {map, shareReplay} from 'rxjs/operators';

@Injectable({
  providedIn: 'root',
})
export class APIPerspectiveService {
  readonly #picturesService = inject(PicturesService);

  readonly #perspectives$: Observable<GoautowpPerspective[]> = this.#picturesService.picturesGetPerspectives().pipe(
    map((response) => (response.items ? response.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public getPerspectives$(): Observable<GoautowpPerspective[]> {
    return this.#perspectives$;
  }
}

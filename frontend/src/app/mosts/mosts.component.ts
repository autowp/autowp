import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {Observable} from 'rxjs';
import {debounceTime, distinctUntilChanged, map} from 'rxjs/operators';

import {MostsContentsComponent} from './contents/contents.component';

@Component({
  selector: 'app-mosts',
  imports: [RouterLink, MostsContentsComponent, AsyncPipe],
  templateUrl: './mosts.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MostsComponent implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly ratingCatname$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('rating_catname') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );
  protected readonly typeCatname$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('type_catname') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );
  protected readonly yearsCatname$: Observable<string> = this.#route.paramMap.pipe(
    map((params) => params.get('years_catname') ?? ''),
    distinctUntilChanged(),
    debounceTime(10),
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 21});
  }
}

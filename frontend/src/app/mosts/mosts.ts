import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {map} from 'rxjs';

import {MostsContents} from './contents/mosts-contents';

@Component({
  selector: 'app-mosts',
  imports: [RouterLink, MostsContents],
  templateUrl: './mosts.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Mosts implements OnInit {
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly ratingCatname = toSignal(
    this.#route.paramMap.pipe(map((params) => params.get('rating_catname') ?? '')),
    {requireSync: true},
  );
  protected readonly typeCatname = toSignal(
    this.#route.paramMap.pipe(map((params) => params.get('type_catname') ?? '')),
    {requireSync: true},
  );
  protected readonly yearsCatname = toSignal(
    this.#route.paramMap.pipe(map((params) => params.get('years_catname') ?? '')),
    {requireSync: true},
  );

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.MOSTS});
  }
}

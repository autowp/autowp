import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';

import {MostsContents} from './contents/mosts-contents';

@Component({
  selector: 'app-mosts',
  imports: [RouterLink, MostsContents],
  templateUrl: './mosts.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Mosts implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  readonly ratingCatname = input('', {
    transform: (value: string | undefined) => value ?? '',
  });
  readonly typeCatname = input('', {
    transform: (value: string | undefined) => value ?? '',
  });
  readonly yearsCatname = input('', {
    transform: (value: string | undefined) => value ?? '',
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.MOSTS});
  }
}

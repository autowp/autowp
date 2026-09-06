import type {OnInit} from '@angular/core';
import type {TwinsBrandsList, TwinsBrandsListItem} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {GetTwinsBrandsListRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {NameCountComponent} from '@utils/name-count/name-count.component';

function addCSS(document: Document, url: string) {
  const cssId = 'brands-css';
  if (!document.getElementById(cssId)) {
    const head = document.getElementsByTagName('head')[0];
    const link = document.createElement('link');
    link.id = cssId;
    link.rel = 'stylesheet';
    link.type = 'text/css';
    link.href = url;
    link.media = 'all';
    head.appendChild(link);
  }
}

@Component({
  selector: 'app-twins-index',
  imports: [RouterLink, AsyncPipe, NameCountComponent],
  templateUrl: './twins-index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsIndexComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);

  protected readonly brands$: Observable<TwinsBrandsList> = this.#itemsClient.getTwinsBrandsList(
    new GetTwinsBrandsListRequest({language: this.#languageService.language}),
  );

  protected readonly iconsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'twins-index-brand-icons',
    stream: () => this.#itemsClient.getBrandIcons(new Empty()),
  });

  // Decorative only (background image on each brand card) - silently omitted on a transient
  // iconsResource error rather than taking down the whole page over it.
  protected readonly iconsData = computed(() =>
    this.iconsResource.hasValue() ? this.iconsResource.value() : undefined,
  );

  constructor() {
    effect(() => {
      // Reads iconsData(), not iconsResource.value(): value() throws while the resource is in an
      // error state, and an effect that throws takes the error to the ErrorHandler on every
      // recompute rather than just leaving the decorative brand icons unstyled.
      const icons = this.iconsData();
      if (icons) {
        addCSS(this.#document, icons.css);
      }
    });
  }

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.TWINS});
  }

  protected cssClass(item: TwinsBrandsListItem): string {
    return item.catname.replace(/\./g, '_');
  }
}

import type {OnInit} from '@angular/core';
import type {BrandsListCharacter} from '@grpc/spec.pb';

import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {GetBrandsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';
import {tap} from 'rxjs';

import {BrandsItemComponent} from './item/item.component';

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
  selector: 'app-brands',
  imports: [RouterLink, BrandsItemComponent],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);

  protected readonly itemsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'brands-page',
    stream: () =>
      this.#itemsClient.getBrands(
        new GetBrandsRequest({
          language: this.#languageService.language,
        }),
      ),
  });

  // Was a raw Observable, only subscribed once the template's nested @for loops (three levels
  // deep, gated behind itemsResource) reached the `| async`. That races Angular's SSR
  // whenStable() check the same way the Articles list author lookup did — resource() registers
  // its pending task through Angular's reactive graph (an effect, scheduled at construction)
  // rather than lazy template subscription, so it doesn't race.
  protected readonly iconsResource = rxResource({
    id: 'brands-icons',
    stream: () =>
      this.#itemsClient.getBrandIcons(new Empty()).pipe(
        tap((icons) => {
          addCSS(this.#document, icons.css);
        }),
      ),
  });

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.BRANDS});
  }

  // A button rather than a link to the fragment, which is what this used to be: index.html carries
  // a <base href>, and a bare `#char42` href resolves against *that*, not against the current URL -
  // so opening one in a new tab landed on the index page with a fragment nothing on it matches.
  // Scrolling the current page is all these do, and a button is what that is.
  protected scrollTo(info: BrandsListCharacter): void {
    const element = this.#document.getElementById('char' + info.id);
    if (element) {
      element.scrollIntoView({behavior: 'smooth'});
    }
  }

  // resource.value() throws while its resource is in an error state - hasValue() is the reactive
  // guard against that, since the template reads iconsResource.value() without an error() check
  // of its own (a missing icon set just leaves the brand icons unrendered).
  protected readonly iconsData = computed(() =>
    this.iconsResource.hasValue() ? this.iconsResource.value() : undefined,
  );

  protected readonly errorMessage = errorMessage;
}

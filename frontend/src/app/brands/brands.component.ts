import {DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {BrandsListCharacter, GetBrandsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
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
    this.#pageEnv.set({pageId: 61});
  }

  protected scrollTo(info: BrandsListCharacter) {
    const element = this.#document.getElementById('char' + info.id);
    if (element) {
      element.scrollIntoView({behavior: 'smooth'});
    }
    return false;
  }
}

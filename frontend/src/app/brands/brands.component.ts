import {AsyncPipe, DOCUMENT} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {BrandIcons, BrandsListCharacter, GetBrandsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {Observable} from 'rxjs';
import {shareReplay, tap} from 'rxjs/operators';

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
  imports: [RouterLink, BrandsItemComponent, AsyncPipe],
  templateUrl: './brands.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BrandsComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #document = inject(DOCUMENT);

  protected readonly itemsResource = rxResource({
    stream: () =>
      this.#itemsClient.getBrands(
        new GetBrandsRequest({
          language: this.#languageService.language,
        }),
      ),
  });

  protected readonly icons$: Observable<BrandIcons> = this.#itemsClient.getBrandIcons(new Empty()).pipe(
    tap((icons) => {
      addCSS(this.#document, icons.css);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

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

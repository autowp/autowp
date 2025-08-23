import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {RouterLink} from '@angular/router';
import {APIBrandsListCharacter, GetBrandsRequest} from '@grpc/spec.pb';
import {ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {EMPTY, Observable} from 'rxjs';
import {catchError, shareReplay, tap} from 'rxjs/operators';

import {ToastsService} from '../toasts/toasts.service';
import {BrandsItemComponent} from './item/item.component';
import {AutowpService} from '@rest/api/autowp.service';
import {BrandIcons} from '@rest/model/brandIcons';

function addCSS(url: string) {
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
  readonly #toastService = inject(ToastsService);
  readonly #autowp = inject(AutowpService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);

  protected readonly items$ = this.#itemsClient
    .getBrands(
      new GetBrandsRequest({
        language: this.#languageService.language,
      }),
    )
    .pipe(
      catchError((response: unknown) => {
        this.#toastService.handleError(response);
        return EMPTY;
      }),
    );

  protected readonly icons$: Observable<BrandIcons> = this.#autowp.autowpGetBrandIcons().pipe(
    tap((icons) => {
      addCSS(icons.css);
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  ngOnInit(): void {
    setTimeout(() => this.#pageEnv.set({pageId: 61}), 0);
  }

  protected scrollTo(info: APIBrandsListCharacter) {
    const element = document.getElementById('char' + info.id);
    if (element) {
      element.scrollIntoView({behavior: 'smooth'});
    }
    return false;
  }
}

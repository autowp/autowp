import {ChangeDetectionStrategy, Component, computed, effect, inject} from '@angular/core';
import {rxResource, toSignal} from '@angular/core/rxjs-interop';
import {DomSanitizer} from '@angular/platform-browser';
import {ActivatedRoute, Router} from '@angular/router';
import {GetSpecificationsRequest, ItemFields, ItemRequest} from '@grpc/spec.pb';
import {AttrsClient, ItemsClient} from '@grpc/spec.pbsc';
import {LanguageService} from '@services/language';
import {PageEnvService} from '@services/page-env.service';
import {isNotFoundError, notFoundError} from 'app/grpc';
import {map, of} from 'rxjs';

@Component({
  selector: 'app-twins-group-specifications',
  imports: [],
  templateUrl: './specifications.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TwinsGroupSpecificationsComponent {
  readonly #route = inject(ActivatedRoute);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);
  readonly #languageService = inject(LanguageService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #sanitizer = inject(DomSanitizer);

  readonly #groupId = toSignal(this.#route.parent!.paramMap.pipe(map((params) => params.get('group') ?? '')), {
    requireSync: true,
  });

  protected readonly groupResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: `twins-group-specifications-group-${this.#groupId()}`,
    params: () => this.#groupId(),
    stream: ({params: id}) =>
      id
        ? this.#itemsClient.item(
            new ItemRequest({
              fields: new ItemFields({nameText: true}),
              id,
              language: this.#languageService.language,
            }),
          )
        : notFoundError(),
  });

  protected readonly htmlResource = rxResource({
    id: `twins-group-specifications-html-${this.#groupId()}`,
    params: () => this.#groupId(),
    stream: ({params: id}) =>
      id
        ? this.#attrsClient.getChildSpecifications(
            new GetSpecificationsRequest({itemId: id, language: this.#languageService.language}),
          )
        : of(null),
  });

  protected readonly html = computed(() => {
    const response = this.htmlResource.value();

    // eslint-disable-next-line sonarjs/no-angular-bypass-sanitization
    return response ? this.#sanitizer.bypassSecurityTrustHtml(response.html) : null;
  });

  constructor() {
    effect(() => {
      if (isNotFoundError(this.groupResource.error())) {
        void this.#router.navigate(['/error-404'], {skipLocationChange: true});
        return;
      }

      const group = this.groupResource.value();
      if (group) {
        this.#pageEnv.set({
          pageId: 27,
          title: $localize`Specifications of ${group.nameText}`,
        });
      }
    });
  }
}

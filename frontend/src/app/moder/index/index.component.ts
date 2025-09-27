import {AfterViewInit, ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';

@Component({
  selector: 'app-moder-index',
  imports: [RouterLink],
  templateUrl: './index.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerIndexComponent implements AfterViewInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly ItemType = ItemType;

  ngAfterViewInit() {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 67,
        }),
      0,
    );
  }
}

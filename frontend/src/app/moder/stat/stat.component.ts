import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {ItemsClient} from '@grpc/spec.pbsc';
import {NgbProgressbar} from '@ng-bootstrap/ng-bootstrap';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';

@Component({
  selector: 'app-moder-stat',
  imports: [RouterLink, NgbProgressbar],
  templateUrl: './stat.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerStatComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);
  readonly #itemsClient = inject(ItemsClient);

  protected readonly statsResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-stat-page',
    stream: () => this.#itemsClient.getStats(new Empty()),
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 119,
    });
  }
}

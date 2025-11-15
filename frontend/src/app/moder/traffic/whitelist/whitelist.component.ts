import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {Router, RouterLink} from '@angular/router';
import {DeleteTrafficWhitelistItemRequest, TrafficWhitelistItem} from '@grpc/spec.pb';
import {TrafficClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {PageEnvService} from '@services/page-env.service';
import {BehaviorSubject, combineLatest, EMPTY, Observable} from 'rxjs';
import {catchError, map} from 'rxjs/operators';

@Component({
  selector: 'app-moder-traffic-whitelist',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './whitelist.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerTrafficWhitelistComponent implements OnInit {
  readonly #trafficClient = inject(TrafficClient);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<TrafficWhitelistItem[]> = combineLatest([
    this.#trafficClient.getTrafficWhitelistItems(new Empty()),
    this.#reload$,
  ]).pipe(
    map(([response]) => (response.items ? response.items : [])),
    catchError(() => {
      this.#router.navigate(['/error-404'], {
        skipLocationChange: true,
      });
      return EMPTY;
    }),
  );

  protected deleteItem(item: TrafficWhitelistItem) {
    this.#trafficClient
      .deleteTrafficWhitelistItem(new DeleteTrafficWhitelistItemRequest({ip: item.ip}))
      .subscribe(() => {
        this.#reload$.next();
      });
  }

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 77,
    });
  }
}

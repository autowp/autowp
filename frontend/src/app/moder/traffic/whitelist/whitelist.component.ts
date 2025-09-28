import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {Router, RouterLink} from '@angular/router';
import {TrafficService} from '@rest/api/traffic.service';
import {GoautowpTrafficWhitelistItem} from '@rest/model/goautowpTrafficWhitelistItem';
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
  readonly #trafficService = inject(TrafficService);
  readonly #router = inject(Router);
  readonly #pageEnv = inject(PageEnvService);

  readonly #reload$ = new BehaviorSubject<void>(void 0);

  protected readonly items$: Observable<GoautowpTrafficWhitelistItem[]> = combineLatest([
    this.#trafficService.trafficGetWhitelistItems(),
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

  protected deleteItem(item: GoautowpTrafficWhitelistItem) {
    this.#trafficService.trafficDeleteTrafficWhitelistItem({ip: item.ip}).subscribe(() => {
      this.#reload$.next();
    });
  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.#pageEnv.set({
          layout: {isAdminPage: true},
          pageId: 77,
        }),
      0,
    );
  }
}

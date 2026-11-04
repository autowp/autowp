import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {environment} from '@environment/environment';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';

@Component({
  selector: 'app-account-access',
  imports: [RouterLink],
  templateUrl: './account-access.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AccountAccess implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly changePasswordUrl =
    environment.keycloak.url + '/realms/' + environment.keycloak.realm + '/account/#/security/device-activity';

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.ACCOUNT_ACCESS});
  }
}

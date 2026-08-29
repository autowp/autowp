import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkComponent} from 'ngx-remark';

// Like policy.component/terms.component: one $localize block rendered as Markdown by <remark>.
// The two linked .txt files are generated at build/release time (frontend: Angular's own
// extractLicenses; backend: `go-licenses save` in publish-goautowp) and served by the
// frontend-assets nginx sidecar from a shared volume - see frontend-assets-cm.yaml and
// serve.yaml's goautowp-licenses-copy/frontend-assets-copy init containers. Not committed to
// this repo, so nothing here needs to change when a dependency is added or bumped.
const licensesText = $localize`:@@licenses-body:This Site is built on open source software.

## Frontend

Everything that runs in your browser, and the server that renders pages for you, bundles
third-party components under permissive licences (MIT, Apache-2.0, BSD, and similar). The full
list of components and their licence texts: [frontend-third-party-licenses.txt](/frontend-third-party-licenses.txt).

## Backend

The server that answers the Site's requests is built from open source components under the same
kinds of licences. The full list: [backend-third-party-licenses.txt](/backend-third-party-licenses.txt).

## Our own code

The Site's own source code is published on [GitHub](https://github.com/autowp/autowp) under the
licence stated there.
`;

@Component({
  selector: 'app-licenses',
  imports: [RouterLink, RemarkComponent],
  templateUrl: './licenses.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LicensesComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly licensesText = licensesText;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}

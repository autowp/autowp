import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkComponent} from 'ngx-remark';

// Like policy.component/terms.component: one $localize block rendered as Markdown by <remark>.
// The operator chose to disclose only an email/feedback-form contact point, not a real name or
// address - keep this page to that scope rather than inventing operator details it doesn't have.
const legalNoticeText = $localize`:@@legal-notice-body:This Site is a personal, non-commercial project run by an individual, not a registered company.

## Contact

For any question, complaint, or legal notice concerning the Site, use the [feedback form](/feedback) or email [autowp@gmail.com](mailto:autowp@gmail.com).

## Responsibility for content

Decisions about content on the Site — moderation, removal, and takedown — are made through the process described in the [Terms of Service](/tos) and the [Copyright and content complaints](/copyright) page.
`;

@Component({
  selector: 'app-legal-notice',
  imports: [RouterLink, RemarkComponent],
  templateUrl: './legal-notice.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LegalNoticeComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly legalNoticeText = legalNoticeText;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}

import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {RemarkComponent} from 'ngx-remark';

// Same shape as tos.component / policy.component: one $localize Markdown block rendered by
// <remark>, explicit @@copyright-policy-body id so small wording edits don't re-key and orphan
// the nine translations. Keep the "Last updated" date in the first line in sync with real changes.
const copyrightText = $localize`:@@copyright-policy-body:*Last updated: 29 August 2026*

The websites **wheelsage.org** and **autowp.ru** (the "Site") host pictures and text submitted by their visitors. We respect the rights of copyright and other rights holders and act on valid complaints. This page explains how to report material on the Site that infringes your rights, and how someone whose upload was removed can respond.

## Reporting an infringement

The quickest way is the **"Report"** button shown on every picture and comment — choose "Copyright infringement" and, if you can, add the details below.

You can also email us at [autowp@gmail.com](mailto:autowp@gmail.com) with the subject line "Copyright complaint". Please include:

* identification of the work you say is infringed (for example, a link to the original, or a description);
* the address (URL) of the material on the Site that you are complaining about;
* your name and contact details, and, if you are acting for the rights holder, in what capacity;
* a statement that you believe in good faith that the use is not authorised by the rights holder, its agent, or the law;
* a statement that the information in your complaint is accurate, and that you are the rights holder or authorised to act on their behalf.

We may pass the information in your complaint, including your identity, to the person who uploaded the material, so that they can respond.

## What we do

When we receive a valid complaint we remove or disable access to the material within a reasonable time. We tell the person who uploaded it why it was removed and how to respond, unless the law prevents us from doing so.

Accounts of users who repeatedly upload infringing material are suspended or closed.

## If your upload was removed

If material you uploaded was removed and you believe that was a mistake — because the work is yours, you have permission to use it, or it was misidentified — you can ask us to restore it. Use the appeal link in the message we sent you, or email [autowp@gmail.com](mailto:autowp@gmail.com), and include:

* the address (URL) or identifier of the material that was removed;
* the reason you believe the removal was a mistake;
* your name and contact details, and a statement that the information is accurate.

We may share your response, including your identity, with the person who complained. If they do not pursue the matter further, we may restore the material.

## Trademarks, privacy, and other rights

Use the same button or email address to report material that infringes a trademark, discloses private information, or uses someone's image or name without the consent the law requires. Describe the right you are relying on and identify the material as above.

## Contact

[autowp@gmail.com](mailto:autowp@gmail.com)
`;

@Component({
  selector: 'app-copyright',
  imports: [RouterLink, RemarkComponent],
  templateUrl: './copyright.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CopyrightComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly copyrightText = copyrightText;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}

import type {UserContact} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, computed, input} from '@angular/core';
import {socialContactUrl, socialPlatform} from '@services/user-contact';

interface RenderedContact {
  bi?: string;
  name: string;
  svg?: string;
  url: string;
}

@Component({
  selector: 'app-social-contact-list',
  imports: [],
  templateUrl: './social-contact-list.component.html',
  styleUrl: './social-contact-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SocialContactListComponent {
  readonly contacts = input<UserContact[]>([]);

  protected readonly rendered = computed<RenderedContact[]>(() =>
    this.contacts()
      .map((contact): null | RenderedContact => {
        const platform = socialPlatform(contact.platform);
        if (!platform) {
          return null;
        }
        return {
          name: platform.name,
          url: socialContactUrl(contact.platform, contact.username),
          bi: platform.bi,
          svg: platform.svg,
        };
      })
      .filter((item): item is RenderedContact => item !== null),
  );
}

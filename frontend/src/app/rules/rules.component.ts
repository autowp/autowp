import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {RemarkModule} from 'ngx-remark';

const rulesMarkdown = $localize`:@@rules:
1. ## Common
  1. When posting any materials or fragments of them, be sure to indicate the source
  2. Simultaneous use of several accounts by one user is prohibited.
  3. Profanity, obscene expressions, etc. are prohibited anywhere on the site, in any form.
  4. Insulting site visitors is prohibited
  5. Raising questions of politics, religion, sexual preferences and the like is prohibited, unless directly related to the subject of discussion or specifically agreed otherwise
  6. Discussing moderators' actions anywhere other than in specially designated places or personal correspondence with them is prohibited
  7. Publishing private correspondence or any other information discrediting other site members is prohibited
  8. Using obscene images as avatars or photos is prohibited
2. ## Comments
  1. Discussing anything other than the subject being commented on is prohibited
  2. Writing meaningless messages containing only a "smiley" or another way of signaling mood is prohibited
  3. Incorrect messages will be deleted without notice
3. ## Forum
  1. Writing in all caps, perceived as shouting, is prohibited
  2. "Off-topic" posting of any kind is prohibited
  3. Creating identical topics across multiple threads is prohibited
  4. Raising the question of the "best car" without specifying the intended use is prohibited
4. ## Catalogue rules
  ### Naming cars
  1. Car names are assigned according to their original name, as shown on the body or in the manufacturer's official publications
  2. The car name is written with a capital letter, but writing entirely in lowercase or entirely in uppercase is allowed if that matches the car's official name
  3. It is desirable to specify the body/series/model code
  4. Use of Cyrillic, Latin, and individual Greek alphabet characters is allowed
  5. Use of digits is allowed
  6. Use of special characters is allowed if they were used by the manufacturer
  7. To distinguish cars by body type, it is allowed to add it to the name
  8. It is allowed to add information identifying the sales market to the name, to distinguish cars by this attribute. For example, UK-spec, North America, ZA-spec
  9. Car names adapted to the site's language version follow the same rules, but have no restriction on the alphabet used
`;

@Component({
  selector: 'app-rules',
  imports: [RouterLink, RemarkModule],
  templateUrl: './rules.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RulesComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly rulesMarkdown = rulesMarkdown;

  ngOnInit(): void {
    this.#pageEnv.set({pageId: 106});
  }
}

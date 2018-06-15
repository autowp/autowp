import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges
} from '@angular/core';
import { APIItem } from '../../../../services/item';
import { HttpClient } from '@angular/common/http';
import {
  APIItemLanguage,
  ItemLanguageService
} from '../../../../services/item-language';
import { ContentLanguageService } from '../../../../services/content-language';

@Component({
  selector: 'app-moder-items-item-name',
  templateUrl: './name.component.html'
})
@Injectable()
export class ModerItemsItemNameComponent implements OnChanges {
  @Input() item: APIItem;

  public loading = 0;

  public itemLanguages: APIItemLanguage[] = [];
  public currentLanguage: any = null;

  constructor(
    private http: HttpClient,
    private itemLanguageService: ItemLanguageService,
    private contentLanguage: ContentLanguageService
  ) { }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.loading++;
      this.contentLanguage.getList().toPromise().then(
        contentLanguages => {
          this.currentLanguage = contentLanguages[0];

          const languages = new Map<string, APIItemLanguage>();

          for (const language of contentLanguages) {
            languages.set(language, {
              language: language,
              name: null,
              text: null,
              full_text: null,
              text_id: null,
              full_text_id: null
            });
          }

          this.itemLanguageService
            .getItems(this.item.id)
            .subscribe(response => {
              for (const itemLanguage of response.items) {
                languages.set(itemLanguage.language, itemLanguage);
              }

              this.itemLanguages = Array.from(languages.values());
            });
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
    }
  }

  public saveLanguages() {
    for (const language of this.itemLanguages) {
      this.loading++;
      this.http
        .put<void>(
          '/api/item/' + this.item.id + '/language/' + language.language,
          {
            name: language.name,
            text: language.text,
            full_text: language.full_text
          }
        )
        .subscribe(
          response => {
            this.loading--;
          },
          response => {
            this.loading--;
          }
        );
    }
  }
}

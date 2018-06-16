import {
  Component,
  Injectable,
  OnInit,
  OnChanges,
  Input,
  SimpleChanges
} from '@angular/core';
import { APIItem } from '../../../../services/item';
import { PictureService, APIPicture } from '../../../../services/picture';
import { chunkBy } from '../../../../chunk';

@Component({
  selector: 'app-moder-items-item-pictures',
  templateUrl: './pictures.component.html'
})
@Injectable()
export class ModerItemsItemPicturesComponent implements OnInit, OnChanges {
  @Input() item: APIItem;

  public loading = 0;
  public canUseTurboGroupCreator = false;
  public pictures: APIPicture[];
  public picturesChunks: APIPicture[][] = [];

  constructor(private pictureService: PictureService) {}

  ngOnInit(): void {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.canUseTurboGroupCreator =
        [1, 2].indexOf(this.item.item_type_id) !== -1;

      this.loading++;
      this.pictureService
        .getPictures({
          exact_item_id: this.item.id,
          limit: 500,
          fields:
            'owner,thumb_medium,moder_vote,votes,similar,comments_count,perspective_item,name_html,name_text,views',
          order: 14
        })
        .subscribe(
          response => {
            this.pictures = response.pictures;
            this.picturesChunks = chunkBy<APIPicture>(this.pictures, 6);
            this.loading--;
          },
          () => {
            this.loading--;
          }
        );
    }
  }
}

import { sprintf } from 'sprintf-js';
import {
  Component,
  Injectable,
  Input,
  OnChanges,
  SimpleChanges,
  OnInit,
  Output,
  EventEmitter
} from '@angular/core';
import { APIItem } from '../../services/item';
import {
  APIVehicleType,
  VehicleTypeService
} from '../../services/vehicle-type';
import { SpecService, APISpec } from '../../services/spec';
import { LanguageService } from '../../services/language';
import Notify from '../../notify';
import { Observable, from } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { VehicleTypesModalComponent } from '../vehicle-types-modal/vehicle-types-modal.component';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';

function specsToPlain(
  options: ItemMetaFormAPISpec[],
  deep: number
): ItemMetaFormAPISpec[] {
  const result: ItemMetaFormAPISpec[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of specsToPlain(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

function vehicleTypesToPlain(
  options: ItemMetaFormAPIVehicleType[],
  deep: number
): ItemMetaFormAPIVehicleType[] {
  const result: ItemMetaFormAPIVehicleType[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of vehicleTypesToPlain(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

interface ItemMetaFormAPIVehicleType extends APIVehicleType {
  deep?: number;
}

interface ItemMetaFormAPISpec extends APISpec {
  deep?: number;
}

@Component({
  selector: 'app-item-meta-form',
  templateUrl: './item-meta-form.component.html',
  styleUrls: ['./styles.scss']
})
@Injectable()
export class ItemMetaFormComponent implements OnChanges, OnInit {

  @Input() item: APIItem;
  @Input() submitNotify: Function;
  @Input() parent: APIItem;
  @Input() invalidParams: any;
  @Input() hideSubmit: boolean;
  @Input() disableIsGroup: boolean;
  @Input() vehicleTypeIDs: number[] = [];
  @Output() submit = new EventEmitter<void>();

  public vehicleTypes: APIVehicleType[];

  public loading = 0;
  public todayOptions = [
    {
      value: null,
      name: '--'
    },
    {
      value: false,
      name: 'moder/vehicle/today/ended'
    },
    {
      value: true,
      name: 'moder/vehicle/today/continue'
    }
  ];

  public producedOptions = [
    {
      value: false,
      name: 'moder/item/produced/about'
    },
    {
      value: true,
      name: 'moder/item/produced/exactly'
    }
  ];
  public center = {
    lat: 55.7423627,
    lng: 37.6786422,
    zoom: 8
  };

  public markers: any = {};
  public tiles = {
    url: 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    options: {
      attribution:
        '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }
  };
  public name_maxlength = 100; // DbTable\Item::MAX_NAME
  public full_name_maxlength = 255; // BrandModel::MAX_FULLNAME
  public body_maxlength = 20;
  public model_year_max: number;
  public year_max: number;
  public specOptions: any[] = [];
  public monthOptions: any[];
  private isConceptOptions = [
    {
      value: false,
      name: 'moder/vehicle/is-concept/no'
    },
    {
      value: true,
      name: 'moder/vehicle/is-concept/yes'
    },
    {
      value: 'inherited',
      name: 'moder/vehicle/is-concept/inherited'
    }
  ];
  public defaultSpecOptions = [
    {
      id: null,
      short_name: '--',
      deep: 0
    },
    {
      id: 'inherited',
      short_name: 'inherited',
      deep: 0
    }
  ];

  constructor(
    private specService: SpecService,
    private vehicleTypeService: VehicleTypeService,
    private languageService: LanguageService,
    private http: HttpClient,
    private modalService: NgbModal
  ) {
    if (this.item && this.item.lat && this.item.lng) {
      this.markers.point = {
        lat: this.item ? this.item.lat : null,
        lng: this.item ? this.item.lng : null,
        focus: true
      };
    }

    /*$scope.$on('leafletDirectiveMap.click', function(event: any, e: any) {
      const latLng = e.leafletEvent.latlng;
      this.markers.point = {
        lat: latLng.lat,
        lng: latLng.lng,
        focus: true
      };
      this.item.lat = latLng.lat;
      this.item.lng = latLng.lng;
    });*/

    this.model_year_max = new Date().getFullYear() + 10;
    this.year_max = new Date().getFullYear() + 10;

    this.monthOptions = [
      {
        value: null,
        name: '--'
      }
    ];

    const date = new Date(Date.UTC(2000, 1, 1, 0, 0, 0, 0));
    for (let i = 0; i < 12; i++) {
      date.setMonth(i);
      const language = this.languageService.getLanguage();
      if (language) {
        const month = date.toLocaleString(language, { month: 'long' });
        this.monthOptions.push({
          value: i + 1,
          name: sprintf('%02d - %s', i + 1, month)
        });
      }
    }

    this.loading++;
    this.specService.getSpecs().then(
      types => {
        this.loading--;
        this.specOptions = specsToPlain(types, 0);
      },
      response => {
        this.loading--;
        Notify.response(response);
      }
    );
  }

  ngOnInit(): void {
    this.loadVehicleTypes();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.vehicleTypeIDs) {
      this.loadVehicleTypes();
    }
  }

  public matchingFn(value: string, target: APIVehicleType): boolean {
    const targetValue = target['nameTranslated'].toString();
    return (
      targetValue && targetValue.toLowerCase().indexOf(value.toLowerCase()) >= 0
    );
  }

  public loadVehicleTypes(): void {
    this.vehicleTypeService.getTypesById(this.vehicleTypeIDs).then(types => {
      this.vehicleTypes = types;
    });
  }

  public coordsChanged() {
    const lat = this.item.lat;
    const lng = this.item.lng;
    if (this.markers.point) {
      this.markers.point.lat = isNaN(lat) ? 0 : lat;
      this.markers.point.lng = isNaN(lng) ? 0 : lng;
    } else {
      this.markers.point = {
        lat: isNaN(lat) ? 0 : lat,
        lng: isNaN(lng) ? 0 : lng,
        focus: true
      };
    }
    this.center.lat = isNaN(lat) ? 0 : lat;
    this.center.lng = isNaN(lng) ? 0 : lng;
  }

  public doSubmit(event) {
    event.preventDefault();
    event.stopPropagation();
    this.submit.emit();
    return false;
  }

  public getIsConceptOptions(parent: APIItem) {
    this.isConceptOptions[2].name = parent
      ? parent.is_concept
        ? 'moder/vehicle/is-concept/inherited-yes'
        : 'moder/vehicle/is-concept/inherited-no'
      : 'moder/vehicle/is-concept/inherited';

    return this.isConceptOptions;
  }

  public getSpecOptions(specOptions: any[]): any[] {
    return this.defaultSpecOptions.concat(specOptions);
  }

  public showVehicleTypesModal() {
    const modalRef = this.modalService.open(VehicleTypesModalComponent, {
      size: 'lg',
      centered: true
    });

    modalRef.componentInstance.ids = this.vehicleTypeIDs;
    modalRef.componentInstance.changed.subscribe(() => {
      this.loadVehicleTypes();
    });
  }
}

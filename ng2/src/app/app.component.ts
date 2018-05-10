import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from './services/auth.service';
import { ACLService } from './services/acl.service';
import Notify from './notify';
import { APIUser } from './services/user';
import { MessageService } from './services/message';
import { Page, PageService } from './services/page';

function replaceArgs(str: string, args: {[key: string]: string}): string {
  for (const key in args) {
    if (this.formErrors.hasOwnProperty(key)) {
      str = str.replace(key, args[key]);
    }
  }
  return str;
}

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.less']
})
export class AppComponent implements OnInit {
  public loginInvalidParams: any;
  public languages;
  public path; // = $location.path();
  public user; // = opt.user;
  public isModer; // = opt.isModer;
  public newPersonalMessages; // = opt.sidebar.newPersonalMessages;
  public mainMenu; // = opt.mainMenu;
  public mainMenuItems: Page[] = [];
  public secondaryMenuItems: Page[] = [];
  public mainInSecondaryItems: Page[] = [];
  public categories = [];
  public moderMenu; // = opt.moderMenu;
  public searchHostname; // = opt.searchHostname;
  public pageName = null;
  public title = 'WheelsAge';
  public pageId = null;
  public disablePageName = false;
  public needRight = false;
  public spanRight = 0;
  public spanCenter = 12;
  public isAdminPage = false;
  public loginForm = {
    login: '',
    password: '',
    remember: false
  };

  constructor(
    public auth: AuthService,
    public acl: ACLService,
    private router: Router,
    private translate: TranslateService,
    private http: HttpClient,
    private pages: PageService,
    private messageService: MessageService
  ) {
    translate.setTranslation('en', require('../languages/en.json'));
    translate.setDefaultLang('en');

    translate.use('en');

    this.auth.loggedIn$.subscribe(() => {
      this.updateRights();
    });
  }

  ngOnInit() {
    // this.updateRights();

    this.setSidebars(false);

    this.mainMenuItems = this.pages.getMenu(2);
    this.secondaryMenuItems = this.pages.getMenu(87);
    this.mainInSecondaryItems = [];
    for (const page of this.mainMenuItems) {
      if (this.isSecondaryMenuItem(page)) {
        this.mainInSecondaryItems.push(page);
      }
    }

    this.messageService.newMessagesCount.subscribe((value) => {
      this.newPersonalMessages = value;
    });
  }

  pageEnv(data) {
    this.setSidebars(data.layout.needRight);
    this.isAdminPage = data.layout.isAdminPage;
    this.disablePageName = !!data.disablePageName;

    const args = data.args ? data.args : {};
    const preparedUrlArgs: {[key: string]: string} = {};
    const preparedNameArgs: {[key: string]: string} = {};
    for (const key in args) {
      if (args.hasOwnProperty(key)) {
        preparedUrlArgs['%' + key + '%'] = encodeURIComponent(args[key]);
        preparedNameArgs['%' + key + '%'] = args[key];
      }
    }

    // PageService.setCurrent(data.pageId, preparedNameArgs);

    if (data.pageId) {
      let nameKey: string;
      let titleKey: string;
      if (data.name) {
        nameKey = data.name;
        titleKey = data.title ? data.title : data.name;
      } else {
        nameKey = 'page/' + data.pageId + '/name';
        titleKey = 'page/' + data.pageId + '/title';
      }
      this.translate.get([nameKey, titleKey]).subscribe(
        (translations: string[]) => {
          const name = replaceArgs(translations[0], preparedNameArgs);
          const title = replaceArgs(translations[1], preparedNameArgs);
          this.pageName = name;
          this.title = title ? title : name;
        },
        () => {
          this.pageName = nameKey;
          this.title = titleKey;
        }
      );
    } else {
      this.pageName = null;
      this.title = data.title ? data.title : null;
    }
  }

  isSecondaryMenuItem(page: Page): boolean {
    return [25, 117, 42].indexOf(page.id) !== -1;
  }

  doLogin() {
    this.http.post<void>('/api/login', this.loginForm).subscribe(
      () => {
        this.http.get<APIUser>('/api/user/me').subscribe(
          response => {
            this.user = response;
            this.router.navigate(['/login/ok']);
          },
          response => {
            Notify.response(response);
          }
        );
      },
      response => {
        if (response.status === 400) {
          this.loginInvalidParams = response.error.invalid_params;
        } else {
          Notify.response(response);
        }
      }
    );
  }

  updateRights() {}

  signOut(event) {
    console.log(event);
    event.preventDefault();

    this.auth.signOut().then(
      () => {},
      error => {
        console.log(error);
      }
    );
  }

  private setSidebars(right: boolean) {
    this.needRight = right;

    this.spanRight = right ? 4 : 0;
    this.spanCenter = 12 - this.spanRight;
  }
}

import { Component, OnInit } from '@angular/core';

import { Router } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { AuthService } from './services/auth.service';
import { ACLService } from './services/acl.service';
import Notify from './notify';
import { APIUser } from './services/user';
import { MessageService } from './services/message';
import { Page, PageService } from './services/page';
import { PageEnvService, LayoutParams } from './services/page-env.service';
import { Observable } from 'rxjs';
import { LanguageService } from './services/language';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.less']
})
export class AppComponent implements OnInit {
  private hosts = {
    en: {
      hostname: 'en.wheelsage.org',
      name: 'English',
      flag: 'flag-icon flag-icon-gb'
    },
    zh: {
      hostname: 'zh.wheelsage.org',
      name: '中文 (beta)',
      flag: 'flag-icon flag-icon-cn'
    },
    ru: {
      hostname: 'www.autowp.ru',
      name: 'Русский',
      flag: 'flag-icon flag-icon-ru'
    },
    'pt-br': {
      hostname: 'br.wheelsage.org',
      name: 'Português brasileiro',
      flag: 'flag-icon flag-icon-br'
    },
    fr: {
      hostname: 'fr.wheelsage.org',
      name: 'Français (beta)',
      flag: 'flag-icon flag-icon-fr'
    },
    be: {
      hostname: 'be.wheelsage.org',
      name: 'Беларуская',
      flag: 'flag-icon flag-icon-by'
    },
    uk: {
      hostname: 'uk.wheelsage.org',
      name: 'Українська (beta)',
      flag: 'flag-icon flag-icon-ua'
    }
  };
  public layoutParams$: Observable<LayoutParams>;
  public loginInvalidParams: any;
  public languages;
  public path; // = $location.path();
  public user: APIUser;
  public isModer; // = opt.isModer;
  public newPersonalMessages; // = opt.sidebar.newPersonalMessages;
  public searchHostname: string;
  public mainMenuItems: Page[] = [];
  public secondaryMenuItems: Page[] = [];
  public mainInSecondaryItems: Page[] = [];
  public categories = [];
  public moderMenu; // = opt.moderMenu;
  public loginForm = {
    login: '',
    password: '',
    remember: false
  };
  public language: string;

  constructor(
    public auth: AuthService,
    public acl: ACLService,
    private router: Router,
    private translate: TranslateService,
    private pages: PageService,
    private messageService: MessageService,
    private pageEnv: PageEnvService,
    private languageService: LanguageService
  ) {
    this.language = this.languageService.getLanguage();
    this.translate.setTranslation('en', require('../languages/en.json'));
    this.translate.setDefaultLang('en');

    this.translate.use('en');

    this.layoutParams$ = this.pageEnv.layoutParams$.asObservable();

    this.auth.loadMe().subscribe();

    this.auth.getUser().subscribe(user => {
      this.user = user;
    });

    let searchHostname = 'wheelsage.org';
    for (const itemLanguage in this.hosts) {
      if (itemLanguage === this.language) {
        searchHostname = this.hosts[itemLanguage]['hostname'];
      }
    }

    this.searchHostname = searchHostname;
  }

  ngOnInit() {
    this.mainInSecondaryItems = [];
    this.pages.getMenu(2).subscribe(items => {
      this.mainMenuItems = items;
      for (const page of this.mainMenuItems) {
        if (this.isSecondaryMenuItem(page)) {
          this.mainInSecondaryItems.push(page);
        }
      }
    });

    this.pages.getMenu(87).subscribe(items => {
      this.secondaryMenuItems = items;
    });

    this.messageService.getNew().subscribe(value => {
      this.newPersonalMessages = value;
    });
  }

  isSecondaryMenuItem(page: Page): boolean {
    return [25, 117, 42].indexOf(page.id) !== -1;
  }

  doLogin() {
    this.auth
      .login(
        this.loginForm.login,
        this.loginForm.password,
        this.loginForm.remember
      )
      .subscribe(
        () => {
          this.router.navigate(['/login/ok']);
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

  signOut(event) {
    event.preventDefault();

    this.auth.signOut().subscribe(
      () => {},
      error => {
        console.log(error);
      }
    );
  }
}

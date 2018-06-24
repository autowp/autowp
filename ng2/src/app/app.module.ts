import { BrowserModule } from '@angular/platform-browser';
import { NgModule } from '@angular/core';
import { HttpClientModule, HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterModule, Routes } from '@angular/router';
import { TranslateModule, TranslateCompiler } from '@ngx-translate/core';
import { TranslateMessageFormatCompiler } from 'ngx-translate-messageformat-compiler';
import { MomentModule } from 'ngx-moment';
import { NgbModule } from '@ng-bootstrap/ng-bootstrap';
import { NgPipesModule, BytesPipe } from 'ngx-pipes';
import { FileUploadModule } from 'ng2-file-upload';
import { ChartsModule } from 'ng2-charts/ng2-charts';
import { RecaptchaModule } from 'ng-recaptcha';
import { RecaptchaFormsModule } from 'ng-recaptcha/forms';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { LeafletModule } from '@asymmetrik/ngx-leaflet';

import { AppComponent } from './app.component';
import { IndexComponent } from './index/index.component';
import { PageNotFoundComponent } from './not-found.component';
import { SignInComponent } from './signin/signin.component';
import { ModerIndexComponent } from './moder/index/index.component';

import { ModerMenuComponent } from './moder-menu.component';
import { AuthGuard } from './auth.guard';
import { ModerPerspectivesComponent } from './moder/perspectives/perspectives.component';
import { ModerUsersComponent } from './moder/users/users.component';
import { PaginatorComponent } from './components/paginator/paginator.component';
import { UserComponent } from './components/user/user.component';
import { ModerHotlinksComponent } from './moder/hotlinks/hotlinks.component';
import { ModerTrafficComponent } from './moder/traffic/traffic.component';
import { APIService } from './services/api.service';
import { AuthService } from './services/auth.service';
import { ACLService, APIACL } from './services/acl.service';
import { AboutComponent } from './about/about.component';
import { ModerCommentsComponent } from './moder/comments/comments.component';
import { ModerItemParentComponent } from './moder/item-parent/item-parent.component';
import { ModerItemsAlphaComponent } from './moder/items/alpha/alpha.component';
import { ModerItemsItemSelectParentComponent } from './moder/items/item/select-parent/select-parent.component';
import { ModerItemsItemComponent } from './moder/items/item/item.component';
import { ModerItemsComponent } from './moder/items/items.component';
import { ModerPagesAddComponent } from './moder/pages/add/add.component';
import { ModerPagesEditComponent } from './moder/pages/edit/edit.component';
import { ModerPagesComponent } from './moder/pages/pages.component';
import { ModerPictureVoteTemplatesComponent } from './moder/picture-vote-templates/picture-vote-templates.component';
import { ModerPicturesItemAreaComponent } from './moder/pictures/item/area/area.component';
import { ModerPicturesItemCropComponent } from './moder/pictures/item/crop/crop.component';
import { ModerPicturesItemMoveComponent } from './moder/pictures/item/move/move.component';
import { ModerPicturesItemComponent } from './moder/pictures/item/item.component';
import { ModerPicturesComponent } from './moder/pictures/pictures.component';
import { ModerRightsComponent } from './moder/rights/rights.component';
import { ModerTrafficWhitelistComponent } from './moder/traffic/whitelist/whitelist.component';
import { ModerAttrsAttributeComponent } from './moder/attrs/attribute/attribute.component';
import { ModerAttrsZoneComponent } from './moder/attrs/zone/zone.component';
import { ModerAttrsComponent } from './moder/attrs/attrs.component';
import { AccountComponent } from './account/account.component';
import { AccountAccessComponent } from './account/access/access.component';
import { AccountAccountsComponent } from './account/accounts/accounts.component';
import { AccountContactsComponent } from './account/contacts/contacts.component';
import { AccountDeleteComponent } from './account/delete/delete.component';
import { AccountDeletedComponent } from './account/delete/deleted/deleted.component';
import { AccountEmailComponent } from './account/email/email.component';
import { AccountEmailcheckComponent } from './account/emailcheck/emailcheck.component';
import { AccountInboxPicturesComponent } from './account/inbox-pictures/inbox-pictures.component';
import { AccountMessagesComponent } from './account/messages/messages.component';
import { AccountProfileComponent } from './account/profile/profile.component';
import { AccountSidebarComponent } from './account/sidebar/sidebar.component';
import { AccountSpecsConflictsComponent } from './account/specs-conflicts/specs-conflicts.component';
import { ArticlesComponent } from './articles/articles.component';
import { ArticlesArticleComponent } from './articles/article/article.component';
import { BrandsComponent } from './brands/brands.component';
import { CarsAttrsChangeLogComponent } from './cars/attrs-change-log/attrs-change-log.component';
import { CarsDatelessComponent } from './cars/dateless/dateless.component';
import { CarsSpecificationsEditorComponent } from './cars/specifications-editor/specifications-editor.component';
import { CarsSpecsAdminComponent } from './cars/specs-admin/specs-admin.component';
import { ChartComponent } from './chart/chart.component';
import { CutawayComponent } from './cutaway/cutaway.component';
import { DonateComponent } from './donate/donate.component';
import { DonateLogComponent } from './donate/log/log.component';
import { DonateSuccessComponent } from './donate/success/success.component';
import { DonateVodComponent } from './donate/vod/vod.component';
import { DonateVodSelectComponent } from './donate/vod/select/select.component';
import { DonateVodSuccessComponent } from './donate/vod/success/success.component';
import { FactoryComponent } from './factories/factories.component';
import { FactoryItemsComponent } from './factories/items/items.component';
import { FeedbackComponent } from './feedback/feedback.component';
import { FeedbackSentComponent } from './feedback/sent/sent.component';
import { ForumsComponent } from './forums/forums.component';
import { ForumsMoveMessageComponent } from './forums/move-message/move-message.component';
import { ForumsMoveTopicComponent } from './forums/move-topic/move-topic.component';
import { ForumsNewTopicComponent } from './forums/new-topic/new-topic.component';
import { ForumsSubscriptionsComponent } from './forums/subscriptions/subscriptions.component';
import { ForumsTopicComponent } from './forums/topic/topic.component';
import { InboxComponent } from './inbox/inbox.component';
import { InfoSpecComponent } from './info/spec/spec.component';
import { InfoTextComponent } from './info/text/text.component';
import { LogComponent } from './log/log.component';
import { MapComponent } from './map/map.component';
import { MascotsComponent } from './mascots/mascots.component';
import { MostsComponent } from './mosts/mosts.component';
import { MuseumComponent } from './museum/museum.component';
import { NewComponent } from './new/new.component';
import { NewItemComponent } from './new/item/item.component';
import { PersonsComponent } from './persons/persons.component';
import { PersonsAuthorsComponent } from './persons/authors/authors.component';
import { PersonsPersonComponent } from './persons/person/person.component';
import { PulseComponent } from './pulse/pulse.component';
import { RestorePasswordComponent } from './restore-password/restore-password.component';
import { RestorePasswordNewComponent } from './restore-password/new/new.component';
import { RestorePasswordNewOkComponent } from './restore-password/new/ok/ok.component';
import { RestorePasswordSentComponent } from './restore-password/sent/sent.component';
import { RulesComponent } from './rules/rules.component';
import { SignupComponent } from './signup/signup.component';
import { SignupOkComponent } from './signup/ok/ok.component';
import { TelegramComponent } from './telegram/telegram.component';
import { TopViewComponent } from './top-view/top-view.component';
import { UploadComponent } from './upload/upload.component';
import { UploadSelectComponent } from './upload/select/select.component';
import { UsersRatingComponent } from './users/rating/rating.component';
import { UsersUserComponent } from './users/user/user.component';
import { UsersUserCommentsComponent } from './users/user/comments/comments.component';
import { UsersUserPicturesComponent } from './users/user/pictures/pictures.component';
import { UsersUserPicturesBrandComponent } from './users/user/pictures/brand/brand.component';
import { VotingComponent } from './voting/voting.component';
import { NewListItemComponent } from './new/list-item/list-item.component';
import { DonateVodSelectItemComponent } from './donate/vod/select/item/item.component';
import { ModerPictureMoveItemComponent } from './moder/pictures/item/move/item/item.component';
import { PictureService } from './services/picture';
import { ItemService } from './services/item';
import { InboxService } from './services/inbox';
import { MessageComponent } from './forums/message/message.component';
import { ReCaptchaService } from './services/recaptcha';
import { DonateService } from './services/donate';
import { ItemParentService } from './services/item-parent';
import { ArticleService } from './services/article';
import { ItemLinkService } from './services/item-link';
import { ModerRightsTreeComponent } from './moder/rights/tree/tree.component';
import { ModerItemsTooBigComponent } from './moder/items/too-big/too-big.component';
import { ModerItemsNewComponent } from './moder/items/new/new.component';
import { ItemLanguageService } from './services/item-language';
import { ModerItemsItemTreeComponent } from './moder/items/item/tree/tree.component';
import { ModerItemsItemSelectParentTreeItemComponent } from './moder/items/item/select-parent/tree-item/tree-item.component';
import { ModerItemsItemSelectParentTreeComponent } from './moder/items/item/select-parent/tree/tree.component';
import { ModerAttrsAttributeListComponent } from './moder/attrs/attribute-list/attribute-list.component';
import { ModerAttrsZoneAttributeListComponent } from './moder/attrs/zone/attribute-list/attribute-list.component';
import { ModerAttrsAttributeListOptionsTreeComponent } from './moder/attrs/attribute/list-options-tree/list-options-tree.component';
import { InfoSpecRowComponent } from './info/spec/row/row.component';
import { PastTimeIndicatorComponent } from './components/past-time-indicator/past-time-indicator.component';
import { UploadSelectTreeItemComponent } from './upload/select/tree-item/tree-item.component';
import { ThumbnailComponent } from './components/thumbnail/thumbnail.component';
import { VotingService } from './services/voting';
import { CommentsListComponent } from './components/comments/list/list.component';
import { CommentsComponent } from './components/comments/comments.component';
import { CommentsFormComponent } from './components/comments/form/form.component';
import { MarkdownEditComponent } from './components/markdown-edit/markdown-edit.component';
import { ItemOfDayComponent } from './components/item-of-day/item-of-day.component';
import { ItemComponent } from './components/item/item.component';
import { PictureModerVoteComponent } from './components/picture-moder-vote/picture-moder-vote.component';
import { ItemMetaFormComponent } from './components/item-meta-form/item-meta-form.component';
import { MessageService } from './services/message';
import { CommentService } from './services/comment';
import { PageService } from './services/page';
import { UserService } from './services/user';
import { DecimalPipe } from '@angular/common';
import { ForumService } from './services/forum';
import { PerspectiveService } from './services/perspective';
import { PictureModerVoteService } from './services/picture-moder-vote';
import { PictureModerVoteTemplateService } from './services/picture-moder-vote-template';
import { VehicleTypeService } from './services/vehicle-type';
import { SpecService } from './services/spec';
import { PictureItemService } from './services/picture-item';
import { ContactsService } from './services/contacts';
import { InvalidParamsPipe } from './invalid-params.pipe';
import { MessageDialogService } from './services/message-dialog';
import { AttrsService } from './services/attrs';
import { ModalMessageComponent } from './components/modal-message/modal-message.component';
import { ModerStatComponent } from './moder/stat/stat.component';
import { PageEnvService } from './services/page-env.service';
import { BreadcrumbsComponent } from './components/breadcrumbs/breadcrumbs.component';
import { MarkdownComponent } from './components/markdown/markdown.component';
import { CommentsVotesComponent } from './components/comments/votes/votes.component';
import { PictureModerVoteModalComponent } from './components/picture-moder-vote/modal/modal.component';
import { ContentLanguageService } from './services/content-language';
import { LanguageService } from './services/language';
import { VehicleTypesModalComponent } from './components/vehicle-types-modal/vehicle-types-modal.component';
import { ModerItemsItemCatalogueComponent } from './moder/items/item/catalogue/catalogue.component';
import { ModerItemsItemMetaComponent } from './moder/items/item/meta/meta.component';
import { ModerItemsItemLinksComponent } from './moder/items/item/links/links.component';
import { ModerItemsItemNameComponent } from './moder/items/item/name/name.component';
import { ModerItemsItemLogoComponent } from './moder/items/item/logo/logo.component';
import { ModerItemsItemPicturesComponent } from './moder/items/item/pictures/pictures.component';
import { ModerItemsItemVehiclesComponent } from './moder/items/item/vehicles/vehicles.component';
import { ModerItemsItemOrganizeComponent } from './moder/items/item/catalogue/organize/organize.component';
import { ModerItemsItemPicturesOrganizeComponent } from './moder/items/item/pictures/organize/organize.component';
import { CarsSpecificationsEditorEngineComponent } from './cars/specifications-editor/engine/engine.component';
import { CarsEngineSelectComponent } from './cars/specifications-editor/engine/select/select.component';
import { CarsSelectEngineTreeItemComponent } from './cars/specifications-editor/engine/select/tree-item/tree-item.component';
import { CarsSpecificationsEditorResultComponent } from './cars/specifications-editor/result/result.component';
import { CarsSpecificationsEditorSpecComponent } from './cars/specifications-editor/spec/spec.component';
import { VotingVotesComponent } from './voting/votes/votes.component';
import { MapPopupComponent } from './map/popup/popup.component';
import { MostsService } from './services/mosts';
import { TimezoneService } from './services/timezone';
import { Error403Component } from './error/403/403.component';
import { Error404Component } from './error/404/404.component';
import { UploadCropComponent } from './upload/crop/crop.component';

// AoT requires an exported function for factories
/* export function HttpLoaderFactory(http: HttpClient) {
  return new TranslateHttpLoader(http, '/ng2/i18n/', '.json');
}*/

const appRoutes: Routes = [
  { path: 'about', component: AboutComponent },
  {
    path: 'account',
    children: [
      {
        path: 'access',
        component: AccountAccessComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'accounts',
        component: AccountAccountsComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'contacts',
        component: AccountContactsComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'delete',
        children: [
          { path: 'deleted', component: AccountDeletedComponent },
          {
            path: '',
            component: AccountDeleteComponent,
            canActivate: [AuthGuard]
          }
        ]
      },
      {
        path: 'email',
        component: AccountEmailComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'emailcheck/:token',
        component: AccountEmailcheckComponent
      },
      {
        path: 'inbox-pictures',
        component: AccountInboxPicturesComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'messages',
        component: AccountMessagesComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'profile',
        component: AccountProfileComponent,
        canActivate: [AuthGuard]
      },
      {
        path: 'specs-conflicts',
        component: AccountSpecsConflictsComponent,
        canActivate: [AuthGuard]
      },
      { path: '', component: AccountComponent, canActivate: [AuthGuard] }
    ]
  },
  {
    path: 'articles',
    children: [
      { path: ':catname', component: ArticlesArticleComponent },
      { path: '', component: ArticlesComponent }
    ]
  },
  { path: 'brands', component: BrandsComponent },
  {
    path: 'cars',
    children: [
      {
        path: 'attrs-change-log',
        component: CarsAttrsChangeLogComponent
      },
      {
        path: 'dateless',
        component: CarsDatelessComponent
      },
      {
        path: 'select-engine',
        component: CarsEngineSelectComponent
      },
      {
        path: 'specifications-editor',
        component: CarsSpecificationsEditorComponent
      },
      {
        path: 'specs-admin',
        component: CarsSpecsAdminComponent
      }
    ]
  },
  { path: 'chart', component: ChartComponent },
  { path: 'cutaway', component: CutawayComponent },
  {
    path: 'donate',
    children: [
      { path: 'log', component: DonateLogComponent },
      { path: 'success', component: DonateSuccessComponent },
      {
        path: 'vod',
        children: [
          { path: 'select', component: DonateVodSelectComponent },
          { path: 'success', component: DonateVodSuccessComponent },
          { path: '', component: DonateVodComponent }
        ]
      },
      { path: '', component: DonateComponent }
    ]
  },
  {
    path: 'factories/:id',
    children: [
      { path: 'items', component: FactoryItemsComponent },
      { path: '', component: FactoryComponent }
    ]
  },
  {
    path: 'feedback',
    children: [
      { path: 'sent', component: FeedbackSentComponent },
      { path: '', component: FeedbackComponent }
    ]
  },
  {
    path: 'forums',
    children: [
      {
        path: 'move-message',
        component: ForumsMoveMessageComponent
      },
      {
        path: 'move-topic',
        component: ForumsMoveTopicComponent
      },
      {
        path: 'new-topic/:theme_id',
        component: ForumsNewTopicComponent
      },
      {
        path: 'subscriptions',
        component: ForumsSubscriptionsComponent
      },
      {
        path: 'topic/:topic_id',
        component: ForumsTopicComponent
      },
      {
        path: 'message/:message_id',
        component: MessageComponent
      },
      {
        path: ':theme_id',
        component: ForumsComponent
      },
      {
        path: '',
        component: ForumsComponent
      }
    ]
  },
  {
    path: 'inbox',
    children: [
      { path: '', component: InboxComponent },
      { path: ':brand', component: InboxComponent },
      { path: ':brand/:date', component: InboxComponent }
    ]
  },
  {
    path: 'info',
    children: [
      { path: 'spec', component: InfoSpecComponent },
      { path: 'text/:id', component: InfoTextComponent }
    ]
  },
  { path: 'log', component: LogComponent },
  { path: 'login', component: SignInComponent },
  { path: 'map', component: MapComponent },
  { path: 'mascots', component: MascotsComponent },
  {
    path: 'moder',
    children: [
      {
        path: 'comments',
        component: ModerCommentsComponent
      },
      {
        path: 'item-parent/:item_id/:parent_id',
        component: ModerItemParentComponent
      },
      {
        path: 'items',
        children: [
          {
            path: 'alpha',
            component: ModerItemsAlphaComponent
          },
          {
            path: 'too-big',
            component: ModerItemsTooBigComponent
          },
          {
            path: 'new',
            component: ModerItemsNewComponent
          },
          {
            path: 'item/:id',
            children: [
              {
                path: 'organize',
                component: ModerItemsItemOrganizeComponent
              },
              {
                path: 'organize-pictures',
                component: ModerItemsItemPicturesOrganizeComponent
              },
              {
                path: 'select-parent',
                component: ModerItemsItemSelectParentComponent
              },
              {
                path: '',
                component: ModerItemsItemComponent
              }
            ]
          },
          {
            path: '',
            component: ModerItemsComponent
          }
        ]
      },
      {
        path: 'pages',
        children: [
          {
            path: 'add',
            component: ModerPagesAddComponent
          },
          {
            path: 'edit',
            component: ModerPagesEditComponent
          },
          {
            path: '',
            component: ModerPagesComponent
          }
        ]
      },
      {
        path: 'perspectives',
        component: ModerPerspectivesComponent
      },
      {
        path: 'picture-vote-templates',
        component: ModerPictureVoteTemplatesComponent
      },
      {
        path: 'pictures',
        children: [
          {
            path: ':id',
            children: [
              {
                path: 'area',
                component: ModerPicturesItemAreaComponent
              },
              {
                path: 'crop',
                component: ModerPicturesItemCropComponent
              },
              {
                path: 'move',
                component: ModerPicturesItemMoveComponent
              },
              {
                path: '',
                component: ModerPicturesItemComponent
              }
            ]
          },
          {
            path: '',
            component: ModerPicturesComponent
          }
        ]
      },
      {
        path: 'rights',
        component: ModerRightsComponent
      },
      {
        path: 'stat',
        component: ModerStatComponent
      },
      {
        path: 'traffic',
        children: [
          {
            path: 'whitelist',
            component: ModerTrafficWhitelistComponent
          },
          {
            path: '',
            component: ModerTrafficComponent
          }
        ]
      },
      {
        path: 'users',
        component: ModerUsersComponent
      },
      {
        path: 'hotlinks',
        component: ModerHotlinksComponent
      },
      {
        path: 'attrs',
        children: [
          {
            path: 'attribute/:id',
            component: ModerAttrsAttributeComponent
          },
          {
            path: 'zone/:id',
            component: ModerAttrsZoneComponent
          },
          {
            path: '',
            component: ModerAttrsComponent
          }
        ]
      },
      {
        path: '',
        component: ModerIndexComponent
      }
    ]
  },
  {
    path: 'mosts',
    children: [
      {
        path: '',
        component: MostsComponent
      },
      {
        path: ':rating_catname',
        component: MostsComponent
      },
      {
        path: ':rating_catname/:type_catname',
        component: MostsComponent
      },
      {
        path: ':rating_catname/:type_catname/:years_catname',
        component: MostsComponent
      }
    ]
  },
  { path: 'museums/:id', component: MuseumComponent },
  {
    path: 'new',
    children: [
      {
        path: ':date',
        component: NewComponent
      },
      {
        path: ':date/:page',
        component: NewComponent
      },
      {
        path: ':date/item/:item_id',
        component: NewItemComponent
      },
      {
        path: ':date/item/:item_id/:page',
        component: NewItemComponent
      },
      {
        path: '',
        component: NewComponent
      }
    ]
  },
  {
    path: 'persons',
    children: [
      {
        path: 'authors',
        component: PersonsAuthorsComponent
      },
      {
        path: ':id',
        component: PersonsPersonComponent
      },
      {
        path: '',
        component: PersonsComponent
      }
    ]
  },
  { path: 'pulse', component: PulseComponent },
  {
    path: 'resore-password',
    children: [
      {
        path: 'new',
        children: [
          { path: 'ok', component: RestorePasswordNewOkComponent },
          { path: '', component: RestorePasswordNewComponent }
        ]
      },
      {
        path: 'sent',
        component: RestorePasswordSentComponent
      },
      {
        path: '',
        component: RestorePasswordComponent
      }
    ]
  },
  { path: 'rules', component: RulesComponent },
  {
    path: 'signup',
    children: [
      { path: 'ok', component: SignupOkComponent },
      { path: '', component: SignupComponent }
    ]
  },
  { path: 'telegram', component: TelegramComponent },
  { path: 'top-view', component: TopViewComponent },
  {
    path: 'upload',
    children: [
      { path: 'select', component: UploadSelectComponent },
      { path: '', component: UploadComponent }
    ]
  },
  {
    path: 'users',
    children: [
      {
        path: 'rating',
        children: [
          {
            path: ':rating',
            component: UsersRatingComponent
          },
          {
            path: '',
            component: UsersRatingComponent
          }
        ]
      },
      {
        path: ':identity',
        children: [
          { path: 'comments', component: UsersUserCommentsComponent },
          {
            path: 'pictures',
            children: [
              { path: ':brand', component: UsersUserPicturesBrandComponent },
              { path: '', component: UsersUserPicturesComponent }
            ]
          },
          { path: '', component: UsersUserComponent }
        ]
      }
    ]
  },
  { path: 'voting/:id', component: VotingComponent },
  { path: '', component: IndexComponent },
  { path: '**', component: PageNotFoundComponent }
];

@NgModule({
  declarations: [
    AboutComponent,
    AccountComponent,
    AccountAccessComponent,
    AccountAccountsComponent,
    AccountContactsComponent,
    AccountDeleteComponent,
    AccountDeletedComponent,
    AccountEmailComponent,
    AccountEmailcheckComponent,
    AccountInboxPicturesComponent,
    AccountMessagesComponent,
    AccountProfileComponent,
    AccountSidebarComponent,
    AccountSpecsConflictsComponent,
    AppComponent,
    ArticlesComponent,
    ArticlesArticleComponent,
    BrandsComponent,
    CarsAttrsChangeLogComponent,
    CarsDatelessComponent,
    CarsEngineSelectComponent,
    CarsSpecificationsEditorComponent,
    CarsSpecsAdminComponent,
    ChartComponent,
    CutawayComponent,
    DonateComponent,
    DonateLogComponent,
    DonateSuccessComponent,
    DonateVodComponent,
    DonateVodSelectComponent,
    DonateVodSuccessComponent,
    FactoryComponent,
    FactoryItemsComponent,
    FeedbackComponent,
    FeedbackSentComponent,
    ForumsComponent,
    ForumsMoveMessageComponent,
    ForumsMoveTopicComponent,
    ForumsNewTopicComponent,
    ForumsSubscriptionsComponent,
    ForumsTopicComponent,
    InboxComponent,
    IndexComponent,
    InfoSpecComponent,
    InfoTextComponent,
    LogComponent,
    MapComponent,
    MascotsComponent,
    MostsComponent,
    MuseumComponent,
    NewComponent,
    NewItemComponent,
    PageNotFoundComponent,
    PersonsComponent,
    PersonsAuthorsComponent,
    PersonsPersonComponent,
    PulseComponent,
    RestorePasswordComponent,
    RestorePasswordNewComponent,
    RestorePasswordNewOkComponent,
    RestorePasswordSentComponent,
    RulesComponent,
    ModerIndexComponent,
    MarkdownComponent,
    ModerMenuComponent,
    SignInComponent,
    SignupComponent,
    SignupOkComponent,
    TelegramComponent,
    TopViewComponent,
    UploadComponent,
    UploadSelectComponent,
    UsersRatingComponent,
    UsersUserComponent,
    UsersUserCommentsComponent,
    UsersUserPicturesComponent,
    UsersUserPicturesBrandComponent,
    VotingComponent,
    ModerPerspectivesComponent,
    ModerUsersComponent,
    PaginatorComponent,
    UserComponent,
    ModerAttrsComponent,
    ModerAttrsAttributeComponent,
    ModerAttrsZoneComponent,
    ModerCommentsComponent,
    ModerItemParentComponent,
    ModerItemsComponent,
    ModerItemsAlphaComponent,
    ModerItemsItemComponent,
    ModerItemsItemOrganizeComponent,
    ModerItemsItemPicturesOrganizeComponent,
    ModerItemsItemSelectParentComponent,
    ModerPagesComponent,
    ModerPagesAddComponent,
    ModerPagesEditComponent,
    ModerPictureVoteTemplatesComponent,
    ModerPicturesComponent,
    ModerPicturesItemComponent,
    ModerPicturesItemAreaComponent,
    ModerPicturesItemCropComponent,
    ModerPicturesItemMoveComponent,
    ModerRightsComponent,
    ModerTrafficComponent,
    ModerTrafficWhitelistComponent,
    NewListItemComponent,
    DonateVodSelectItemComponent,
    ModerPictureMoveItemComponent,
    MessageComponent,
    ModerRightsTreeComponent,
    ModerItemsTooBigComponent,
    ModerItemsNewComponent,
    ModerItemsItemTreeComponent,
    ModerItemsItemSelectParentTreeItemComponent,
    ModerItemsItemSelectParentTreeComponent,
    ModerHotlinksComponent,
    ModerAttrsAttributeListComponent,
    ModerAttrsZoneAttributeListComponent,
    ModerAttrsAttributeListOptionsTreeComponent,
    InfoSpecRowComponent,
    PastTimeIndicatorComponent,
    UploadSelectTreeItemComponent,
    ThumbnailComponent,
    CommentsComponent,
    CommentsListComponent,
    CommentsFormComponent,
    MarkdownEditComponent,
    ItemOfDayComponent,
    CarsSelectEngineTreeItemComponent,
    ItemComponent,
    PictureModerVoteComponent,
    ItemMetaFormComponent,
    InvalidParamsPipe,
    ModalMessageComponent,
    ModerStatComponent,
    BreadcrumbsComponent,
    CommentsVotesComponent,
    PictureModerVoteModalComponent,
    VehicleTypesModalComponent,
    ModerItemsItemCatalogueComponent,
    ModerItemsItemMetaComponent,
    ModerItemsItemLinksComponent,
    ModerItemsItemNameComponent,
    ModerItemsItemLogoComponent,
    ModerItemsItemPicturesComponent,
    ModerItemsItemVehiclesComponent,
    CarsSpecificationsEditorEngineComponent,
    CarsSpecificationsEditorResultComponent,
    CarsSpecificationsEditorSpecComponent,
    VotingVotesComponent,
    MapPopupComponent,
    Error403Component,
    Error404Component,
    UploadCropComponent
  ],
  entryComponents: [
    ModalMessageComponent,
    CommentsVotesComponent,
    PictureModerVoteModalComponent,
    VehicleTypesModalComponent,
    VotingVotesComponent,
    MapPopupComponent,
    UploadCropComponent
  ],
  imports: [
    BrowserModule,
    HttpClientModule,
    FormsModule,
    NgbModule.forRoot(),
    RouterModule.forRoot(
      appRoutes,
      { enableTracing: false } // <-- debugging purposes only
    ),
    TranslateModule.forRoot({
      /*loader: {
        provide: TranslateLoader,
        useFactory: HttpLoaderFactory,
        deps: [HttpClient]
      },*/
      compiler: {
        provide: TranslateCompiler,
        useClass: TranslateMessageFormatCompiler
      }
    }),
    MomentModule,
    NgPipesModule,
    FileUploadModule,
    ChartsModule,
    RecaptchaModule.forRoot(),
    RecaptchaFormsModule,
    BrowserAnimationsModule,
    LeafletModule.forRoot()
  ],
  providers: [
    APIService,
    APIACL,
    AuthService,
    AuthGuard,
    ACLService,
    PictureService,
    ItemService,
    InboxService,
    ReCaptchaService,
    DonateService,
    ItemParentService,
    ArticleService,
    ItemLinkService,
    ItemLanguageService,
    VotingService,
    MessageService,
    CommentService,
    PageService,
    UserService,
    DecimalPipe,
    BytesPipe,
    ForumService,
    PerspectiveService,
    PictureModerVoteService,
    PictureModerVoteTemplateService,
    VehicleTypeService,
    SpecService,
    PictureItemService,
    ContactsService,
    MessageDialogService,
    AttrsService,
    PageEnvService,
    ContentLanguageService,
    LanguageService,
    MostsService,
    TimezoneService
  ],
  bootstrap: [AppComponent]
})
export class AppModule {}

import type {Page} from './page';

import {PageId} from './page-id';

// Page-id hierarchy used by PageService to find a page's ancestors (for menu-item active-state
// highlighting via isDescendant$()), from the CMS's own page tree. Trimmed to the pages
// application code refers to (see PageId); the CMS's intermediate grouping nodes were spliced
// out, their children lifted to the nearest kept ancestor, so every named ancestor -> descendant
// relationship is preserved.
const pages: Page[] = [
  {
    id: PageId.HOME,
    childs: [
      {
        id: PageId.TWINS,
        childs: [
          {
            id: PageId.TWINS_GROUP,
            childs: [
              {id: PageId.TWINS_GROUP_PICTURES, childs: []},
              {id: PageId.TWINS_GROUP_SPECIFICATIONS, childs: []},
            ],
          },
          {id: PageId.TWINS_BRAND, childs: []},
        ],
      },
      {id: PageId.MOSTS, childs: []},
      {id: PageId.CATEGORIES, childs: []},
      {
        id: PageId.MAP,
        childs: [
          {id: PageId.MUSEUM, childs: []},
          {
            id: PageId.FACTORIES,
            childs: [{id: PageId.FACTORY_ITEMS, childs: []}],
          },
        ],
      },
      {
        id: PageId.FORUMS,
        childs: [
          {
            id: PageId.FORUM_THEME,
            childs: [
              {id: PageId.FORUM_NEW_TOPIC, childs: []},
              {
                id: PageId.FORUM_TOPIC,
                childs: [{id: PageId.FORUM_MOVE, childs: []}],
              },
            ],
          },
        ],
      },
      {id: PageId.CUTAWAY, childs: []},
      {
        id: PageId.USER,
        childs: [
          {
            id: PageId.USER_PICTURES,
            childs: [{id: PageId.USER_PICTURES_BRAND, childs: []}],
          },
          {id: PageId.USER_COMMENTS, childs: []},
        ],
      },
      {id: PageId.VOTING, childs: []},
      {
        id: PageId.ITEM_NEW,
        childs: [{id: PageId.ITEM_NEW_CHILD, childs: []}],
      },
      {id: PageId.BRANDS, childs: []},
      {
        id: PageId.MODER,
        childs: [
          {id: PageId.MODER_ITEMS_ALPHA, childs: []},
          {id: PageId.LOG, childs: []},
          {id: PageId.MODER_TRAFFIC, childs: []},
          {
            id: PageId.MODER_ATTRS,
            childs: [
              {id: PageId.MODER_ATTRIBUTE, childs: []},
              {id: PageId.MODER_ATTRS_ZONE, childs: []},
            ],
          },
          {id: PageId.MODER_STAT, childs: []},
          {
            id: PageId.MODER_PICTURES,
            childs: [
              {
                id: PageId.MODER_PICTURE,
                childs: [
                  {id: PageId.MODER_PICTURE_AREA, childs: []},
                  {id: PageId.MODER_PICTURE_MOVE, childs: []},
                ],
              },
            ],
          },
          {id: PageId.MODER_PERSPECTIVES, childs: []},
          {id: PageId.MODER_USERS, childs: []},
          {
            id: PageId.MODER_ITEMS,
            childs: [
              {
                id: PageId.MODER_ITEM,
                childs: [
                  {id: PageId.MODER_ITEM_SELECT_PARENT, childs: []},
                  {id: PageId.MODER_ITEM_CATALOGUE_ORGANIZE, childs: []},
                ],
              },
              {id: PageId.MODER_ITEM_NEW, childs: []},
            ],
          },
          {id: PageId.MODER_PICTURE_VOTE_TEMPLATES, childs: []},
          {id: PageId.MODER_COMMENTS, childs: []},
          {id: PageId.MODER_CONTENT_REPORTS, childs: []},
        ],
      },
      {id: PageId.RULES, childs: []},
      {
        id: PageId.UPLOAD,
        childs: [{id: PageId.UPLOAD_SELECT, childs: []}],
      },
      {
        id: PageId.FEEDBACK,
        childs: [{id: PageId.FEEDBACK_SENT, childs: []}],
      },
      {id: PageId.ACCOUNT_PROFILE, childs: []},
      {id: PageId.ACCOUNT, childs: []},
      {id: PageId.ACCOUNT_MESSAGES_SENT, childs: []},
      {id: PageId.ACCOUNT_MESSAGES, childs: []},
      {id: PageId.ACCOUNT_MESSAGES_SYSTEM, childs: []},
      {id: PageId.ACCOUNT_INBOX_PICTURES, childs: []},
      {id: PageId.ACCOUNT_EMAIL, childs: []},
      {id: PageId.ACCOUNT_ACCESS, childs: []},
      {id: PageId.ACCOUNT_DELETE, childs: []},
      {id: PageId.ACCOUNT_SPECS_CONFLICTS, childs: []},
      {id: PageId.ACCOUNT_PICTURES, childs: []},
      {id: PageId.FORUM_SUBSCRIPTIONS, childs: []},
      {id: PageId.ACCOUNT_CONTACTS, childs: []},
      {id: PageId.ABOUT, childs: []},
      {
        id: PageId.CATALOGUE_INDEX,
        childs: [
          {
            id: PageId.CATALOGUE_VEHICLES,
            childs: [
              {id: PageId.PICTURES, childs: []},
              {id: PageId.CATALOGUE_SPECIFICATIONS, childs: []},
            ],
          },
          {
            id: PageId.CATALOGUE_CARS,
            childs: [{id: PageId.CATALOGUE_CARS_VEHICLE_TYPE, childs: []}],
          },
          {id: PageId.CATALOGUE_CONCEPTS, childs: []},
          {
            id: PageId.CATALOGUE_LOGOTYPES,
            childs: [{id: PageId.CATALOGUE_LOGOTYPES_PICTURE, childs: []}],
          },
          {
            id: PageId.CATALOGUE_MIXED,
            childs: [{id: PageId.CATALOGUE_MIXED_PICTURE, childs: []}],
          },
          {
            id: PageId.CATALOGUE_OTHER,
            childs: [{id: PageId.CATALOGUE_OTHER_PICTURE, childs: []}],
          },
          {id: PageId.CATALOGUE_RECENT, childs: []},
          {id: PageId.CATALOGUE_ENGINES, childs: []},
        ],
      },
      {
        id: PageId.ARTICLES,
        childs: [{id: PageId.ARTICLE, childs: []}],
      },
      {
        id: PageId.SPECIFICATIONS_EDITOR,
        childs: [{id: PageId.SPECS_ADMIN, childs: []}],
      },
      {id: PageId.PULSE, childs: []},
      {id: PageId.USERS_RATING, childs: []},
      {id: PageId.INFO_SPEC, childs: []},
      {id: PageId.INBOX, childs: []},
      {id: PageId.DONATE, childs: []},
      {id: PageId.INFO_TEXT, childs: []},
      {id: PageId.GALLERIES, childs: []},
      {id: PageId.TELEGRAM, childs: []},
      {
        id: PageId.PERSONS,
        childs: [{id: PageId.PERSON, childs: []}],
      },
    ],
  },
];

export default pages;

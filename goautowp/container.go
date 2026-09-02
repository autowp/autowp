package goautowp

import (
	"context"
	"database/sql"
	"fmt"
	"log/slog"
	"net/http"
	"net/netip"
	"sync"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/achievements"
	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/contentreport"
	"github.com/autowp/goautowp/email"
	"github.com/autowp/goautowp/feedback"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/index"
	"github.com/autowp/goautowp/itemofday"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/log"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/mosts"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/telegram"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/traffic"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/votings"
	"github.com/doug-martin/goqu/v9"
	"github.com/gin-contrib/cors"
	"github.com/gin-gonic/gin"
	grpclogging "github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/recovery"
	"github.com/improbable-eng/grpc-web/go/grpcweb"
	"github.com/redis/go-redis/v9"
	"google.golang.org/grpc"
	"google.golang.org/grpc/reflection"
)

const readHeaderTimeout = time.Second * 30

// Container Container.
type Container struct {
	mu sync.Mutex

	achievementsRepository       *achievements.Repository
	achievementsGrpcServer       *AchievementsGRPCServer
	articlesGRPCServer           *ArticlesGRPCServer
	attrsRepository              *attrs.Repository
	autowpDB                     *sql.DB
	banRepository                *ban.Repository
	banChecker                   *BanChecker
	catalogue                    *Catalogue
	commentsRepository           *comments.Repository
	mostsRepository              *mosts.Repository
	config                       config.Config
	commentsGrpcServer           *CommentsGRPCServer
	mostsGrpcServer              *MostsGRPCServer
	contactsGrpcServer           *ContactsGRPCServer
	contactsRepository           *ContactsRepository
	duplicateFinder              *DuplicateFinder
	donationsGrpcServer          *DonationsGRPCServer
	contentReports               *contentreport.Repository
	emailSender                  email.Sender
	events                       *Events
	feedback                     *feedback.Repository
	forums                       *Forums
	goquDB                       *goqu.Database
	grpcServer                   *GRPCServer
	hostsManager                 *hosts.Manager
	imageStorage                 *storage.Storage
	i18n                         *i18nbundle.I18n
	itemOfDayRepository          *itemofday.Repository
	itemsGrpcServer              *ItemsGRPCServer
	itemOfDayCached              *ItemOfDayCached
	itemParentLanguageRepository *items.ItemParentLanguageRepository
	ratingGrpcServer             *RatingGRPCServer
	votingsGrpcServer            *VotingsGRPCServer
	itemsRepository              *items.Repository
	keyCloak                     *gocloak.GoCloak
	messagingGrpcServer          *MessagingGRPCServer
	messagingRepository          *messaging.Repository
	messagingHub                 *messaging.Hub
	messagingWS                  *MessagingWS
	publicHTTPServer             *http.Server
	publicRouter                 http.HandlerFunc
	grpcServerWithServices       *grpc.Server
	telegramService              *telegram.Service
	textGrpcServer               *TextGRPCServer
	traffic                      *traffic.Repository
	trafficGrpcServer            *TrafficGRPCServer
	votingsRepository            *votings.Repository
	usersRepository              *users.Repository
	usersGrpcServer              *UsersGRPCServer
	redis                        *redis.Client
	auth                         *Auth
	mapGrpcServer                *MapGRPCServer
	picturesRepository           *pictures.Repository
	picturesGrpcServer           *PicturesGRPCServer
	picturesHub                  *pictures.Hub
	picturesWS                   *PicturesWS
	statisticsGrpcServer         *StatisticsGRPCServer
	forumsGrpcServer             *ForumsGRPCServer
	attrsGRPCServer              *AttrsGRPCServer
	textStorageRepository        *textstorage.Repository
	yoomoneyHandler              *YoomoneyHandler
	logRepository                *log.Repository
	LogGrpcServer                *LogGRPCServer
}

// NewContainer constructor.
func NewContainer(cfg config.Config) *Container {
	return &Container{
		config: cfg,
	}
}

func (s *Container) Close() error {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.closeLocked()
}

func (s *Container) GoquDB(ctx context.Context) (*goqu.Database, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.goquDBLocked(ctx)
}

func (s *Container) BanRepository(ctx context.Context) (*ban.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.banRepositoryLocked(ctx)
}

// BanChecker returns the shared ban lookup used by the gRPC interceptors and the REST middleware -
// one instance, so they share its cache.
func (s *Container) BanChecker(ctx context.Context) (*BanChecker, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.banCheckerLocked(ctx)
}

func (s *Container) Catalogue(ctx context.Context) (*Catalogue, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.catalogueLocked(ctx)
}

func (s *Container) CommentsRepository(ctx context.Context) (*comments.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.commentsRepositoryLocked(ctx)
}

func (s *Container) MostsRepository(ctx context.Context) (*mosts.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.mostsRepositoryLocked(ctx)
}

func (s *Container) Config() config.Config {
	return s.config
}

func (s *Container) AttrsRepository(ctx context.Context) (*attrs.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.attrsRepositoryLocked(ctx)
}

func (s *Container) ContactsRepository(ctx context.Context) (*ContactsRepository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.contactsRepositoryLocked(ctx)
}

func (s *Container) DuplicateFinder(ctx context.Context) (*DuplicateFinder, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.duplicateFinderLocked(ctx)
}

func (s *Container) Feedback() (*feedback.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.feedbackLocked()
}

func (s *Container) IPExtractor(ctx context.Context) (*IPExtractor, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.ipExtractorLocked(ctx)
}

func (s *Container) HostsManager() *hosts.Manager {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.hostsManagerLocked()
}

func (s *Container) LogRepository(ctx context.Context) (*log.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.logRepositoryLocked(ctx)
}

func (s *Container) PicturesRepository(ctx context.Context) (*pictures.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.picturesRepositoryLocked(ctx)
}

// PicturesHub is the in-process registry of live /ws/pictures connections for this pod.
func (s *Container) PicturesHub() *pictures.Hub {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.picturesHubLocked()
}

func (s *Container) PicturesWS() *PicturesWS {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.picturesWSLocked()
}

func (s *Container) PublicHTTPServer(ctx context.Context) (*http.Server, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.publicHTTPServerLocked(ctx)
}

func (s *Container) ItemsREST(ctx context.Context) (*ItemsREST, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemsRESTLocked(ctx)
}

func (s *Container) UsersREST(ctx context.Context) (*UsersREST, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.usersRESTLocked(ctx)
}

func (s *Container) PicturesREST(ctx context.Context) (*PicturesREST, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.picturesRESTLocked(ctx)
}

func (s *Container) PublicRouter(ctx context.Context) (http.HandlerFunc, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.publicRouterLocked(ctx)
}

func (s *Container) GRPCServerWithServices(ctx context.Context) (*grpc.Server, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.grpcServerWithServicesLocked(ctx)
}

func (s *Container) TelegramService(ctx context.Context) (*telegram.Service, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.telegramServiceLocked(ctx)
}

func (s *Container) Traffic(ctx context.Context) (*traffic.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.trafficLocked(ctx)
}

func (s *Container) UserExtractor(ctx context.Context) (*UserExtractor, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.userExtractorLocked(ctx)
}

func (s *Container) VotingsRepository(ctx context.Context) (*votings.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.votingsRepositoryLocked(ctx)
}

func (s *Container) UsersRepository(ctx context.Context) (*users.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.usersRepositoryLocked(ctx)
}

func (s *Container) I18n() (*i18nbundle.I18n, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.i18nLocked()
}

func (s *Container) ItemsRepository(ctx context.Context) (*items.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemsRepositoryLocked(ctx)
}

func (s *Container) ItemParentLanguageRepository(ctx context.Context) (*items.ItemParentLanguageRepository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemParentLanguageRepositoryLocked(ctx)
}

func (s *Container) ItemExtractor() *ItemExtractor {
	return NewItemExtractor(s)
}

func (s *Container) PictureItemExtractor() *PictureItemExtractor {
	return NewPictureItemExtractor(s)
}

func (s *Container) PictureExtractor() *PictureExtractor {
	return NewPictureExtractor(s)
}

func (s *Container) ItemParentCacheExtractor() *ItemParentCacheExtractor {
	return NewItemParentCacheExtractor(s)
}

func (s *Container) DfDistanceExtractor() *DfDistanceExtractor {
	return NewDfDistanceExtractor(s)
}

func (s *Container) ItemParentExtractor() *ItemParentExtractor {
	return NewItemParentExtractor(s)
}

func (s *Container) NewLinkExtractor() *LinkExtractor {
	return NewLinkExtractor(s)
}

func (s *Container) Auth(ctx context.Context) (*Auth, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.authLocked(ctx)
}

func (s *Container) GRPCServer(ctx context.Context) (*GRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.grpcServerLocked(ctx)
}

func (s *Container) StatisticsGRPCServer(ctx context.Context) (*StatisticsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.statisticsGRPCServerLocked(ctx)
}

func (s *Container) TextGRPCServer(ctx context.Context) (*TextGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.textGRPCServerLocked(ctx)
}

func (s *Container) TrafficGRPCServer(ctx context.Context) (*TrafficGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.trafficGRPCServerLocked(ctx)
}

func (s *Container) UsersGRPCServer(ctx context.Context) (*UsersGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.usersGRPCServerLocked(ctx)
}

func (s *Container) VotingsGRPCServer(ctx context.Context) (*VotingsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.votingsGRPCServerLocked(ctx)
}

func (s *Container) RatingGRPCServer(ctx context.Context) (*RatingGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.ratingGRPCServerLocked(ctx)
}

func (s *Container) ItemOfDayCached(ctx context.Context) (*ItemOfDayCached, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemOfDayCachedLocked(ctx)
}

func (s *Container) ItemsGRPCServer(ctx context.Context) (*ItemsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemsGRPCServerLocked(ctx)
}

func (s *Container) MostsGRPCServer(ctx context.Context) (*MostsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.mostsGRPCServerLocked(ctx)
}

func (s *Container) CommentsGRPCServer(ctx context.Context) (*CommentsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.commentsGRPCServerLocked(ctx)
}

func (s *Container) ArticlesGRPCServer(ctx context.Context) (*ArticlesGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.articlesGRPCServerLocked(ctx)
}

func (s *Container) AttrsGRPCServer(ctx context.Context) (*AttrsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.attrsGRPCServerLocked(ctx)
}

func (s *Container) ContactsGRPCServer(ctx context.Context) (*ContactsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.contactsGRPCServerLocked(ctx)
}

func (s *Container) LogGRPCServer(ctx context.Context) (*LogGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.logGRPCServerLocked(ctx)
}

func (s *Container) PicturesGRPCServer(ctx context.Context) (*PicturesGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.picturesGRPCServerLocked(ctx)
}

func (s *Container) MapGRPCServer(ctx context.Context) (*MapGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.mapGRPCServerLocked(ctx)
}

func (s *Container) DonationsGRPCServer(ctx context.Context) (*DonationsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.donationsGRPCServerLocked(ctx)
}

func (s *Container) ForumsGRPCServer(ctx context.Context) (*ForumsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.forumsGRPCServerLocked(ctx)
}

func (s *Container) MessagingGRPCServer(ctx context.Context) (*MessagingGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.messagingGRPCServerLocked(ctx)
}

func (s *Container) Forums(ctx context.Context) (*Forums, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.forumsLocked(ctx)
}

func (s *Container) ItemOfDayRepository(ctx context.Context) (*itemofday.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.itemOfDayRepositoryLocked(ctx)
}

func (s *Container) MessagingRepository(ctx context.Context) (*messaging.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.messagingRepositoryLocked(ctx)
}

func (s *Container) AchievementsRepository(ctx context.Context) (*achievements.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.achievementsRepositoryLocked(ctx)
}

func (s *Container) AchievementsGRPCServer(ctx context.Context) (*AchievementsGRPCServer, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.achievementsGRPCServerLocked(ctx)
}

// MessagingHub is the in-process registry of live /ws/messages connections for this pod.
func (s *Container) MessagingHub() *messaging.Hub {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.messagingHubLocked()
}

func (s *Container) MessagingWS(ctx context.Context) (*MessagingWS, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.messagingWSLocked(ctx)
}

func (s *Container) Keycloak() *gocloak.GoCloak {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.keycloakLocked()
}

func (s *Container) EmailSender() email.Sender { //nolint: ireturn
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.emailSenderLocked()
}

func (s *Container) SetEmailSender(emailSender email.Sender) {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.setEmailSenderLocked(emailSender)
}

func (s *Container) Events(ctx context.Context) (*Events, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.eventsLocked(ctx)
}

func (s *Container) ImageStorage(ctx context.Context) (*storage.Storage, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.imageStorageLocked(ctx)
}

func (s *Container) Redis() (*redis.Client, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.redisLocked()
}

func (s *Container) Index(ctx context.Context) (*index.Cache, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.indexLocked(ctx)
}

func (s *Container) TextStorageRepository(ctx context.Context) (*textstorage.Repository, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.textStorageRepositoryLocked(ctx)
}

func (s *Container) YoomoneyHandler(ctx context.Context) (*YoomoneyHandler, error) {
	s.mu.Lock()
	defer s.mu.Unlock()

	return s.yoomoneyHandlerLocked(ctx)
}

// --- lock-holding factory implementations (call these from other *Locked methods) ---

func (s *Container) closeLocked() error {
	s.banRepository = nil
	s.banChecker = nil
	s.catalogue = nil
	s.commentsRepository = nil
	s.contactsRepository = nil
	s.duplicateFinder = nil
	s.traffic = nil
	s.usersRepository = nil
	s.feedback = nil

	if s.autowpDB != nil {
		err := s.autowpDB.Close()
		if err != nil {
			logging.Error(err.Error())
		}

		s.autowpDB = nil
	}

	/*if s.goquDB != nil {
		s.goquDB.Close()
		s.goquDB = nil
	}*/

	return nil
}

func (s *Container) goquDBLocked(ctx context.Context) (*goqu.Database, error) {
	if s.goquDB != nil {
		return s.goquDB, nil
	}

	start := time.Now()

	const (
		connectionTimeout = 60 * time.Second
		reconnectDelay    = 100 * time.Millisecond
	)

	logging.Info("Waiting for postgres (goqu)")

	var (
		db  *sql.DB
		err error
	)

	for {
		db, err = sql.Open("postgres", s.config.PostgresDSN)
		if err != nil {
			return nil, err
		}

		err = db.PingContext(ctx)
		if err == nil {
			logging.Info("Started.")

			break
		}

		if time.Since(start) > connectionTimeout {
			return nil, err
		}

		logging.Info(".")
		time.Sleep(reconnectDelay)
	}

	// Left at database/sql's own default (MaxOpenConns unbounded, MaxIdleConns 2), a burst of
	// concurrent requests in this single process can open enough connections on its own to hit
	// Postgres' server-side max_connections, starving every other client - these bounds cap this
	// process' share instead.
	db.SetMaxOpenConns(s.config.PostgresPool.MaxOpenConns)
	db.SetMaxIdleConns(s.config.PostgresPool.MaxIdleConns)
	db.SetConnMaxLifetime(s.config.PostgresPool.ConnMaxLifetime)
	db.SetConnMaxIdleTime(s.config.PostgresPool.ConnMaxIdleTime)

	s.goquDB = goqu.New("postgres", db)

	return s.goquDB, nil
}

func (s *Container) banRepositoryLocked(ctx context.Context) (*ban.Repository, error) {
	if s.banRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.banRepository, err = ban.NewRepository(db)
		if err != nil {
			return nil, err
		}
	}

	return s.banRepository, nil
}

func (s *Container) banCheckerLocked(ctx context.Context) (*BanChecker, error) {
	if s.banChecker == nil {
		banRepository, err := s.banRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.banChecker = NewBanChecker(banRepository)
	}

	return s.banChecker, nil
}

func (s *Container) catalogueLocked(ctx context.Context) (*Catalogue, error) {
	if s.catalogue == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		pgDB, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.catalogue, err = NewCatalogue(db, pgDB)
		if err != nil {
			return nil, err
		}
	}

	return s.catalogue, nil
}

func (s *Container) commentsRepositoryLocked(ctx context.Context) (*comments.Repository, error) {
	if s.commentsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.commentsRepository = comments.NewRepository(
			db,
			usersRepository,
			messagingRepository,
			s.hostsManagerLocked(),
			func(ctx context.Context, authorID int64) error {
				achievementsRepository, err := s.achievementsRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return achievementsRepository.GrantCommentPosted(ctx, authorID)
			},
		)
	}

	return s.commentsRepository, nil
}

func (s *Container) mostsRepositoryLocked(ctx context.Context) (*mosts.Repository, error) {
	if s.mostsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.attrsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.mostsRepository = mosts.NewRepository(db, itemsRepository, attrsRepository)
	}

	return s.mostsRepository, nil
}

func (s *Container) attrsRepositoryLocked(ctx context.Context) (*attrs.Repository, error) {
	if s.attrsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.i18nLocked()
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		is, err := s.imageStorageLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.attrsRepository = attrs.NewRepository(
			db, i18n, itemsRepository, picturesRepository, is,
			func(ctx context.Context, userID int64) error {
				achievementsRepository, err := s.achievementsRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return achievementsRepository.GrantSpecValueSet(ctx, userID)
			},
			func(ctx context.Context, userID int64) error {
				usersRepository, err := s.usersRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return usersRepository.InvalidateSpecsVolume(ctx, userID)
			},
		)
	}

	return s.attrsRepository, nil
}

func (s *Container) contactsRepositoryLocked(ctx context.Context) (*ContactsRepository, error) {
	if s.contactsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.contactsRepository = NewContactsRepository(db)
	}

	return s.contactsRepository, nil
}

func (s *Container) duplicateFinderLocked(ctx context.Context) (*DuplicateFinder, error) {
	if s.duplicateFinder == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.duplicateFinder, err = NewDuplicateFinder(db, s.Config().DuplicateFinder)
		if err != nil {
			return nil, err
		}
	}

	return s.duplicateFinder, nil
}

func (s *Container) feedbackLocked() (*feedback.Repository, error) {
	if s.feedback == nil {
		s.feedback = feedback.NewRepository(s.Config().Feedback, s.emailSenderLocked())
	}

	return s.feedback, nil
}

func (s *Container) contentReportsRepositoryLocked(ctx context.Context) (*contentreport.Repository, error) {
	if s.contentReports == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.contentReports = contentreport.NewRepository(db)
	}

	return s.contentReports, nil
}

func (s *Container) ipExtractorLocked(ctx context.Context) (*IPExtractor, error) {
	banRepository, err := s.banRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	userRepository, err := s.usersRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	userExtractor, err := s.userExtractorLocked(ctx)
	if err != nil {
		return nil, err
	}

	return NewIPExtractor(banRepository, userRepository, userExtractor), nil
}

func (s *Container) hostsManagerLocked() *hosts.Manager {
	if s.hostsManager == nil {
		s.hostsManager = hosts.NewManager(s.Config().Languages)
	}

	return s.hostsManager
}

func (s *Container) logRepositoryLocked(ctx context.Context) (*log.Repository, error) {
	if s.logRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.logRepository = log.NewRepository(db)
	}

	return s.logRepository, nil
}

func (s *Container) picturesRepositoryLocked(ctx context.Context) (*pictures.Repository, error) {
	if s.picturesRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		is, err := s.imageStorageLocked(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.textStorageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		redisClient, err := s.redisLocked()
		if err != nil {
			return nil, err
		}

		s.picturesRepository = pictures.NewRepository(
			db, is, textStorageRepository, itemsRepository, cfg.DuplicateFinder,
			func(id int64) error {
				commentsRepository, err := s.commentsRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return commentsRepository.DeleteTopic(ctx, schema.CommentMessageTypeIDPictures, id)
			},
			func(ctx context.Context) error {
				// Best-effort: a missed live-reload ping isn't worth failing the accept.
				if err := pictures.PublishAccepted(ctx, redisClient); err != nil {
					logging.Error(err.Error())
				}

				return nil
			},
			func(ctx context.Context, ownerID sql.NullInt64, moderatorID int64) error {
				achievementsRepository, err := s.achievementsRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return achievementsRepository.GrantPictureAccepted(ctx, ownerID, moderatorID)
			},
			func(ctx context.Context, moderatorID int64) error {
				achievementsRepository, err := s.achievementsRepositoryLocked(ctx)
				if err != nil {
					return err
				}

				return achievementsRepository.GrantPictureQueuedForRemoval(ctx, moderatorID)
			},
		)
	}

	return s.picturesRepository, nil
}

func (s *Container) picturesHubLocked() *pictures.Hub {
	if s.picturesHub == nil {
		s.picturesHub = pictures.NewHub()
	}

	return s.picturesHub
}

func (s *Container) picturesWSLocked() *PicturesWS {
	if s.picturesWS == nil {
		s.picturesWS = NewPicturesWS(s.picturesHubLocked(), s.config.PublicRest.Cors.Origin)
	}

	return s.picturesWS
}

func (s *Container) publicHTTPServerLocked(ctx context.Context) (*http.Server, error) {
	if s.publicHTTPServer == nil {
		cfg := s.Config()

		handler, err := s.publicRouterLocked(ctx)
		if err != nil {
			return nil, fmt.Errorf("PublicRouter(): %w", err)
		}

		s.publicHTTPServer = &http.Server{
			Addr:              cfg.PublicRest.Listen,
			Handler:           handler,
			ReadHeaderTimeout: readHeaderTimeout,
		}
	}

	return s.publicHTTPServer, nil
}

func (s *Container) itemsRESTLocked(ctx context.Context) (*ItemsREST, error) {
	itemsRepo, err := s.itemsRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	auth, err := s.authLocked(ctx)
	if err != nil {
		return nil, err
	}

	events, err := s.eventsLocked(ctx)
	if err != nil {
		return nil, err
	}

	return NewItemsREST(auth, itemsRepo, events), nil
}

func (s *Container) usersRESTLocked(ctx context.Context) (*UsersREST, error) {
	usersRepo, err := s.usersRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	auth, err := s.authLocked(ctx)
	if err != nil {
		return nil, err
	}

	return NewUsersREST(auth, usersRepo), nil
}

func (s *Container) picturesRESTLocked(ctx context.Context) (*PicturesREST, error) {
	auth, err := s.authLocked(ctx)
	if err != nil {
		return nil, err
	}

	picturesRepo, err := s.picturesRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	imageStorage, err := s.imageStorageLocked(ctx)
	if err != nil {
		return nil, err
	}

	itemOfDayRepo, err := s.itemOfDayRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	itemsRepo, err := s.itemsRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.i18nLocked()
	if err != nil {
		return nil, err
	}

	usersRepo, err := s.usersRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	commentsRepo, err := s.commentsRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	df, err := s.duplicateFinderLocked(ctx)
	if err != nil {
		return nil, err
	}

	ts, err := s.telegramServiceLocked(ctx)
	if err != nil {
		return nil, err
	}

	pictureNameFormatter := pictures.NewPictureNameFormatter(
		items.NewItemNameFormatter(i18nBundle),
		i18nBundle,
	)

	itemOfDayCached, err := s.itemOfDayCachedLocked(ctx)
	if err != nil {
		return nil, err
	}

	return NewPicturesREST(
		auth,
		picturesRepo,
		pictureNameFormatter,
		s.hostsManagerLocked(),
		imageStorage,
		itemOfDayRepo,
		itemsRepo,
		usersRepo,
		commentsRepo,
		df,
		ts,
		itemOfDayCached,
	), nil
}

func (s *Container) publicRouterLocked(ctx context.Context) (http.HandlerFunc, error) {
	if s.publicRouter != nil {
		return s.publicRouter, nil
	}

	grpcServer, err := s.grpcServerWithServicesLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("GRPCServerWithServices(): %w", err)
	}

	originFunc := func(origin string) bool {
		return util.Contains(s.config.PublicRest.Cors.Origin, origin)
	}
	wrappedGrpc := grpcweb.WrapServer(grpcServer, grpcweb.WithOriginFunc(originFunc))

	yoomoney, err := s.yoomoneyHandlerLocked(ctx)
	if err != nil {
		return nil, err
	}

	tg, err := s.telegramServiceLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("TelegramService(): %w", err)
	}

	ginEngine := gin.New()
	ginEngine.Use(gin.Recovery())

	err = ginEngine.SetTrustedProxies([]string{s.Config().TrustedNetwork})
	if err != nil {
		return nil, fmt.Errorf("SetTrustedProxies(): %w", err)
	}

	if len(s.config.PublicRest.Cors.Origin) > 0 {
		corsConfig := cors.DefaultConfig()
		corsConfig.AllowOrigins = s.config.PublicRest.Cors.Origin
		corsConfig.AllowCredentials = true
		ginEngine.Use(cors.New(corsConfig))
	}

	banChecker, err := s.banCheckerLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("BanChecker(): %w", err)
	}

	ginEngine.Use(BanGinMiddleware(banChecker)) //nolint: contextcheck

	yoomoney.SetupRouter(ctx, ginEngine)

	tg.SetupRouter(ginEngine) //nolint: contextcheck

	picturesREST, err := s.picturesRESTLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("PicturesREST(): %w", err)
	}

	picturesREST.SetupRouter(ginEngine) //nolint: contextcheck

	itemsREST, err := s.itemsRESTLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("ItemsREST(): %w", err)
	}

	itemsREST.SetupRouter(ginEngine) //nolint: contextcheck

	usersREST, err := s.usersRESTLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("UsersREST(): %w", err)
	}

	usersREST.SetupRouter(ginEngine) //nolint: contextcheck

	messagingWS, err := s.messagingWSLocked(ctx)
	if err != nil {
		return nil, fmt.Errorf("MessagingWS(): %w", err)
	}

	messagingWS.SetupRouter(ginEngine) //nolint: contextcheck

	s.picturesWSLocked().SetupRouter(ginEngine)

	s.publicRouter = func(resp http.ResponseWriter, req *http.Request) {
		if wrappedGrpc.IsAcceptableGrpcCorsRequest(req) || wrappedGrpc.IsGrpcWebRequest(req) {
			wrappedGrpc.ServeHTTP(resp, req)

			return
		}

		// Fall back to gRPC+h2c server
		ginEngine.ServeHTTP(resp, req)
	}

	s.grpcServerWithServices = grpcServer

	return s.publicRouter, nil
}

func (s *Container) grpcServerWithServicesLocked(ctx context.Context) (*grpc.Server, error) {
	if s.grpcServerWithServices != nil {
		return s.grpcServerWithServices, nil
	}

	srv, err := s.grpcServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	articlesSrv, err := s.articlesGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	attrsSrv, err := s.attrsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	commentsSrv, err := s.commentsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	contactsSrv, err := s.contactsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	donationsSrv, err := s.donationsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	forumsSrv, err := s.forumsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	itemsSrv, err := s.itemsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	logSrv, err := s.logGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	mapSrv, err := s.mapGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	mostsSrv, err := s.mostsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	textSrv, err := s.textGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	trafficSrv, err := s.trafficGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	picturesSrv, err := s.picturesGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	messagingSrv, err := s.messagingGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	usersSrv, err := s.usersGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	statSrv, err := s.statisticsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	ratingSrv, err := s.ratingGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	votingSrv, err := s.votingsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	achievementsSrv, err := s.achievementsGRPCServerLocked(ctx)
	if err != nil {
		return nil, err
	}

	trustedPeers := []netip.Prefix{
		netip.MustParsePrefix(s.Config().TrustedNetwork),
	}

	opts := []realip.Option{
		realip.WithTrustedPeers(trustedPeers),
		realip.WithHeaders([]string{realip.XForwardedFor}),
	}

	logger := slog.Default()
	loggerOpts := []grpclogging.Option{
		grpclogging.WithLogOnEvents(grpclogging.StartCall, grpclogging.FinishCall),
	}

	banChecker, err := s.banCheckerLocked(ctx)
	if err != nil {
		return nil, err
	}

	grpcServer := grpc.NewServer(
		grpc.ChainUnaryInterceptor(
			// Outermost, so what it times is the whole call - ban check and all - the same span the
			// caller waits on.
			SlowCallUnaryServerInterceptor(s.config.GRPC.SlowCallThreshold),
			grpclogging.UnaryServerInterceptor(InterceptorLogger(logger), loggerOpts...),
			realip.UnaryServerInterceptorOpts(opts...),
			BanUnaryServerInterceptor(banChecker),
			// Innermost, so it wraps the handler itself: a panic that escapes becomes a logged
			// codes.Internal for that one call instead of unwinding the serving goroutine.
			recovery.UnaryServerInterceptor(recoveryOpts()...),
		),
		grpc.ChainStreamInterceptor(
			grpclogging.StreamServerInterceptor(InterceptorLogger(logger), loggerOpts...),
			realip.StreamServerInterceptorOpts(opts...),
			BanStreamServerInterceptor(banChecker), //nolint: contextcheck
			recovery.StreamServerInterceptor(recoveryOpts()...),
		),
	)
	RegisterArticlesServer(grpcServer, articlesSrv)
	RegisterAttrsServer(grpcServer, attrsSrv)
	RegisterAutowpServer(grpcServer, srv)
	RegisterCommentsServer(grpcServer, commentsSrv)
	RegisterContactsServer(grpcServer, contactsSrv)
	RegisterDonationsServer(grpcServer, donationsSrv)
	RegisterForumsServer(grpcServer, forumsSrv)
	RegisterItemsServer(grpcServer, itemsSrv)
	RegisterLogServer(grpcServer, logSrv)
	RegisterMapServer(grpcServer, mapSrv)
	RegisterMostsServer(grpcServer, mostsSrv)
	RegisterMessagingServer(grpcServer, messagingSrv)
	RegisterPicturesServer(grpcServer, picturesSrv)
	RegisterStatisticsServer(grpcServer, statSrv)
	RegisterTextServer(grpcServer, textSrv)
	RegisterTrafficServer(grpcServer, trafficSrv)
	RegisterUsersServer(grpcServer, usersSrv)
	RegisterRatingServer(grpcServer, ratingSrv)
	RegisterVotingsServer(grpcServer, votingSrv)
	RegisterAchievementsServer(grpcServer, achievementsSrv)

	reflection.Register(grpcServer)

	s.grpcServerWithServices = grpcServer

	return s.grpcServerWithServices, nil
}

func (s *Container) telegramServiceLocked(ctx context.Context) (*telegram.Service, error) {
	if s.telegramService == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.telegramService = telegram.NewService(
			s.Config().Telegram,
			db,
			s.hostsManagerLocked(),
			userRepository,
			itemRepository,
			messagingRepository,
			picturesRepository,
		)
	}

	return s.telegramService, nil
}

func (s *Container) trafficLocked(ctx context.Context) (*traffic.Repository, error) {
	if s.traffic == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		banRepository, err := s.banRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		traf, err := traffic.NewRepository(db, banRepository)
		if err != nil {
			logging.Error(err.Error())

			return nil, err
		}

		s.traffic = traf
	}

	return s.traffic, nil
}

func (s *Container) userExtractorLocked(ctx context.Context) (*UserExtractor, error) {
	is, err := s.imageStorageLocked(ctx)
	if err != nil {
		return nil, err
	}

	picRepository, err := s.picturesRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	return NewUserExtractor(is, picRepository), nil
}

func (s *Container) votingsRepositoryLocked(ctx context.Context) (*votings.Repository, error) {
	if s.votingsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.votingsRepository = votings.NewRepository(db)
	}

	return s.votingsRepository, nil
}

func (s *Container) usersRepositoryLocked(ctx context.Context) (*users.Repository, error) {
	if s.usersRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		is, err := s.imageStorageLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.usersRepository = users.NewRepository(
			db,
			cfg.UsersSalt,
			cfg.Languages,
			s.keycloakLocked(),
			cfg.Keycloak,
			cfg.MessageInterval,
			is,
		)
	}

	return s.usersRepository, nil
}

func (s *Container) i18nLocked() (*i18nbundle.I18n, error) {
	if s.i18n == nil {
		i, err := i18nbundle.New()
		if err != nil {
			return nil, err
		}

		s.i18n = i
	}

	return s.i18n, nil
}

func (s *Container) itemsRepositoryLocked(ctx context.Context) (*items.Repository, error) {
	if s.itemsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		textStorageRepository, err := s.textStorageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		imageStorage, err := s.imageStorageLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemParentLanguageRepository, err := s.itemParentLanguageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.itemsRepository = items.NewRepository(
			db,
			cfg.MostsMinCarsCount,
			itemParentLanguageRepository,
			textStorageRepository,
			imageStorage,
		)
	}

	return s.itemsRepository, nil
}

func (s *Container) itemParentLanguageRepositoryLocked(
	ctx context.Context,
) (*items.ItemParentLanguageRepository, error) {
	if s.itemsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.itemParentLanguageRepository = items.NewItemParentLanguageRepository(
			db,
			s.Config().ContentLanguages,
		)
	}

	return s.itemParentLanguageRepository, nil
}

func (s *Container) authLocked(ctx context.Context) (*Auth, error) {
	if s.auth == nil {
		cfg := s.Config()

		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		rep, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.auth = NewAuth(db, s.keycloakLocked(), cfg.Keycloak, rep)
	}

	return s.auth, nil
}

func (s *Container) grpcServerLocked(ctx context.Context) (*GRPCServer, error) {
	if s.grpcServer == nil {
		cfg := s.Config()

		commentsRepository, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		fb, err := s.feedbackLocked()
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		ipExtractor, err := s.ipExtractorLocked(ctx)
		if err != nil {
			return nil, err
		}

		contentReports, err := s.contentReportsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.grpcServer = NewGRPCServer(
			auth,
			cfg.Recaptcha,
			commentsRepository,
			ipExtractor,
			fb,
			contentReports,
			cfg.Captcha,
		)
	}

	return s.grpcServer, nil
}

func (s *Container) statisticsGRPCServerLocked(ctx context.Context) (*StatisticsGRPCServer, error) {
	if s.statisticsGrpcServer == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.statisticsGrpcServer = NewStatisticsGRPCServer(db, s.Config().About)
	}

	return s.statisticsGrpcServer, nil
}

func (s *Container) textGRPCServerLocked(ctx context.Context) (*TextGRPCServer, error) {
	if s.textGrpcServer == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.textGrpcServer = NewTextGRPCServer(auth, db)
	}

	return s.textGrpcServer, nil
}

func (s *Container) trafficGRPCServerLocked(ctx context.Context) (*TrafficGRPCServer, error) {
	if s.trafficGrpcServer == nil {
		traf, err := s.trafficLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepo, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.userExtractorLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.trafficGrpcServer = NewTrafficGRPCServer(auth, usersRepo, userExtractor, traf)
	}

	return s.trafficGrpcServer, nil
}

func (s *Container) usersGRPCServerLocked(ctx context.Context) (*UsersGRPCServer, error) {
	if s.usersGrpcServer == nil {
		cfg := s.Config()

		contactsRepository, err := s.contactsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.eventsLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.userExtractorLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.usersGrpcServer = NewUsersGRPCServer(
			auth,
			contactsRepository,
			userRepository,
			events,
			cfg.Languages,
			cfg.Captcha,
			userExtractor,
		)
	}

	return s.usersGrpcServer, nil
}

func (s *Container) votingsGRPCServerLocked(ctx context.Context) (*VotingsGRPCServer, error) {
	if s.votingsGrpcServer == nil {
		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		votingsRepo, err := s.votingsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.votingsGrpcServer = NewVotingsGRPCServer(votingsRepo, auth)
	}

	return s.votingsGrpcServer, nil
}

func (s *Container) ratingGRPCServerLocked(ctx context.Context) (*RatingGRPCServer, error) {
	if s.ratingGrpcServer == nil {
		commentsRepository, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.attrsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.ratingGrpcServer = NewRatingGRPCServer(
			picturesRepository,
			userRepository,
			itemsRepository,
			commentsRepository,
			attrsRepository,
		)
	}

	return s.ratingGrpcServer, nil
}

func (s *Container) itemOfDayCachedLocked(ctx context.Context) (*ItemOfDayCached, error) {
	if s.itemOfDayCached == nil {
		itemOfDayRepo, err := s.itemOfDayRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepo, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepo, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		redisClient, err := s.redisLocked()
		if err != nil {
			return nil, err
		}

		s.itemOfDayCached = NewItemOfDayCached(
			itemOfDayRepo, itemsRepo, picturesRepo, s.Config().ContentLanguages, redisClient, s.ItemExtractor(),
		)
	}

	return s.itemOfDayCached, nil
}

func (s *Container) itemsGRPCServerLocked(ctx context.Context) (*ItemsGRPCServer, error) {
	if s.itemsGrpcServer == nil {
		repo, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.textStorageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		extractor := s.ItemExtractor()

		i18n, err := s.i18nLocked()
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.attrsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		idx, err := s.indexLocked(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.eventsLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		redisClient, err := s.redisLocked()
		if err != nil {
			return nil, err
		}

		catalogue, err := s.catalogueLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemOfDayCached, err := s.itemOfDayCachedLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemParentLanguageRepository, err := s.itemParentLanguageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.itemsGrpcServer = NewItemsGRPCServer(
			repo,
			itemParentLanguageRepository,
			db,
			auth,
			s.Config().ContentLanguages,
			textStorageRepository,
			extractor,
			i18n,
			attrsRepository,
			picturesRepository,
			idx,
			events,
			usersRepository,
			messagingRepository,
			s.hostsManagerLocked(),
			s.ItemParentExtractor(),
			s.NewLinkExtractor(),
			redisClient,
			catalogue,
			s.Config().FileStorage,
			itemOfDayCached,
		)
	}

	return s.itemsGrpcServer, nil
}

func (s *Container) mostsGRPCServerLocked(ctx context.Context) (*MostsGRPCServer, error) {
	if s.mostsGrpcServer == nil {
		mostsRepository, err := s.mostsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.mostsGrpcServer = NewMostsGRPCServer(
			auth,
			s.ItemExtractor(),
			mostsRepository,
		)
	}

	return s.mostsGrpcServer, nil
}

func (s *Container) commentsGRPCServerLocked(ctx context.Context) (*CommentsGRPCServer, error) {
	if s.commentsGrpcServer == nil {
		commentsRepository, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.userExtractorLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.commentsGrpcServer = NewCommentsGRPCServer(
			auth,
			commentsRepository,
			usersRepository,
			picturesRepository,
			userExtractor,
		)
	}

	return s.commentsGrpcServer, nil
}

func (s *Container) articlesGRPCServerLocked(ctx context.Context) (*ArticlesGRPCServer, error) {
	if s.articlesGRPCServer == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.articlesGRPCServer = NewArticlesGRPCServer(db)
	}

	return s.articlesGRPCServer, nil
}

func (s *Container) attrsGRPCServerLocked(ctx context.Context) (*AttrsGRPCServer, error) {
	if s.attrsGRPCServer == nil {
		repository, err := s.attrsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.attrsGRPCServer = NewAttrsGRPCServer(repository, auth)
	}

	return s.attrsGRPCServer, nil
}

func (s *Container) contactsGRPCServerLocked(ctx context.Context) (*ContactsGRPCServer, error) {
	if s.contactsGrpcServer == nil {
		contactsRepository, err := s.contactsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.userExtractorLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.contactsGrpcServer = NewContactsGRPCServer(
			auth,
			contactsRepository,
			userRepository,
			userExtractor,
		)
	}

	return s.contactsGrpcServer, nil
}

func (s *Container) logGRPCServerLocked(ctx context.Context) (*LogGRPCServer, error) {
	if s.LogGrpcServer == nil {
		repository, err := s.logRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.LogGrpcServer = NewLogGRPCServer(repository, auth)
	}

	return s.LogGrpcServer, nil
}

func (s *Container) picturesGRPCServerLocked(ctx context.Context) (*PicturesGRPCServer, error) {
	if s.picturesGrpcServer == nil {
		repository, err := s.picturesRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.eventsLocked(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		duplicateFinder, err := s.duplicateFinderLocked(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.textStorageRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		tg, err := s.telegramServiceLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemRepository, err := s.itemsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepository, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		catalogue, err := s.catalogueLocked(ctx)
		if err != nil {
			return nil, err
		}

		itemOfDayCached, err := s.itemOfDayCachedLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.picturesGrpcServer = NewPicturesGRPCServer(
			repository,
			auth,
			events,
			s.hostsManagerLocked(),
			messagingRepository,
			userRepository,
			duplicateFinder,
			textStorageRepository,
			tg,
			itemRepository,
			commentsRepository,
			s.PictureExtractor(),
			s.PictureItemExtractor(),
			s.ItemExtractor(),
			catalogue,
			itemOfDayCached,
		)
	}

	return s.picturesGrpcServer, nil
}

func (s *Container) mapGRPCServerLocked(ctx context.Context) (*MapGRPCServer, error) {
	if s.mapGrpcServer == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		imageStorage, err := s.imageStorageLocked(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.i18nLocked()
		if err != nil {
			return nil, err
		}

		s.mapGrpcServer = NewMapGRPCServer(db, imageStorage, i18n)
	}

	return s.mapGrpcServer, nil
}

func (s *Container) donationsGRPCServerLocked(ctx context.Context) (*DonationsGRPCServer, error) {
	if s.donationsGrpcServer == nil {
		repository, err := s.itemOfDayRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.donationsGrpcServer = NewDonationsGRPCServer(repository, s.Config().DonationsVodPrice, db)
	}

	return s.donationsGrpcServer, nil
}

func (s *Container) forumsGRPCServerLocked(ctx context.Context) (*ForumsGRPCServer, error) {
	if s.forumsGrpcServer == nil {
		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		forums, err := s.forumsLocked(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepo, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepo, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.forumsGrpcServer = NewForumsGRPCServer(auth, forums, commentsRepo, usersRepo)
	}

	return s.forumsGrpcServer, nil
}

func (s *Container) messagingGRPCServerLocked(ctx context.Context) (*MessagingGRPCServer, error) {
	if s.messagingGrpcServer == nil {
		repository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.messagingGrpcServer = NewMessagingGRPCServer(repository, auth)
	}

	return s.messagingGrpcServer, nil
}

func (s *Container) forumsLocked(ctx context.Context) (*Forums, error) {
	if s.forums == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepository, err := s.commentsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.forums = NewForums(db, commentsRepository)
	}

	return s.forums, nil
}

func (s *Container) itemOfDayRepositoryLocked(ctx context.Context) (*itemofday.Repository, error) {
	if s.itemOfDayRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.itemOfDayRepository = itemofday.NewRepository(db)
	}

	return s.itemOfDayRepository, nil
}

func (s *Container) messagingRepositoryLocked(ctx context.Context) (*messaging.Repository, error) {
	if s.messagingRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.i18nLocked()
		if err != nil {
			return nil, err
		}

		redisClient, err := s.redisLocked()
		if err != nil {
			return nil, err
		}

		s.messagingRepository = messaging.NewRepository(
			db,
			func(ctx context.Context, fromUserID int64, toUserID int64, text string) error {
				tg, err := s.telegramServiceLocked(ctx)
				if err != nil {
					return err
				}

				return tg.NotifyMessage(ctx, fromUserID, toUserID, text)
			},
			func(ctx context.Context, userIDs []int64) error {
				// Best-effort: a missed live-reload ping isn't worth failing the
				// underlying message operation over.
				if err := messaging.PublishEvent(ctx, redisClient, userIDs); err != nil {
					logging.Error(err.Error())
				}

				return nil
			},
			i18n,
		)
	}

	return s.messagingRepository, nil
}

func (s *Container) achievementsRepositoryLocked(ctx context.Context) (*achievements.Repository, error) {
	if s.achievementsRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.usersRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.messagingRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.achievementsRepository = achievements.NewRepository(
			db, usersRepository, messagingRepository, s.hostsManagerLocked(),
		)
	}

	return s.achievementsRepository, nil
}

func (s *Container) achievementsGRPCServerLocked(ctx context.Context) (*AchievementsGRPCServer, error) {
	if s.achievementsGrpcServer == nil {
		repository, err := s.achievementsRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.achievementsGrpcServer = NewAchievementsGRPCServer(repository)
	}

	return s.achievementsGrpcServer, nil
}

func (s *Container) messagingHubLocked() *messaging.Hub {
	if s.messagingHub == nil {
		s.messagingHub = messaging.NewHub()
	}

	return s.messagingHub
}

func (s *Container) messagingWSLocked(ctx context.Context) (*MessagingWS, error) {
	if s.messagingWS == nil {
		auth, err := s.authLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.messagingWS = NewMessagingWS(s.messagingHubLocked(), auth, s.config.PublicRest.Cors.Origin)
	}

	return s.messagingWS, nil
}

func (s *Container) keycloakLocked() *gocloak.GoCloak {
	if s.keyCloak == nil {
		client := gocloak.NewClient(s.Config().Keycloak.URL)

		s.keyCloak = client
	}

	return s.keyCloak
}

func (s *Container) emailSenderLocked() email.Sender { //nolint: ireturn
	if s.emailSender == nil {
		cfg := s.Config()

		if s.config.MockEmailSender {
			s.emailSender = &email.MockSender{}
		} else {
			s.emailSender = &email.SMTPSender{Config: cfg.SMTP}
		}
	}

	return s.emailSender
}

func (s *Container) setEmailSenderLocked(emailSender email.Sender) {
	s.emailSender = emailSender
}

func (s *Container) eventsLocked(ctx context.Context) (*Events, error) {
	if s.events == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.events = NewEvents(db)
	}

	return s.events, nil
}

func (s *Container) imageStorageLocked(ctx context.Context) (*storage.Storage, error) {
	if s.imageStorage == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		imageStorage, err := storage.NewStorage(db, s.Config().ImageStorage)
		if err != nil {
			return nil, err
		}

		s.imageStorage = imageStorage
	}

	return s.imageStorage, nil
}

func (s *Container) redisLocked() (*redis.Client, error) {
	if s.redis == nil {
		opts, err := redis.ParseURL(s.Config().Redis)
		if err != nil {
			return nil, err
		}

		s.redis = redis.NewClient(opts)
	}

	return s.redis, nil
}

func (s *Container) indexLocked(ctx context.Context) (*index.Cache, error) {
	redisClient, err := s.redisLocked()
	if err != nil {
		return nil, err
	}

	repository, err := s.itemsRepositoryLocked(ctx)
	if err != nil {
		return nil, err
	}

	return index.NewCache(redisClient, repository), nil
}

func (s *Container) textStorageRepositoryLocked(ctx context.Context) (*textstorage.Repository, error) {
	if s.textStorageRepository == nil {
		db, err := s.goquDBLocked(ctx)
		if err != nil {
			return nil, err
		}

		s.textStorageRepository = textstorage.New(db)
	}

	return s.textStorageRepository, nil
}

func (s *Container) yoomoneyHandlerLocked(ctx context.Context) (*YoomoneyHandler, error) {
	if s.yoomoneyHandler == nil {
		repository, err := s.itemOfDayRepositoryLocked(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config().YoomoneyConfig

		s.yoomoneyHandler, err = NewYoomoneyHandler(cfg.Price, cfg.Secret, repository)
		if err != nil {
			return nil, err
		}
	}

	return s.yoomoneyHandler, nil
}

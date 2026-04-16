package goautowp

import (
	"context"
	"database/sql"
	"fmt"
	"net/http"
	"net/netip"
	"sync"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/email"
	"github.com/autowp/goautowp/feedback"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/index"
	"github.com/autowp/goautowp/itemofday"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/log"
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
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"github.com/improbable-eng/grpc-web/go/grpcweb"
	"github.com/redis/go-redis/v9"
	"github.com/sirupsen/logrus"
	"google.golang.org/grpc"
	"google.golang.org/grpc/reflection"
)

const readHeaderTimeout = time.Second * 30

// Container Container.
type Container struct {
	articlesGRPCServer           *ArticlesGRPCServer
	attrsRepository              *attrs.Repository
	autowpDB                     *sql.DB
	autowpDBMutex                sync.Mutex
	banRepository                *ban.Repository
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
	emailSender                  email.Sender
	events                       *Events
	feedback                     *feedback.Repository
	forums                       *Forums
	goquDB                       *goqu.Database
	goquPostgresDB               *goqu.Database
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
	publicHTTPServer             *http.Server
	publicRouter                 http.HandlerFunc
	grpcServerWithServices       *grpc.Server
	telegramService              *telegram.Service
	textGrpcServer               *TextGRPCServer
	traffic                      *traffic.Traffic
	trafficGrpcServer            *TrafficGRPCServer
	votingsRepository            *votings.Repository
	usersRepository              *users.Repository
	usersGrpcServer              *UsersGRPCServer
	redis                        *redis.Client
	auth                         *Auth
	mapGrpcServer                *MapGRPCServer
	picturesRepository           *pictures.Repository
	picturesGrpcServer           *PicturesGRPCServer
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
	s.banRepository = nil
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
			logrus.Error(err.Error())
		}

		s.autowpDB = nil
	}

	/*if s.goquPostgresDB != nil {
		s.goquPostgresDB.Close()
		s.goquPostgresDB = nil
	}*/

	return nil
}

func (s *Container) AutowpDB(ctx context.Context) (*sql.DB, error) {
	s.autowpDBMutex.Lock()
	defer s.autowpDBMutex.Unlock()

	if s.autowpDB != nil {
		return s.autowpDB, nil
	}

	const (
		connectionTimeout = 60 * time.Second
		reconnectDelay    = 100 * time.Millisecond
	)

	logrus.Info("Waiting for mysql")

	var (
		db    *sql.DB
		err   error
		start = time.Now()
	)

	for {
		db, err = sql.Open("mysql", s.config.AutowpDSN)
		if err != nil {
			return nil, err
		}

		err = db.PingContext(ctx)
		if err == nil {
			logrus.Info("Started.")

			break
		}

		if time.Since(start) > connectionTimeout {
			return nil, err
		}

		logrus.Infof(". %s", err.Error())
		time.Sleep(reconnectDelay)
	}

	s.autowpDB = db

	return s.autowpDB, nil
}

func (s *Container) GoquDB(ctx context.Context) (*goqu.Database, error) {
	if s.goquDB == nil {
		db, err := s.AutowpDB(ctx)
		if err != nil {
			return nil, err
		}

		s.goquDB = goqu.New("mysql", db)
	}

	return s.goquDB, nil
}

func (s *Container) GoquPostgresDB(ctx context.Context) (*goqu.Database, error) {
	if s.goquPostgresDB != nil {
		return s.goquPostgresDB, nil
	}

	start := time.Now()

	const (
		connectionTimeout = 60 * time.Second
		reconnectDelay    = 100 * time.Millisecond
	)

	logrus.Info("Waiting for postgres (goqu)")

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
			logrus.Info("Started.")

			break
		}

		if time.Since(start) > connectionTimeout {
			return nil, err
		}

		logrus.Info(".")
		time.Sleep(reconnectDelay)
	}

	s.goquPostgresDB = goqu.New("postgres", db)

	return s.goquPostgresDB, nil
}

func (s *Container) BanRepository(ctx context.Context) (*ban.Repository, error) {
	if s.banRepository == nil {
		db, err := s.GoquPostgresDB(ctx)
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

func (s *Container) Catalogue(ctx context.Context) (*Catalogue, error) {
	if s.catalogue == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		pgDB, err := s.GoquPostgresDB(ctx)
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

func (s *Container) CommentsRepository(ctx context.Context) (*comments.Repository, error) {
	if s.commentsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.MessagingRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.commentsRepository = comments.NewRepository(
			db,
			usersRepository,
			messagingRepository,
			s.HostsManager(),
		)
	}

	return s.commentsRepository, nil
}

func (s *Container) MostsRepository(ctx context.Context) (*mosts.Repository, error) {
	if s.mostsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.AttrsRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.mostsRepository = mosts.NewRepository(db, itemsRepository, attrsRepository)
	}

	return s.mostsRepository, nil
}

func (s *Container) Config() config.Config {
	return s.config
}

func (s *Container) AttrsRepository(ctx context.Context) (*attrs.Repository, error) {
	if s.attrsRepository == nil {
		pgDB, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.I18n()
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		is, err := s.ImageStorage(ctx)
		if err != nil {
			return nil, err
		}

		s.attrsRepository = attrs.NewRepository(db, pgDB, i18n, itemsRepository, picturesRepository, is)
	}

	return s.attrsRepository, nil
}

func (s *Container) ContactsRepository(ctx context.Context) (*ContactsRepository, error) {
	if s.contactsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.contactsRepository = NewContactsRepository(db)
	}

	return s.contactsRepository, nil
}

func (s *Container) DuplicateFinder(ctx context.Context) (*DuplicateFinder, error) {
	if s.duplicateFinder == nil {
		db, err := s.GoquDB(ctx)
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

func (s *Container) Feedback() (*feedback.Repository, error) {
	if s.feedback == nil {
		s.feedback = feedback.NewRepository(s.Config().Feedback, s.EmailSender())
	}

	return s.feedback, nil
}

func (s *Container) IPExtractor(ctx context.Context) (*IPExtractor, error) {
	banRepository, err := s.BanRepository(ctx)
	if err != nil {
		return nil, err
	}

	userRepository, err := s.UsersRepository(ctx)
	if err != nil {
		return nil, err
	}

	userExtractor, err := s.UserExtractor(ctx)
	if err != nil {
		return nil, err
	}

	return NewIPExtractor(banRepository, userRepository, userExtractor), nil
}

func (s *Container) HostsManager() *hosts.Manager {
	if s.hostsManager == nil {
		s.hostsManager = hosts.NewManager(s.Config().Languages)
	}

	return s.hostsManager
}

func (s *Container) LogRepository(ctx context.Context) (*log.Repository, error) {
	if s.logRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.logRepository = log.NewRepository(db)
	}

	return s.logRepository, nil
}

func (s *Container) PicturesRepository(ctx context.Context) (*pictures.Repository, error) {
	if s.picturesRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		is, err := s.ImageStorage(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.TextStorageRepository(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		s.picturesRepository = pictures.NewRepository(
			db, is, textStorageRepository, itemsRepository, cfg.DuplicateFinder,
			func(id int64) error {
				commentsRepository, err := s.CommentsRepository(ctx)
				if err != nil {
					return err
				}

				return commentsRepository.DeleteTopic(ctx, schema.CommentMessageTypeIDPictures, id)
			},
		)
	}

	return s.picturesRepository, nil
}

func (s *Container) PublicHTTPServer(ctx context.Context) (*http.Server, error) {
	if s.publicHTTPServer == nil {
		cfg := s.Config()

		handler, err := s.PublicRouter(ctx)
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

func (s *Container) ItemsREST(ctx context.Context) (*ItemsREST, error) {
	itemsRepo, err := s.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	auth, err := s.Auth(ctx)
	if err != nil {
		return nil, err
	}

	events, err := s.Events(ctx)
	if err != nil {
		return nil, err
	}

	return NewItemsREST(auth, itemsRepo, events), nil
}

func (s *Container) UsersREST(ctx context.Context) (*UsersREST, error) {
	usersRepo, err := s.UsersRepository(ctx)
	if err != nil {
		return nil, err
	}

	auth, err := s.Auth(ctx)
	if err != nil {
		return nil, err
	}

	return NewUsersREST(auth, usersRepo), nil
}

func (s *Container) PicturesREST(ctx context.Context) (*PicturesREST, error) {
	auth, err := s.Auth(ctx)
	if err != nil {
		return nil, err
	}

	picturesRepo, err := s.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	imageStorage, err := s.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	itemOfDayRepo, err := s.ItemOfDayRepository(ctx)
	if err != nil {
		return nil, err
	}

	itemsRepo, err := s.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.I18n()
	if err != nil {
		return nil, err
	}

	usersRepo, err := s.UsersRepository(ctx)
	if err != nil {
		return nil, err
	}

	commentsRepo, err := s.CommentsRepository(ctx)
	if err != nil {
		return nil, err
	}

	df, err := s.DuplicateFinder(ctx)
	if err != nil {
		return nil, err
	}

	ts, err := s.TelegramService(ctx)
	if err != nil {
		return nil, err
	}

	pictureNameFormatter := pictures.NewPictureNameFormatter(
		items.NewItemNameFormatter(i18nBundle),
		i18nBundle,
	)

	itemOfDayCached, err := s.ItemOfDayCached(ctx)
	if err != nil {
		return nil, err
	}

	return NewPicturesREST(
		auth,
		picturesRepo,
		pictureNameFormatter,
		s.HostsManager(),
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

func (s *Container) PublicRouter(ctx context.Context) (http.HandlerFunc, error) {
	if s.publicRouter != nil {
		return s.publicRouter, nil
	}

	grpcServer, err := s.GRPCServerWithServices(ctx)
	if err != nil {
		return nil, fmt.Errorf("GRPCServerWithServices(): %w", err)
	}

	originFunc := func(origin string) bool {
		return util.Contains(s.config.PublicRest.Cors.Origin, origin)
	}
	wrappedGrpc := grpcweb.WrapServer(grpcServer, grpcweb.WithOriginFunc(originFunc))

	yoomoney, err := s.YoomoneyHandler(ctx)
	if err != nil {
		return nil, err
	}

	tg, err := s.TelegramService(ctx)
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

	yoomoney.SetupRouter(ctx, ginEngine)

	tg.SetupRouter(ginEngine) //nolint: contextcheck

	picturesREST, err := s.PicturesREST(ctx)
	if err != nil {
		return nil, fmt.Errorf("PicturesREST(): %w", err)
	}

	picturesREST.SetupRouter(ginEngine) //nolint: contextcheck

	itemsREST, err := s.ItemsREST(ctx)
	if err != nil {
		return nil, fmt.Errorf("ItemsREST(): %w", err)
	}

	itemsREST.SetupRouter(ginEngine) //nolint: contextcheck

	usersREST, err := s.UsersREST(ctx)
	if err != nil {
		return nil, fmt.Errorf("UsersREST(): %w", err)
	}

	usersREST.SetupRouter(ginEngine) //nolint: contextcheck

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

func (s *Container) GRPCServerWithServices(ctx context.Context) (*grpc.Server, error) {
	if s.grpcServerWithServices != nil {
		return s.grpcServerWithServices, nil
	}

	srv, err := s.GRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	articlesSrv, err := s.ArticlesGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	attrsSrv, err := s.AttrsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	commentsSrv, err := s.CommentsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	contactsSrv, err := s.ContactsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	donationsSrv, err := s.DonationsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	forumsSrv, err := s.ForumsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	itemsSrv, err := s.ItemsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	logSrv, err := s.LogGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	mapSrv, err := s.MapGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	mostsSrv, err := s.MostsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	textSrv, err := s.TextGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	trafficSrv, err := s.TrafficGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	picturesSrv, err := s.PicturesGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	messagingSrv, err := s.MessagingGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	usersSrv, err := s.UsersGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	statSrv, err := s.StatisticsGRPCServer(ctx)
	if err != nil {
		return nil, err
	}

	ratingSrv, err := s.RatingGRPCServer(ctx)
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

	logger := logrus.StandardLogger()
	loggerOpts := []logging.Option{
		logging.WithLogOnEvents(logging.StartCall, logging.FinishCall),
	}

	grpcServer := grpc.NewServer(
		grpc.ChainUnaryInterceptor(
			logging.UnaryServerInterceptor(InterceptorLogger(logger), loggerOpts...),
			realip.UnaryServerInterceptorOpts(opts...),
		),
		grpc.ChainStreamInterceptor(
			logging.StreamServerInterceptor(InterceptorLogger(logger), loggerOpts...),
			realip.StreamServerInterceptorOpts(opts...),
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

	reflection.Register(grpcServer)

	s.grpcServerWithServices = grpcServer

	return s.grpcServerWithServices, nil
}

func (s *Container) TelegramService(ctx context.Context) (*telegram.Service, error) {
	if s.telegramService == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		itemRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.MessagingRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.telegramService = telegram.NewService(
			s.Config().Telegram,
			db,
			s.HostsManager(),
			userRepository,
			itemRepository,
			messagingRepository,
			picturesRepository,
		)
	}

	return s.telegramService, nil
}

func (s *Container) Traffic(ctx context.Context) (*traffic.Traffic, error) {
	if s.traffic == nil {
		db, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		autowpDB, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		banRepository, err := s.BanRepository(ctx)
		if err != nil {
			return nil, err
		}

		traf, err := traffic.NewTraffic(db, autowpDB, banRepository)
		if err != nil {
			logrus.Error(err.Error())

			return nil, err
		}

		s.traffic = traf
	}

	return s.traffic, nil
}

func (s *Container) UserExtractor(ctx context.Context) (*UserExtractor, error) {
	is, err := s.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	picRepository, err := s.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	return NewUserExtractor(is, picRepository), nil
}

func (s *Container) VotingsRepository(ctx context.Context) (*votings.Repository, error) {
	if s.votingsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.votingsRepository = votings.NewRepository(db)
	}

	return s.votingsRepository, nil
}

func (s *Container) UsersRepository(ctx context.Context) (*users.Repository, error) {
	if s.usersRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		postgresDB, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		is, err := s.ImageStorage(ctx)
		if err != nil {
			return nil, err
		}

		s.usersRepository = users.NewRepository(
			db,
			postgresDB,
			cfg.UsersSalt,
			cfg.Languages,
			s.Keycloak(),
			cfg.Keycloak,
			cfg.MessageInterval,
			is,
		)
	}

	return s.usersRepository, nil
}

func (s *Container) I18n() (*i18nbundle.I18n, error) {
	if s.i18n == nil {
		i, err := i18nbundle.New()
		if err != nil {
			return nil, err
		}

		s.i18n = i
	}

	return s.i18n, nil
}

func (s *Container) ItemsRepository(ctx context.Context) (*items.Repository, error) {
	if s.itemsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		goquPgDB, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		cfg := s.Config()

		textStorageRepository, err := s.TextStorageRepository(ctx)
		if err != nil {
			return nil, err
		}

		imageStorage, err := s.ImageStorage(ctx)
		if err != nil {
			return nil, err
		}

		itemParentLanguageRepository, err := s.ItemParentLanguageRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.itemsRepository = items.NewRepository(
			db,
			goquPgDB,
			cfg.MostsMinCarsCount,
			itemParentLanguageRepository,
			textStorageRepository,
			imageStorage,
		)
	}

	return s.itemsRepository, nil
}

func (s *Container) ItemParentLanguageRepository(ctx context.Context) (*items.ItemParentLanguageRepository, error) {
	if s.itemsRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		pgDB, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		s.itemParentLanguageRepository = items.NewItemParentLanguageRepository(
			db,
			pgDB,
			s.Config().ContentLanguages,
		)
	}

	return s.itemParentLanguageRepository, nil
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
	if s.auth == nil {
		cfg := s.Config()

		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		rep, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.auth = NewAuth(db, s.Keycloak(), cfg.Keycloak, rep)
	}

	return s.auth, nil
}

func (s *Container) GRPCServer(ctx context.Context) (*GRPCServer, error) {
	if s.grpcServer == nil {
		cfg := s.Config()

		commentsRepository, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		fb, err := s.Feedback()
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		ipExtractor, err := s.IPExtractor(ctx)
		if err != nil {
			return nil, err
		}

		s.grpcServer = NewGRPCServer(
			auth,
			cfg.Recaptcha,
			commentsRepository,
			ipExtractor,
			fb,
			cfg.Captcha,
		)
	}

	return s.grpcServer, nil
}

func (s *Container) StatisticsGRPCServer(ctx context.Context) (*StatisticsGRPCServer, error) {
	if s.statisticsGrpcServer == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.statisticsGrpcServer = NewStatisticsGRPCServer(db, s.Config().About)
	}

	return s.statisticsGrpcServer, nil
}

func (s *Container) TextGRPCServer(ctx context.Context) (*TextGRPCServer, error) {
	if s.textGrpcServer == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.textGrpcServer = NewTextGRPCServer(db)
	}

	return s.textGrpcServer, nil
}

func (s *Container) TrafficGRPCServer(ctx context.Context) (*TrafficGRPCServer, error) {
	if s.trafficGrpcServer == nil {
		traf, err := s.Traffic(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		usersRepo, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.UserExtractor(ctx)
		if err != nil {
			return nil, err
		}

		s.trafficGrpcServer = NewTrafficGRPCServer(auth, usersRepo, userExtractor, traf)
	}

	return s.trafficGrpcServer, nil
}

func (s *Container) UsersGRPCServer(ctx context.Context) (*UsersGRPCServer, error) {
	if s.usersGrpcServer == nil {
		cfg := s.Config()

		contactsRepository, err := s.ContactsRepository(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.Events(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.UserExtractor(ctx)
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

func (s *Container) VotingsGRPCServer(ctx context.Context) (*VotingsGRPCServer, error) {
	if s.votingsGrpcServer == nil {
		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		votingsRepo, err := s.VotingsRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.votingsGrpcServer = NewVotingsGRPCServer(votingsRepo, auth)
	}

	return s.votingsGrpcServer, nil
}

func (s *Container) RatingGRPCServer(ctx context.Context) (*RatingGRPCServer, error) {
	if s.ratingGrpcServer == nil {
		commentsRepository, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.AttrsRepository(ctx)
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

func (s *Container) ItemOfDayCached(ctx context.Context) (*ItemOfDayCached, error) {
	if s.itemOfDayCached == nil {
		itemOfDayRepo, err := s.ItemOfDayRepository(ctx)
		if err != nil {
			return nil, err
		}

		itemsRepo, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepo, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		redisClient, err := s.Redis()
		if err != nil {
			return nil, err
		}

		s.itemOfDayCached = NewItemOfDayCached(
			itemOfDayRepo, itemsRepo, picturesRepo, s.Config().ContentLanguages, redisClient, s.ItemExtractor(),
		)
	}

	return s.itemOfDayCached, nil
}

func (s *Container) ItemsGRPCServer(ctx context.Context) (*ItemsGRPCServer, error) {
	if s.itemsGrpcServer == nil {
		repo, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.TextStorageRepository(ctx)
		if err != nil {
			return nil, err
		}

		extractor := s.ItemExtractor()

		i18n, err := s.I18n()
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.AttrsRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		idx, err := s.Index(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.Events(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.MessagingRepository(ctx)
		if err != nil {
			return nil, err
		}

		redisClient, err := s.Redis()
		if err != nil {
			return nil, err
		}

		catalogue, err := s.Catalogue(ctx)
		if err != nil {
			return nil, err
		}

		itemOfDayCached, err := s.ItemOfDayCached(ctx)
		if err != nil {
			return nil, err
		}

		itemParentLanguageRepository, err := s.ItemParentLanguageRepository(ctx)
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
			s.HostsManager(),
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

func (s *Container) MostsGRPCServer(ctx context.Context) (*MostsGRPCServer, error) {
	if s.mostsGrpcServer == nil {
		mostsRepository, err := s.MostsRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
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

func (s *Container) CommentsGRPCServer(ctx context.Context) (*CommentsGRPCServer, error) {
	if s.commentsGrpcServer == nil {
		commentsRepository, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		usersRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		picturesRepository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.UserExtractor(ctx)
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

func (s *Container) ArticlesGRPCServer(ctx context.Context) (*ArticlesGRPCServer, error) {
	if s.articlesGRPCServer == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.articlesGRPCServer = NewArticlesGRPCServer(db)
	}

	return s.articlesGRPCServer, nil
}

func (s *Container) AttrsGRPCServer(ctx context.Context) (*AttrsGRPCServer, error) {
	if s.attrsGRPCServer == nil {
		repository, err := s.AttrsRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		s.attrsGRPCServer = NewAttrsGRPCServer(repository, auth)
	}

	return s.attrsGRPCServer, nil
}

func (s *Container) ContactsGRPCServer(ctx context.Context) (*ContactsGRPCServer, error) {
	if s.contactsGrpcServer == nil {
		contactsRepository, err := s.ContactsRepository(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		userExtractor, err := s.UserExtractor(ctx)
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

func (s *Container) LogGRPCServer(ctx context.Context) (*LogGRPCServer, error) {
	if s.LogGrpcServer == nil {
		repository, err := s.LogRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		s.LogGrpcServer = NewLogGRPCServer(repository, auth)
	}

	return s.LogGrpcServer, nil
}

func (s *Container) PicturesGRPCServer(ctx context.Context) (*PicturesGRPCServer, error) {
	if s.picturesGrpcServer == nil {
		repository, err := s.PicturesRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		events, err := s.Events(ctx)
		if err != nil {
			return nil, err
		}

		messagingRepository, err := s.MessagingRepository(ctx)
		if err != nil {
			return nil, err
		}

		userRepository, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		duplicateFinder, err := s.DuplicateFinder(ctx)
		if err != nil {
			return nil, err
		}

		textStorageRepository, err := s.TextStorageRepository(ctx)
		if err != nil {
			return nil, err
		}

		tg, err := s.TelegramService(ctx)
		if err != nil {
			return nil, err
		}

		itemRepository, err := s.ItemsRepository(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepository, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		catalogue, err := s.Catalogue(ctx)
		if err != nil {
			return nil, err
		}

		itemOfDayCached, err := s.ItemOfDayCached(ctx)
		if err != nil {
			return nil, err
		}

		s.picturesGrpcServer = NewPicturesGRPCServer(
			repository,
			auth,
			events,
			s.HostsManager(),
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

func (s *Container) MapGRPCServer(ctx context.Context) (*MapGRPCServer, error) {
	if s.mapGrpcServer == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		imageStorage, err := s.ImageStorage(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.I18n()
		if err != nil {
			return nil, err
		}

		s.mapGrpcServer = NewMapGRPCServer(db, imageStorage, i18n)
	}

	return s.mapGrpcServer, nil
}

func (s *Container) DonationsGRPCServer(ctx context.Context) (*DonationsGRPCServer, error) {
	if s.donationsGrpcServer == nil {
		repository, err := s.ItemOfDayRepository(ctx)
		if err != nil {
			return nil, err
		}

		db, err := s.GoquPostgresDB(ctx)
		if err != nil {
			return nil, err
		}

		s.donationsGrpcServer = NewDonationsGRPCServer(repository, s.Config().DonationsVodPrice, db)
	}

	return s.donationsGrpcServer, nil
}

func (s *Container) ForumsGRPCServer(ctx context.Context) (*ForumsGRPCServer, error) {
	if s.forumsGrpcServer == nil {
		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		forums, err := s.Forums(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepo, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		usersRepo, err := s.UsersRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.forumsGrpcServer = NewForumsGRPCServer(auth, forums, commentsRepo, usersRepo)
	}

	return s.forumsGrpcServer, nil
}

func (s *Container) MessagingGRPCServer(ctx context.Context) (*MessagingGRPCServer, error) {
	if s.messagingGrpcServer == nil {
		repository, err := s.MessagingRepository(ctx)
		if err != nil {
			return nil, err
		}

		auth, err := s.Auth(ctx)
		if err != nil {
			return nil, err
		}

		s.messagingGrpcServer = NewMessagingGRPCServer(repository, auth)
	}

	return s.messagingGrpcServer, nil
}

func (s *Container) Forums(ctx context.Context) (*Forums, error) {
	if s.forums == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		commentsRepository, err := s.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		s.forums = NewForums(db, commentsRepository)
	}

	return s.forums, nil
}

func (s *Container) ItemOfDayRepository(ctx context.Context) (*itemofday.Repository, error) {
	if s.itemOfDayRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.itemOfDayRepository = itemofday.NewRepository(db)
	}

	return s.itemOfDayRepository, nil
}

func (s *Container) MessagingRepository(ctx context.Context) (*messaging.Repository, error) {
	if s.messagingRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		i18n, err := s.I18n()
		if err != nil {
			return nil, err
		}

		s.messagingRepository = messaging.NewRepository(
			db,
			func(ctx context.Context, fromUserID int64, toUserID int64, text string) error {
				tg, err := s.TelegramService(ctx)
				if err != nil {
					return err
				}

				return tg.NotifyMessage(ctx, fromUserID, toUserID, text)
			},
			i18n,
		)
	}

	return s.messagingRepository, nil
}

func (s *Container) Keycloak() *gocloak.GoCloak {
	if s.keyCloak == nil {
		client := gocloak.NewClient(s.Config().Keycloak.URL)

		s.keyCloak = client
	}

	return s.keyCloak
}

func (s *Container) EmailSender() email.Sender { //nolint: ireturn
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

func (s *Container) SetEmailSender(emailSender email.Sender) {
	s.emailSender = emailSender
}

func (s *Container) Events(ctx context.Context) (*Events, error) {
	if s.events == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.events = NewEvents(db)
	}

	return s.events, nil
}

func (s *Container) ImageStorage(ctx context.Context) (*storage.Storage, error) {
	if s.imageStorage == nil {
		db, err := s.GoquDB(ctx)
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

func (s *Container) Redis() (*redis.Client, error) {
	if s.redis == nil {
		opts, err := redis.ParseURL(s.Config().Redis)
		if err != nil {
			return nil, err
		}

		s.redis = redis.NewClient(opts)
	}

	return s.redis, nil
}

func (s *Container) Index(ctx context.Context) (*index.Index, error) {
	redisClient, err := s.Redis()
	if err != nil {
		return nil, err
	}

	repository, err := s.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	return index.NewIndex(redisClient, repository), nil
}

func (s *Container) TextStorageRepository(ctx context.Context) (*textstorage.Repository, error) {
	if s.textStorageRepository == nil {
		db, err := s.GoquDB(ctx)
		if err != nil {
			return nil, err
		}

		s.textStorageRepository = textstorage.New(db)
	}

	return s.textStorageRepository, nil
}

func (s *Container) YoomoneyHandler(ctx context.Context) (*YoomoneyHandler, error) {
	if s.yoomoneyHandler == nil {
		repository, err := s.ItemOfDayRepository(ctx)
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

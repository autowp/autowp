package goautowp

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"sync"
	"time"

	"github.com/autowp/goautowp/attrsamqp"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/golang-migrate/migrate/v4"
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // enable postgres migrations
	_ "github.com/golang-migrate/migrate/v4/source/file"       // enable file migration source
	_ "github.com/lib/pq"                                      // enable postgres driver
	"github.com/prometheus/client_golang/prometheus/promhttp"
	"github.com/sirupsen/logrus"
)

type ServeOptions struct {
	DuplicateFinderAMQP   bool
	MonitoringAMQP        bool
	GRPC                  bool
	Public                bool
	Autoban               bool
	AttrsUpdateValuesAMQP bool
}

// Application is Service Main Object.
type Application struct {
	container *Container
}

// NewApplication constructor.
func NewApplication(cfg config.Config) *Application {
	app := &Application{
		container: NewContainer(cfg),
	}

	return app
}

func (s *Application) ServeGRPC(ctx context.Context, quit chan bool) error {
	grpcServer, err := s.container.GRPCServerWithServices(ctx)
	if err != nil {
		return err
	}

	var lc net.ListenConfig

	lis, err := lc.Listen(ctx, "tcp", s.container.Config().GRPC.Listen)
	if err != nil {
		return err
	}

	go func() {
		<-quit

		grpcServer.GracefulStop()
	}()

	logrus.Println("gRPC listener started")

	err = grpcServer.Serve(lis)
	if err != nil {
		// cannot panic, because this probably is an intentional close
		logrus.Printf("gRPC: Serve() error: %s", err)
	}

	logrus.Println("gRPC listener stopped")

	return nil
}

func (s *Application) Serve(ctx context.Context, options ServeOptions, quit chan bool) error {
	wg := sync.WaitGroup{}

	if options.DuplicateFinderAMQP {
		wg.Add(1)

		go func() {
			err := s.ListenDuplicateFinderAMQP(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	if options.MonitoringAMQP {
		wg.Add(1)

		go func() {
			err := s.ListenMonitoringAMQP(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	if options.GRPC {
		wg.Add(1)

		go func() {
			err := s.ServeGRPC(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	if options.Public {
		wg.Add(1)

		go func() {
			err := s.ServePublic(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()

		wg.Add(1)

		go func() {
			err := s.ListenMessagingWSEvents(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()

		wg.Add(1)

		go func() {
			err := s.ListenPicturesWSEvents(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	if options.Autoban {
		wg.Add(1)

		go func() {
			err := s.Autoban(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	if options.AttrsUpdateValuesAMQP {
		wg.Add(1)

		go func() {
			err := s.AttrsUpdateValuesAMQP(ctx, quit)
			if err != nil {
				logrus.Errorln(err.Error())
			}

			wg.Done()
		}()
	}

	wg.Add(1)

	go func() {
		err := s.ServeMetrics(ctx, quit)
		if err != nil {
			logrus.Errorln(err.Error())
		}

		wg.Done()
	}()

	wg.Wait()

	return nil
}

func (s *Application) ServeMetrics(ctx context.Context, quit chan bool) error {
	cfg := s.container.Config()

	httpServer := &http.Server{
		Addr:              cfg.Metrics.Listen,
		Handler:           promhttp.Handler(),
		ReadHeaderTimeout: readHeaderTimeout,
	}

	go func(ctx context.Context) {
		<-quit

		if err := httpServer.Shutdown(ctx); err != nil {
			logrus.Error(err.Error())
		}
	}(ctx)

	logrus.Infoln("metrics HTTP listener started")

	err := httpServer.ListenAndServe()
	if err != nil {
		if !errors.Is(err, http.ErrServerClosed) {
			return err
		}
	}

	logrus.Infoln("metrics HTTP listener stopped")

	return nil
}

func (s *Application) ServePublic(ctx context.Context, quit chan bool) error {
	httpServer, err := s.container.PublicHTTPServer(ctx)
	if err != nil {
		return fmt.Errorf("PublicHTTPServer(): %w", err)
	}

	go func(ctx context.Context) {
		<-quit

		if err := httpServer.Shutdown(ctx); err != nil {
			logrus.Error(err.Error())
		}
	}(ctx)

	logrus.Infoln("public HTTP listener started")

	err = httpServer.ListenAndServe()
	if err != nil {
		if !errors.Is(err, http.ErrServerClosed) {
			return err
		}
	}

	logrus.Infoln("public HTTP listener stopped")

	return nil
}

// ListenMessagingWSEvents fans out Redis-published "messages changed" events to this
// pod's local /ws/messages connections. Only meaningful while ServePublic is also
// running, since that's what owns the hub and accepts the connections.
func (s *Application) ListenMessagingWSEvents(ctx context.Context, quit chan bool) error {
	redisClient, err := s.container.Redis()
	if err != nil {
		return err
	}

	hub := s.container.MessagingHub()

	logrus.Info("Messaging WS listener started")

	err = messaging.Subscribe(ctx, redisClient, hub, quit)
	if err != nil {
		return err
	}

	logrus.Info("Messaging WS listener stopped")

	return nil
}

// ListenPicturesWSEvents fans out Redis-published "picture accepted" events to this
// pod's local /ws/pictures connections. Only meaningful while ServePublic is also
// running, since that's what owns the hub and accepts the connections.
func (s *Application) ListenPicturesWSEvents(ctx context.Context, quit chan bool) error {
	redisClient, err := s.container.Redis()
	if err != nil {
		return err
	}

	hub := s.container.PicturesHub()

	logrus.Info("Pictures WS listener started")

	err = pictures.Subscribe(ctx, redisClient, hub, quit)
	if err != nil {
		return err
	}

	logrus.Info("Pictures WS listener stopped")

	return nil
}

func (s *Application) ListenDuplicateFinderAMQP(ctx context.Context, quit chan bool) error {
	df, err := s.container.DuplicateFinder(ctx)
	if err != nil {
		return err
	}

	logrus.Println("DuplicateFinder listener started")

	err = df.ListenAMQP(ctx, quit)
	if err != nil {
		return err
	}

	logrus.Println("DuplicateFinder listener stopped")

	return nil
}

// Close Destructor.
func (s *Application) Close() error {
	logrus.Println("Closing service")

	if err := s.container.Close(); err != nil {
		return err
	}

	logrus.Println("Service closed")

	return nil
}

func applyMigrations(config config.MigrationsConfig) error {
	logrus.Info("Apply migrations")

	dir := config.Dir
	if dir == "" {
		ex, err := os.Executable()
		if err != nil {
			return err
		}

		exPath := filepath.Dir(ex)
		dir = exPath + "/migrations"
	}

	m, err := migrate.New("file://"+dir, config.DSN)
	if err != nil {
		return err
	}

	err = m.Up()
	if err != nil {
		return err
	}

	logrus.Info("Migrations applied")

	return nil
}

func (s *Application) MigratePostgres(ctx context.Context) error {
	_, err := s.container.GoquDB(ctx)
	if err != nil {
		return err
	}

	cfg := s.container.Config()

	err = applyMigrations(cfg.PostgresMigrations)
	if err != nil && !errors.Is(err, migrate.ErrNoChange) {
		return err
	}

	return nil
}

func (s *Application) SchedulerHourly(ctx context.Context) error {
	traffic, err := s.container.Traffic(ctx)
	if err != nil {
		return err
	}

	deleted, err := traffic.Monitoring.GC(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("`%v` items of monitoring deleted", deleted)

	deleted, err = traffic.Ban.GC(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("`%v` items of ban deleted", deleted)

	err = traffic.AutoWhitelist(ctx)
	if err != nil {
		return err
	}

	return nil
}

// SpecsVolumeBackfill invalidates every non-deleted user's specs_volume and
// immediately recomputes it. One-off admin command for the bug where
// specs_volume froze after its first computation (attrs_user_values changes
// never invalidated it) — new writes are fixed going forward via
// users.Repository.InvalidateSpecsVolume, but already-affected users need
// this to catch up once.
func (s *Application) SpecsVolumeBackfill(ctx context.Context) error {
	usersRep, err := s.container.UsersRepository(ctx)
	if err != nil {
		return err
	}

	if err := usersRep.InvalidateAllSpecsVolumes(ctx); err != nil {
		return err
	}

	return usersRep.UpdateSpecsVolumes(ctx)
}

func (s *Application) SchedulerDaily(ctx context.Context) error {
	usersRep, err := s.container.UsersRepository(ctx)
	if err != nil {
		return err
	}

	err = usersRep.UpdateSpecsVolumes(ctx)
	if err != nil {
		return err
	}

	commentsRep, err := s.container.CommentsRepository(ctx)
	if err != nil {
		return err
	}

	affected, err := commentsRep.CleanupDeleted(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Comments deleted: %d", affected)

	err = usersRep.DeleteUnused(ctx)
	if err != nil {
		return err
	}

	anonymizedIPs, err := usersRep.AnonymizeOldContentIPs(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Content IPs anonymized: %d", anonymizedIPs)

	purgedConsent, err := usersRep.PurgeOldConsentLog(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Consent log rows purged: %d", purgedConsent)

	messRepo, err := s.container.MessagingRepository(ctx)
	if err != nil {
		return err
	}

	deleted, err := messRepo.Recycle(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("%d messages was deleted", deleted)

	deleted, err = messRepo.RecycleSystem(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("%d messages was deleted", deleted)

	// affected, err = commentsRep.RefreshRepliesCount(ctx)
	// if err != nil {
	//	logrus.Error(err.Error())
	//
	//	return err
	// }
	//
	// logrus.Infof("Replies refreshed: %d", affected)

	// affected, err = commentsRep.CleanBrokenMessages(ctx)
	// if err != nil {
	//	logrus.Error(err.Error())
	//
	//	return err
	// }
	//
	// logrus.Infof("Clean broken: %d", affected)

	// affected, err = commentsRep.CleanTopics(ctx)
	// if err != nil {
	//	logrus.Error(err.Error())
	//
	//	return err
	// }
	//
	// logrus.Infof("Clean topics: %d", affected)

	achievementsRep, err := s.container.AchievementsRepository(ctx)
	if err != nil {
		return err
	}

	achievementsGranted, err := achievementsRep.RecomputeTopPicturesContributors(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Top pictures contributor achievement newly granted to %d users", achievementsGranted)

	veteransGranted, err := achievementsRep.RecomputeVeteranBadges(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Veteran achievement newly granted to %d users", veteransGranted)

	return nil
}

func (s *Application) SchedulerMidnight(ctx context.Context) error {
	ur, err := s.container.UsersRepository(ctx)
	if err != nil {
		return err
	}

	err = ur.RestoreVotes(ctx)
	if err != nil {
		return err
	}

	affected, err := ur.UpdateVotesLimits(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("Updated %d users vote limits", affected)

	idr, err := s.container.ItemOfDayRepository(ctx)
	if err != nil {
		return err
	}

	success, err := idr.Pick(ctx)
	if err != nil {
		return err
	}

	logrus.Infof("item of day status: `%v`", success)

	return nil
}

func (s *Application) Autoban(ctx context.Context, quit chan bool) error {
	traffic, err := s.container.Traffic(ctx)
	if err != nil {
		return err
	}

	banTicker := time.NewTicker(time.Minute)

	logrus.Info("AutoBan scheduler started")

loop:
	for {
		select {
		case <-banTicker.C:
			err := traffic.AutoBan(ctx)
			if err != nil {
				logrus.Error(err.Error())
			}
		case <-quit:
			banTicker.Stop()

			break loop
		}
	}

	logrus.Info("AutoBan scheduler stopped")

	return nil
}

func (s *Application) GenerateIndexCache(ctx context.Context) error {
	idx, err := s.container.Index(ctx)
	if err != nil {
		return err
	}

	for lang := range s.container.Config().Languages {
		err = idx.GenerateTopBrandsCache(ctx, lang)
		if err != nil {
			return err
		}

		err = idx.GenerateBrandsCache(ctx, lang)
		if err != nil {
			return err
		}

		err = idx.GenerateTwinsCache(ctx, lang)
		if err != nil {
			return err
		}

		err = idx.GenerateCategoriesCache(ctx, lang)
		if err != nil {
			return err
		}

		err = idx.GeneratePersonsCache(ctx, schema.PictureItemTypeContent, lang)
		if err != nil {
			return err
		}

		err = idx.GeneratePersonsCache(ctx, schema.PictureItemTypeAuthor, lang)
		if err != nil {
			return err
		}

		err = idx.GenerateFactoriesCache(ctx, lang)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Application) SpecsRefreshConflictFlags(ctx context.Context) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshConflictFlags(ctx)
}

func (s *Application) SpecsRefreshActualValues(ctx context.Context) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.UpdateAllActualValues(ctx)
}

func (s *Application) RefreshItemParentLanguage(
	ctx context.Context, parentItemTypeID schema.ItemTableItemTypeID, limit uint,
) error {
	repository, err := s.container.ItemParentLanguageRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshItemParentLanguage(ctx, parentItemTypeID, limit)
}

func (s *Application) RefreshItemParentAllAuto(ctx context.Context) error {
	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshItemParentAllAuto(ctx)
}

func (s *Application) RebuildItemOrderCache(ctx context.Context) error {
	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RebuildItemOrderCache(ctx)
}

func (s *Application) PicturesDfIndex(ctx context.Context) error {
	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return err
	}

	return repository.DfIndex(ctx)
}

func (s *Application) PicturesFixFilenames(ctx context.Context) error {
	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return err
	}

	return repository.CorrectAllFileNames(ctx)
}

func (s *Application) PicturesClearQueue(ctx context.Context) error {
	picturesRepo, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return err
	}

	return picturesRepo.ClearQueue(ctx)
}

func (s *Application) BuildBrandsSprite(ctx context.Context) error {
	imageStorage, err := s.container.ImageStorage(ctx)
	if err != nil {
		return err
	}

	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return err
	}

	cfg := s.container.Config()

	return createIconsSprite(ctx, repository, imageStorage, cfg.FileStorage)
}

func (s *Application) TelegramWebhookInfo(ctx context.Context) error {
	telegram, err := s.container.TelegramService(ctx)
	if err != nil {
		return err
	}

	return telegram.WebhookInfo()
}

func (s *Application) TelegramRegisterWebhook(ctx context.Context) error {
	telegram, err := s.container.TelegramService(ctx)
	if err != nil {
		return err
	}

	return telegram.RegisterWebhook()
}

func (s *Application) SpecsRefreshUsersConflicts(ctx context.Context) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshUserConflictsStat(ctx, nil, true)
}

func (s *Application) SpecsRefreshUserConflicts(ctx context.Context, userID int64) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshUserConflictsStat(ctx, []int64{userID}, false)
}

func (s *Application) SpecsRefreshItemConflictFlags(ctx context.Context, itemID int64) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	return repository.RefreshItemConflictFlags(ctx, itemID)
}

func (s *Application) ExportUsersToKeycloak(ctx context.Context) error {
	ur, err := s.container.UsersRepository(ctx)
	if err != nil {
		return err
	}

	return ur.ExportUsersToKeycloak(ctx)
}

func (s *Application) ListenMonitoringAMQP(ctx context.Context, quit chan bool) error {
	traffic, err := s.container.Traffic(ctx)
	if err != nil {
		return err
	}

	cfg := s.container.Config()

	logrus.Info("Monitoring listener started")

	err = traffic.Monitoring.Listen(ctx, cfg.RabbitMQ, cfg.MonitoringQueue, quit)
	if err != nil {
		return err
	}

	logrus.Info("Monitoring listener stopped")

	return nil
}

func (s *Application) AttrsUpdateValuesAMQP(ctx context.Context, quit chan bool) error {
	repository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return err
	}

	listener := attrsamqp.NewAttrsAMQP(repository)

	cfg := s.container.Config()

	logrus.Info("AttrsUpdateValuesAMQP listener started")

	err = listener.ListenUpdateValues(ctx, cfg.RabbitMQ, cfg.Attrs.AttrsUpdateValuesQueue, quit)
	if err != nil {
		return err
	}

	logrus.Info("AttrsUpdateValuesAMQP listener stopped")

	return nil
}

func (s *Application) ImageStorageGetImage(ctx context.Context, imageID int) (*Image, error) {
	is, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	img, err := is.Image(ctx, imageID)
	if err != nil {
		return nil, err
	}

	return APIImageToGRPC(img), nil
}

func (s *Application) ImageStorageGetFormattedImage(
	ctx context.Context,
	imageID int,
	format string,
) (*Image, error) {
	is, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	img, err := is.FormattedImage(ctx, imageID, format)
	if err != nil {
		return nil, err
	}

	return APIImageToGRPC(img), nil
}

func (s *Application) ImageStorageFlushFormattedImages(
	ctx context.Context,
	options storage.FlushOptions,
) error {
	is, err := s.container.ImageStorage(ctx)
	if err != nil {
		return err
	}

	return is.Flush(ctx, options)
}

func (s *Application) ImageStorageListBrokenImages(
	ctx context.Context,
	dir string,
	offset string,
) error {
	is, err := s.container.ImageStorage(ctx)
	if err != nil {
		return err
	}

	return is.ListBrokenImages(ctx, dir, offset)
}

func (s *Application) ImageStorageListUnlinkedObjects(
	ctx context.Context, dir string, moveToLostAndFound bool, offset string,
) error {
	is, err := s.container.ImageStorage(ctx)
	if err != nil {
		return err
	}

	return is.ListUnlinkedObjects(ctx, dir, moveToLostAndFound, offset)
}

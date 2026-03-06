package config

import (
	"fmt"
	"sync"

	"github.com/sirupsen/logrus"
	"github.com/spf13/viper"
)

// MigrationsConfig MigrationsConfig.
type MigrationsConfig struct {
	DSN string `mapstructure:"dsn" yaml:"dsn"`
	Dir string `mapstructure:"dir" yaml:"dir"`
}

// LanguageConfig LanguageConfig.
type LanguageConfig struct {
	Hostname string   `mapstructure:"hostname" yaml:"hostname"`
	Timezone string   `mapstructure:"timezone" yaml:"timezone"`
	Name     string   `mapstructure:"name"     yaml:"name"`
	Flag     string   `mapstructure:"flag"     yaml:"flag"`
	Aliases  []string `mapstructure:"aliases"  yaml:"aliases"`
}

// KeycloakConfig KeycloakConfig.
type KeycloakConfig struct {
	URL          string `mapstructure:"url"           yaml:"url"`
	ClientID     string `mapstructure:"client-id"     yaml:"client-id"`
	ClientSecret string `mapstructure:"client-secret" yaml:"client-secret"`
	Realm        string `mapstructure:"realm"         yaml:"realm"`
}

// SMTPConfig SMTPConfig.
type SMTPConfig struct {
	Hostname string `mapstructure:"hostname" yaml:"hostname"`
	Port     int    `mapstructure:"port"     yaml:"port"`
	Username string `mapstructure:"username" yaml:"username"`
	Password string `mapstructure:"password" yaml:"password"`
}

// FileStorageConfig FileStorageConfig.
type FileStorageConfig struct {
	S3     S3Config `mapstructure:"s3"     yaml:"s3"`
	Bucket string   `mapstructure:"bucket" yaml:"bucket"`
}

// S3Config S3Config.
type S3Config struct {
	Credentials          S3CredentialsConfig `mapstructure:"credentials"             yaml:"credentials"`
	Region               string              `mapstructure:"region"                  yaml:"region"`
	Endpoint             string              `mapstructure:"endpoint"                yaml:"endpoint"`
	UsePathStyleEndpoint bool                `mapstructure:"use_path_style_endpoint" yaml:"use_path_style_endpoint"`
}

// S3CredentialsConfig S3CredentialsConfig.
type S3CredentialsConfig struct {
	Key    string `mapstructure:"key"    yaml:"key"`
	Secret string `mapstructure:"secret" yaml:"secret"`
}

// DuplicateFinderConfig DuplicateFinderConfig.
type DuplicateFinderConfig struct {
	RabbitMQ string `mapstructure:"rabbitmq" yaml:"rabbitmq"`
	Queue    string `mapstructure:"queue"    yaml:"queue"`
}

// RestCorsConfig RestCorsConfig.
type RestCorsConfig struct {
	Origin []string `mapstructure:"origin"`
}

// RestConfig RestConfig.
type RestConfig struct {
	Listen string         `mapstructure:"listen"`
	Cors   RestCorsConfig `mapstructure:"cors"`
}

// RecaptchaConfig RecaptchaConfig.
type RecaptchaConfig struct {
	PublicKey  string `mapstructure:"public-key"  yaml:"public-key"`
	PrivateKey string `mapstructure:"private-key" yaml:"private-key"`
}

// FeedbackConfig FeedbackConfig.
type FeedbackConfig struct {
	From    string   `mapstructure:"from"    yaml:"from"`
	To      []string `mapstructure:"to"      yaml:"to"`
	Subject string   `mapstructure:"subject" yaml:"subject"`
}

type TelegramConfig struct {
	AccessToken  string `mapstructure:"access-token"  yaml:"access-token"`
	WebHook      string `mapstructure:"webhook"       yaml:"webhook"`
	WebhookToken string `mapstructure:"webhook-token" yaml:"webhook-token"`
}

type AboutConfig struct {
	Developer      string `mapstructure:"developer"        yaml:"developer"`
	FrTranslator   string `mapstructure:"fr-translator"    yaml:"fr-translator"`
	ZhTranslator   string `mapstructure:"zh-translator"    yaml:"zh-translator"`
	BeTranslator   string `mapstructure:"be-translator"    yaml:"be-translator"`
	PtBrTranslator string `mapstructure:"pt-br-translator" yaml:"pt-br-translator"`
}

type YoomoneyConfig struct {
	Secret string `mapstructure:"secret" yaml:"secret"`
	Price  string `mapstructure:"price"  yaml:"price"`
}

type GRPCConfig struct {
	Listen string `mapstructure:"listen" yaml:"listen"`
}

type MetricsConfig struct {
	Listen string `mapstructure:"listen" yaml:"listen"`
}

type AttrsAttrs struct {
	AttrsUpdateValuesQueue string `mapstructure:"update_values_queue" yaml:"update_values_queue"`
}

// Config Application config definition.
type Config struct {
	GRPC               GRPCConfig                `mapstructure:"grpc"                 yaml:"grpc"`
	Metrics            MetricsConfig             `mapstructure:"metrics"              yaml:"metrics"`
	Attrs              AttrsAttrs                `mapstructure:"attrs"                yaml:"attrs"`
	PublicRest         RestConfig                `mapstructure:"public-rest"          yaml:"public-rest"`
	DuplicateFinder    DuplicateFinderConfig     `mapstructure:"duplicate_finder"     yaml:"duplicate_finder"`
	AutowpDSN          string                    `mapstructure:"autowp-dsn"           yaml:"autowp-dsn"`
	AutowpMigrations   MigrationsConfig          `mapstructure:"autowp-migrations"    yaml:"autowp-migrations"`
	FileStorage        FileStorageConfig         `mapstructure:"file-storage"         yaml:"file-storage"`
	RabbitMQ           string                    `mapstructure:"rabbitmq"             yaml:"rabbitmq"`
	MonitoringQueue    string                    `mapstructure:"monitoring_queue"     yaml:"monitoring_queue"`
	Telegram           TelegramConfig            `mapstructure:"telegram"             yaml:"telegram"`
	PostgresDSN        string                    `mapstructure:"postgres-dsn"         yaml:"postgres-dsn"`
	PostgresMigrations MigrationsConfig          `mapstructure:"postgres-migrations"  yaml:"postgres-migrations"`
	Recaptcha          RecaptchaConfig           `mapstructure:"recaptcha"            yaml:"recaptcha"`
	MockEmailSender    bool                      `mapstructure:"mock-email-sender"    yaml:"mock-email-sender"`
	SMTP               SMTPConfig                `mapstructure:"smtp"                 yaml:"smtp"`
	Feedback           FeedbackConfig            `mapstructure:"feedback"             yaml:"feedback"`
	Keycloak           KeycloakConfig            `mapstructure:"keycloak"             yaml:"keycloak"`
	UsersSalt          string                    `mapstructure:"users-salt"           yaml:"users-salt"`
	EmailSalt          string                    `mapstructure:"email-salt"           yaml:"email-salt"`
	Languages          map[string]LanguageConfig `mapstructure:"languages"            yaml:"languages"`
	Captcha            bool                      `mapstructure:"captcha"              yaml:"captcha"`
	ImageStorage       ImageStorageConfig        `mapstructure:"image-storage"        yaml:"image-storage"`
	Redis              string                    `mapstructure:"redis"                yaml:"redis"`
	DonationsVodPrice  int32                     `mapstructure:"donations-vod-price"  yaml:"donations-vod-price"`
	About              AboutConfig               `mapstructure:"about"                yaml:"about"`
	ContentLanguages   []string                  `mapstructure:"content-languages"    yaml:"content-languages"`
	MessageInterval    int64                     `mapstructure:"message-interval"     yaml:"message-interval"`
	MostsMinCarsCount  int                       `mapstructure:"mosts-min-cars-count" yaml:"mosts-min-cars-count"`
	YoomoneyConfig     YoomoneyConfig            `mapstructure:"yoomoney"             yaml:"yoomoney"`
	TrustedNetwork     string                    `mapstructure:"trusted-network"      yaml:"trusted-network"`
}

var configMutex = sync.RWMutex{}

// LoadConfig LoadConfig.
func LoadConfig(path string) Config {
	configMutex.Lock()
	defer configMutex.Unlock()

	cfg := Config{}

	viper.SetConfigName("defaults")
	viper.SetConfigType("yaml")
	viper.AddConfigPath(path)

	err := viper.ReadInConfig()
	if err != nil {
		panic(err)
	}

	viper.SetConfigName("config")
	viper.SetConfigType("yaml")
	viper.AddConfigPath(path)

	err = viper.MergeInConfig()
	if err != nil {
		panic(err)
	}

	err = viper.Unmarshal(&cfg)
	if err != nil {
		panic(fmt.Errorf("fatal error unmarshal config: %w", err))
	}

	return cfg
}

// ValidateConfig ValidateConfig.
func ValidateConfig(config Config) {
	if config.DuplicateFinder.RabbitMQ == "" {
		logrus.Error("Address not provided")
	}

	if config.DuplicateFinder.Queue == "" {
		logrus.Error("DuplicateFinderQueue not provided")
	}

	if config.RabbitMQ == "" {
		logrus.Error("Address not provided")
	}

	if config.MonitoringQueue == "" {
		logrus.Error("MonitoringQueue not provided")
	}
}

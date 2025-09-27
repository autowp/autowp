package config

type FitType int

const (
	FitTypeInner   FitType = 0
	FitTypeOuter   FitType = 1
	FitTypeMaximum FitType = 2
)

type ImageStorageNamingStrategyConfig struct {
	Strategy string `mapstructure:"strategy"`
	Options  struct {
		Deep int `mapstructure:"deep"`
	} `mapstructure:"options"`
}

type ImageStorageDirConfig struct {
	NamingStrategy ImageStorageNamingStrategyConfig `mapstructure:"naming-strategy"`
	Bucket         string                           `mapstructure:"bucket"`
}

type ImageStorageConfig struct {
	Dirs    map[string]ImageStorageDirConfig           `mapstructure:"dirs"`
	Formats map[string]ImageStorageSamplerFormatConfig `mapstructure:"formats"`
	S3      struct {
		Region      string `mapstructure:"region"`
		Endpoint    string `mapstructure:"endpoint"`
		Credentials struct {
			Key    string `mapstructure:"key"`
			Secret string `mapstructure:"secret"`
		} `mapstructure:"credentials"`
		UsePathStyleEndpoint bool `mapstructure:"use_path_style_endpoint"`
	} `mapstructure:"s3"`
	SrcOverride struct {
		Host   string `mapstructure:"host"`
		Scheme string `mapstructure:"scheme"`
	} `mapstructure:"src-override"`
}

type ImageStorageSamplerFormatConfig struct {
	FitType          FitType `mapstructure:"fit-type"`
	Width            int     `mapstructure:"width"`
	Height           int     `mapstructure:"height"`
	Background       string  `mapstructure:"background"`
	Strip            bool    `mapstructure:"strip"`
	ReduceOnly       bool    `mapstructure:"reduce-only"`
	ProportionalCrop bool    `mapstructure:"proportional-crop"`
	Format           string  `mapstructure:"format"`
	IgnoreCrop       bool    `mapstructure:"ignore-crop"`
	Widest           float64 `mapstructure:"widest"`
	Highest          float64 `mapstructure:"highest"`
	Quality          uint    `mapstructure:"quality"`
}

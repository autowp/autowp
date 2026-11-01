package httperrors

type BadRequestResponse struct {
	InvalidParams map[string]map[string]string `json:"invalid_params"`
}

const (
	InvalidParams        = "invalid_params"
	FileIsImageFalseType = "fileIsImageFalseType"
	FileFilesSizeTooBig  = "fileFilesSizeTooBig"
)

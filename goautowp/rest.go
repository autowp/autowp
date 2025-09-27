package goautowp

type BadRequestResponse struct {
	InvalidParams map[string]map[string]string `json:"invalid_params"`
}

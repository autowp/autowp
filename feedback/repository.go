package feedback

import (
	"fmt"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/email"
)

// Repository Main Object.
type Repository struct {
	config      config.FeedbackConfig
	emailSender email.Sender
}

// CreateFeedbackRequest CreateFeedbackRequest.
type CreateFeedbackRequest struct {
	Name    string `json:"name"`
	Email   string `json:"email"`
	Message string `json:"message"`
	Captcha string `json:"captcha"`
	IP      string
}

// NewRepository constructor.
func NewRepository(
	config config.FeedbackConfig,
	emailSender email.Sender,
) *Repository {
	return &Repository{
		config:      config,
		emailSender: emailSender,
	}
}

func (s *Repository) Create(
	request CreateFeedbackRequest,
) error {
	message := fmt.Sprintf(
		"Имя: %s\nE-mail: %s\nСообщение:\n%s",
		request.Name,
		request.Email,
		request.Message,
	)

	return s.emailSender.Send(s.config.From, s.config.To, s.config.Subject, message, request.Email)
}

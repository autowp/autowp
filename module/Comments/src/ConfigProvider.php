<?php

declare(strict_types=1);

namespace Autowp\Comments;

use Laminas\Form\Form;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'forms'        => $this->getFormsConfig(),
            'tables'       => $this->getTablesConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                CommentsService::class => CommentsServiceFactory::class,
            ],
        ];
    }

    public function getFormsConfig(): array
    {
        return [
            'CommentForm' => [
                'type'         => Form::class,
                'attributes'   => [
                    'method' => 'post',
                    'legend' => 'comments/form-title',
                    'id'     => 'form-add-comment',
                ],
                'elements'     => [
                    [
                        'spec' => [
                            'type'       => 'Textarea',
                            'name'       => 'message',
                            'options'    => [
                                'label' => 'forums/topic/text',
                            ],
                            'attributes' => [
                                'cols'      => 80,
                                'rows'      => 5,
                                'maxlength' => CommentsService::MAX_MESSAGE_LENGTH,
                            ],
                        ],
                    ],
                    [
                        'spec' => [
                            'type'    => 'Checkbox',
                            'name'    => 'moderator_attention',
                            'options' => [
                                'label' => 'comments/it-requires-attention-of-moderators',
                            ],
                        ],
                    ],
                    [
                        'spec' => [
                            'type' => 'Hidden',
                            'name' => 'parent_id',
                        ],
                    ],
                    [
                        'spec' => [
                            'type' => 'Hidden',
                            'name' => 'resolve',
                        ],
                    ],
                ],
                'input_filter' => [
                    'message' => [
                        'required'   => true,
                        'filters'    => [
                            ['name' => 'StringTrim'],
                        ],
                        'validators' => [
                            [
                                'name'    => 'StringLength',
                                'options' => [
                                    'min' => 0,
                                    'max' => CommentsService::MAX_MESSAGE_LENGTH,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'comment_message'         => [],
            'comment_vote'            => [],
            'comment_topic'           => [],
            'comment_topic_subscribe' => [],
            'comment_topic_view'      => [],
        ];
    }
}

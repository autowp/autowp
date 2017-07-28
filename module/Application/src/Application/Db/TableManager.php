<?php

namespace Application\Db;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\Feature\SequenceFeature;
use Zend\Db\TableGateway\TableGateway;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\Exception\ServiceNotFoundException;

class TableManager implements ServiceLocatorInterface
{
    /**
     * @var array
     */
    private $specs = [
        'acl_roles' => [], //TODO: rename to acl_role
        'acl_roles_parents' => [], //TODO: rename to acl_role_parent
        'acl_resources' => [], //TODO: rename to acl_resources
        'acl_resources_privileges' => [], //TODO: rename to acl_resource_privilege
        'acl_roles_privileges_allowed' => [], //TODO: rename to acl_role_privilege_allowed
        'acl_roles_privileges_denied' => [], //TODO: rename to acl_role_privilege_denied
        'articles' => [],
        'attrs_attributes' => [],
        'attrs_list_options' => [],
        'attrs_types' => [],
        'attrs_units' => [],
        'attrs_user_values' => [],
        'attrs_user_values_float' => [],
        'attrs_user_values_int' => [],
        'attrs_user_values_list' => [],
        'attrs_user_values_string' => [],
        'attrs_values' => [],
        'attrs_values_float' => [],
        'attrs_values_int' => [],
        'attrs_values_list' => [],
        'attrs_values_string' => [],
        'attrs_zone_attributes' => [],
        'attrs_zones' => [],
        'banned_ip' => [],
        'brand_alias' => [], //TODO: rename to item_alias
        'car_types'   => [], //TODO: rename to vehicle_type
        'car_types_parents' => [], //TODO: rename to vehicle_type_parent
        'comment_message' => [],
        'comment_vote' => [],
        'comment_topic' => [],
        'comment_topic_subscribe' => [],
        'comment_topic_view' => [],
        'contact' => [],
        'df_distance' => [],
        'df_hash' => [],
        'forums_themes' => [],
        'forums_topics' => [],
        'htmls' => [],
        'ip_monitoring4' => [],
        'ip_whitelist' => [],
        'item' => [],
        'item_language' => [],
        'item_parent' => [],
        'item_parent_cache' => [],
        'item_parent_language' => [],
        'item_point' => [],
        'links' => [],
        'log_events' => [],
        'log_events_articles' => [],
        'log_events_item' => [],
        'log_events_pictures' => [],
        'log_events_user' => [],
        'login_state' => [],
        'pages' => [], //TODO: rename to page
        'personal_messages' => [],
        'perspectives' => [],
        'perspectives_groups' => [],
        'perspectives_pages' => [],
        'pictures' => [], //TODO: rename to picture
        'pictures_moder_votes' => [],
        'picture_moder_vote_template' => [],
        'picture_view' => [],
        'picture_vote' => [],
        'picture_vote_summary' => [],
        'spec' => [],
        'user_account' => [],
        'user_item_subscribe' => [],
        'user_password_remind' => [],
        'user_remember' => [],
        'user_renames' => [],
        'users' => [], //TODO: rename to users
        'vehicle_vehicle_type' => [], //TODO: rename to item_vehicle_type
        'voting' => [],
        'voting_variant' => [],
        'voting_variant_vote' => [],
    ];

    /**
     * @var array
     */
    private $tables = [];

    /**
     * @var Adapter
     */
    private $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function build($name, array $options = null)
    {
        if (! isset($this->specs[$name])) {
            throw new ServiceNotFoundException(sprintf(
                'Unable to create service "%s"',
                $name
            ));
        }

        $spec = $this->specs[$name];

        $platform = $this->adapter->getPlatform();
        $platformName = $platform->getName();

        $features = [];
        if ($platformName == 'PostgreSQL') {
            if (isset($spec['sequences'])) {
                foreach ($spec['sequences'] as $field => $sequence) {
                    $features[] = new SequenceFeature($field, $sequence);
                }
            }
        }

        return new TableGateway($name, $this->adapter, $features);
    }

    public function get($id)
    {
        if (! isset($this->tables[$id])) {
            $this->tables[$id] = $this->build($id);
        }

        return $this->tables[$id];
    }

    public function has($id)
    {
        return isset($this->specs[$id]);
    }
}

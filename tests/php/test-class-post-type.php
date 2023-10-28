<?php
/**
 * Test Post Type.
 *
 * @package CustomizeSnapshots
 */

namespace CustomizeSnapshots;

/**
 * Class Test_Post_type
 */
class Test_Post_Type extends \WP_UnitTestCase {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * A valid UUID.
	 *
	 * @type string
	 */
	const UUID = '65aee1ff-af47-47df-9e14-9c69b3017cd3';

	/**
	 * Merge sample data
	 *
	 * @var array
	 */
	public $snapshot_merge_sample_data;

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	public $post_type_slug;

	/**
	 * Set up.
	 */
	function setUp() {
		parent::setUp();
		$GLOBALS['wp_customize'] = null; // WPCS: Global override ok.
		$this->plugin = get_plugin_instance();
		$this->post_type_slug = Post_Type::SLUG;
		$this->snapshot_merge_sample_data = array(
			'foo' => array(
				'value' => 'bar',
				'merge_conflict' => array(
					array(
						'uuid' => 'abc',
						'value' => array( 'baz' ),
					),
					array(
						'uuid' => 'pqr',
						'value' => '',
					),
					array(
						'uuid' => 'lmn',
						'value' => false,
					),
					array(
						'uuid' => 'xyz',
						'value' => 'bar',
					),
				),
				'selected_uuid' => 'xyz',
			),
		);
	}

	/**
	 * Get plugin instance accoding to WP version.
	 *
	 * @param Customize_Snapshot_Manager $manager Manager.
	 *
	 * @return Post_Type Post type object.
	 */
	public function get_new_post_type_instance( $manager ) {
		return new Post_Type( $manager );
	}

	/**
	 * Test register post type.
	 *
	 * @see Post_Type::init()
	 */
	public function test_init() {
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$this->plugin->customize_snapshot_manager->init();
		$post_type_obj->init();

		$this->assertEquals( 10, has_filter( 'post_link', array( $post_type_obj, 'filter_post_type_link' ) ) );
		$this->assertEquals( 10, has_action( 'add_meta_boxes_' . Post_Type::SLUG, array( $post_type_obj, 'setup_metaboxes' ) ) );
		$this->assertEquals( 99, has_action( 'admin_menu', array( $post_type_obj, 'add_admin_menu_item' ) ) );
		$this->assertEquals( 5, has_filter( 'map_meta_cap', array( $post_type_obj, 'remap_customize_meta_cap' ) ) );
		$this->assertEquals( 10, has_filter( 'bulk_actions-edit-' . Post_Type::SLUG, array( $post_type_obj, 'add_snapshot_bulk_actions' ) ) );
		$this->assertEquals( 10, has_filter( 'handle_bulk_actions-edit-' . Post_Type::SLUG, array( $post_type_obj, 'handle_snapshot_merge' ) ) );
	}

	/**
	 * Test common hooks
	 *
	 * @see Post_Type::hooks()
	 */
	public function test_hooks() {
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type_obj->hooks();

		$this->assertEquals( 10, has_filter( 'wp_revisions_to_keep', array( $post_type_obj, 'force_at_least_one_revision' ) ) );
		$this->assertEquals( 100, has_action( 'add_meta_boxes_' . $this->post_type_slug, array( $post_type_obj, 'remove_slug_metabox' ) ) );
		$this->assertEquals( 10, has_action( 'load-revision.php', array( $post_type_obj, 'suspend_kses_for_snapshot_revision_restore' ) ) );
		$this->assertEquals( 10, has_filter( 'get_the_excerpt', array( $post_type_obj, 'filter_snapshot_excerpt' ) ) );
		$this->assertEquals( 10, has_filter( 'post_row_actions', array( $post_type_obj, 'filter_post_row_actions' ) ) );
		$this->assertEquals( 10, has_filter( 'user_has_cap', array( $post_type_obj, 'filter_user_has_cap' ) ) );
		$this->assertEquals( 10, has_action( 'post_submitbox_minor_actions', array( $post_type_obj, 'hide_disabled_publishing_actions' ) ) );
		$this->assertEquals( 10, has_filter( 'content_save_pre', array( $post_type_obj, 'filter_out_settings_if_removed_in_metabox' ) ) );
		$this->assertEquals( 10, has_action( 'admin_print_scripts-revision.php', array( $post_type_obj, 'disable_revision_ui_for_published_posts' ) ) );
		$this->assertEquals( 10, has_action( 'admin_notices', array( $post_type_obj, 'admin_show_merge_error' ) ) );
		$this->assertEquals( 10, has_filter( 'display_post_states', array( $post_type_obj, 'display_post_states' ) ) );
		$this->assertEquals( 10, has_action( 'admin_notices', array( $post_type_obj, 'show_publish_error_admin_notice' ) ) );
		$this->assertEquals( 10, has_action( 'wp_ajax_snapshot_fork', array( $post_type_obj, 'handle_snapshot_fork' ) ) );
		$this->assertEquals( 10, has_action( 'admin_footer-post.php', array( $post_type_obj, 'snapshot_admin_script_template' ) ) );
		$this->assertEquals( 10, has_action( 'admin_notices', array( $post_type_obj, 'admin_show_merge_error' ) ) );
		$this->assertEquals( 11, has_filter( 'content_save_pre', array( $post_type_obj, 'filter_selected_conflict_setting' ) ) );
	}

	/**
	 * Test extend_changeset_post_type_object
	 *
	 * @covers \CustomizeSnapshots\Post_Type::extend_changeset_post_type_object()
	 */
	public function test_extend_changeset_post_type_object() {
		global $_wp_post_type_features;
		$post_type_obj = get_post_type_object( Post_Type::SLUG );
		$this->assertArrayHasKey( 'revisions', $_wp_post_type_features[ Post_Type::SLUG ] );
		$this->assertTrue( $post_type_obj->show_ui );
		$this->assertTrue( $post_type_obj->show_in_menu );
		$this->assertEquals( 'post.php?post=%d', $post_type_obj->_edit_link );
		$this->assertEquals( 'customize_publish', $post_type_obj->cap->publish_posts );
		$caps = (array) $post_type_obj->cap;
		foreach ( $caps as $key => $value ) {
			if ( in_array( $key, array( 'read', 'publish_posts' ), true ) ) {
				continue;
			} else {
				$this->assertTrue( 0 < strpos( $value, Post_Type::SLUG ) );
			}
		}
		$this->assertFalse( $post_type_obj->show_in_customizer );
		$this->assertInstanceOf( __NAMESPACE__ . '\\Post_Type', $post_type_obj->customize_snapshot_post_type_obj );
		$this->assertTrue( $post_type_obj->show_in_rest );
		$this->assertEquals( 'customize_changesets', $post_type_obj->rest_base );
		$this->assertEquals( __NAMESPACE__ . '\\Snapshot_REST_API_Controller', $post_type_obj->rest_controller_class );
	}

	/**
	 * Test forcing at least one revision.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::force_at_least_one_revision
	 */
	public function test_force_at_least_one_revision() {
		add_filter( 'wp_revisions_to_keep', '__return_zero', 1 );
		$post_type = get_plugin_instance()->customize_snapshot_manager->post_type;
		$post_type->init();

		$title = 'Revisions Disabled';
		$data = array(
			'blogname' => array(
				'value' => $title,
			),
		);
		$post_id = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'data' => $data,
			'status' => 'draft',
		) );
		wp_publish_post( $post_id );
		$snapshot_post = get_post( $post_id );
		$content = $post_type->get_post_content( $snapshot_post );
		$this->assertEquals( 'publish', $snapshot_post->post_status );
		$this->assertEquals( $data, $content );
		$this->assertEquals( $title, get_bloginfo( 'name' ) );
	}

	/**
	 * Test add_admin_menu_item.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::add_admin_menu_item()
	 */
	public function test_add_admin_menu_item() {
		global $submenu, $menu;
		$menu = $submenu = array(); // WPCS: global override ok.
		$admin_user_id = $this->factory()->user->create( array(
			'role' => 'administrator',
		) );
		wp_set_current_user( $admin_user_id );
		$post_type_obj = new Post_Type( $this->plugin->customize_snapshot_manager );
		$post_type_obj->add_admin_menu_item();
		$menu_slug = 'edit.php?post_type=' . Post_Type::SLUG;

		$customize_url = add_query_arg( 'return', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'customize.php' );
		$this->assertArrayHasKey( $customize_url, $submenu );
		$this->assertEquals( $menu_slug, $submenu[ $customize_url ][1][2] );

		// Check with user with customize access.
		$submenu = $menu = array(); // WPCS: global override ok.
		add_filter( 'user_has_cap', array( $this, 'hack_user_can' ), 10, 3 );
		$editor_user_id = $this->factory()->user->create( array(
			'role' => 'editor',
		) );
		wp_set_current_user( $editor_user_id );
		$post_type_obj->add_admin_menu_item();
		$this->assertArrayHasKey( $customize_url, $submenu );
		$this->assertEquals( $menu_slug, $submenu[ $customize_url ][1][2] );
		remove_filter( 'user_has_cap', array( $this, 'hack_user_can' ), 10 );
	}

	/**
	 * Allow customize caps to all users for testing.
	 *
	 * @see test_menu_for_customize_cap.
	 * @param array $allcaps all caps.
	 * @param array $caps caps.
	 * @param array $args arg for current_user_can.
	 *
	 * @return array
	 */
	public function hack_user_can( $allcaps, $caps, $args ) {
		if ( 'customize' === $args[0] ) {
			$allcaps = array_merge( $allcaps, array_fill_keys( $caps, true ) );
		}

		return $allcaps;
	}

	/**
	 * Test filter_post_type_link.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::filter_post_type_link()
	 */
	function test_filter_post_type_link() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array(
				'blogname' => array( 'value' => 'Hello' ),
			),
		) );
		$this->assertContains(
			Post_Type::FRONT_UUID_PARAM_NAME . '=' . self::UUID,
			$post_type->filter_post_type_link( '', get_post( $post_id ) )
		);

		remove_all_filters( 'post_type_link' );
		$post_type->init();
		$this->assertContains( Post_Type::FRONT_UUID_PARAM_NAME . '=' . self::UUID, get_permalink( $post_id ) );
	}

	/**
	 * Suspend kses which runs on content_save_pre and can corrupt JSON in post_content.
	 *
	 * @see Post_Type::suspend_kses()
	 * @see Post_Type::restore_kses()
	 */
	function test_suspend_restore_kses() {
		if ( ! has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			kses_init_filters();
		}

		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->suspend_kses();
		$this->assertFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
		$post_type->restore_kses();
		$this->assertEquals( 10, has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );

		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		$post_type->suspend_kses();
		$post_type->restore_kses();
		$this->assertFalse( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
	}

	/**
	 * Test adding and removing the metabox.
	 *
	 * @see Post_Type::setup_metaboxes()
	 * @see Post_Type::remove_metaboxes()
	 */
	public function test_setup_metaboxes() {
		set_current_screen( $this->post_type_slug );
		global $wp_meta_boxes;
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();

		$post_id = $this->factory()->post->create( array(
			'post_type' => $this->post_type_slug,
			'post_status' => 'draft',
		) );

		$wp_meta_boxes = array(); // WPCS: global override ok.
		$metabox_id = $this->post_type_slug;
		$this->assertFalse( ! empty( $wp_meta_boxes[ $this->post_type_slug ]['normal']['high'][ $metabox_id ] ) );
		do_action( 'add_meta_boxes_' . $this->post_type_slug, $post_id );
		$this->assertTrue( ! empty( $wp_meta_boxes[ $this->post_type_slug ]['normal']['high'][ $metabox_id ] ) );

		$wp_meta_boxes = array(); // WPCS: global override ok.
		$metabox_id = $this->post_type_slug . '-fork';
		$this->assertFalse( ! empty( $wp_meta_boxes[ $this->post_type_slug ]['normal']['default'][ $metabox_id ] ) );
		do_action( 'add_meta_boxes_' . $this->post_type_slug, $post_id );
		$this->assertTrue( ! empty( $wp_meta_boxes[ $this->post_type_slug ]['normal']['default'][ $metabox_id ] ) );
	}

	/* Note: Code coverage ignored on Post_Type::remove_publish_metabox(). */

	/* Note: Code coverage ignored on Post_Type::suspend_kses_for_snapshot_revision_restore(). */

	/**
	 * Test include the setting IDs in the excerpt.
	 *
	 * @see Post_Type::filter_snapshot_excerpt()
	 */
	public function test_filter_snapshot_excerpt() {
		global $post;

		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array(
				'foo' => array( 'value' => 1 ),
				'bar' => array( 'value' => 2 ),
			),
		) );
		$this->assertInternalType( 'int', $post_id );

		$post = get_post( $post_id ); // WPCS: global override ok.
		$excerpt = get_the_excerpt();
		$this->assertContains( 'foo', $excerpt );
		$this->assertContains( 'bar', $excerpt );
	}

	/**
	 * Test add Customize link to quick edit links.
	 *
	 * @see Post_Type::filter_post_row_actions()
	 */
	public function test_filter_post_row_actions() {
		$admin_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$subscriber_user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );

		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();
		$data = array(
			'blogdescription' => array( 'value' => 'Another Snapshot Test' ),
		);

		// Bad post type.
		$this->assertEquals( array(), apply_filters( 'post_row_actions', array(), get_post( $this->factory()->post->create() ) ) );

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'draft',
		) );
		$post_type->set_customizer_state_query_vars( $post_id, array(
			'url' => home_url( 'hello-beautiful-world/' ),
		) );
		$original_actions = array(
			'inline hide-if-no-js' => '...',
			'edit' => '<a></a>',
		);

		wp_set_current_user( $admin_user_id );
		$filtered_actions = apply_filters( 'post_row_actions', $original_actions, get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $filtered_actions );
		$this->assertArrayHasKey( 'customize', $filtered_actions );
		$this->assertContains( 'hello-beautiful-world', $filtered_actions['customize'] );
		$this->assertArrayHasKey( 'front-view', $filtered_actions );
		$this->assertContains( 'hello-beautiful-world', $filtered_actions['front-view'] );

		wp_set_current_user( $subscriber_user_id );
		$filtered_actions = apply_filters( 'post_row_actions', $original_actions, get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $filtered_actions );
		$this->assertArrayNotHasKey( 'customize', $filtered_actions );
		$this->assertArrayNotHasKey( 'front-view', $filtered_actions );

		$post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
		) );

		wp_set_current_user( $admin_user_id );
		$filtered_actions = apply_filters( 'post_row_actions', $original_actions, get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $filtered_actions );
		$this->assertArrayNotHasKey( 'customize', $filtered_actions );
		$this->assertArrayNotHasKey( 'front-view', $filtered_actions );

		wp_set_current_user( $subscriber_user_id );
		$filtered_actions = apply_filters( 'post_row_actions', $original_actions, get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'inline hide-if-no-js', $filtered_actions );
		$this->assertArrayNotHasKey( 'customize', $filtered_actions );
		$this->assertArrayNotHasKey( 'front-view', $filtered_actions );

		$post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
		) );
		$filtered_actions = apply_filters( 'post_row_actions', $original_actions, get_post( $post_id ) );
		$this->assertContains( '<a href', $filtered_actions['edit'] );
	}

	/**
	 * Test rendering the metabox.
	 *
	 * @see Post_Type::render_data_metabox()
	 */
	public function test_render_data_metabox() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();
		$data = array(
			'knoa8sdhpasidg0apbdpahcas' => array(
				'value' => 'a09sad0as9hdgw22dutacs',
				'merged_changeset_uuids' => array( self::UUID ),
			),
			'n0nee8fa9s7ap9sdga9sdas9c' => array( 'value' => 'lasdbaosd81vvajgcaf22k' ),
		);
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'draft',
		) );

		ob_start();
		$post_type->render_data_metabox( get_post( $post_id ) );
		$metabox_content = ob_get_clean();

		$this->assertContains( 'UUID:', $metabox_content );
		$this->assertContains( 'button-secondary', $metabox_content );
		$this->assertContains( 'class="snapshot-fork', $metabox_content );
		$this->assertContains( 'snapshot-merged-list', $metabox_content );
		$this->assertContains( '<ul id="snapshot-settings">', $metabox_content );
		foreach ( $data as $setting_id => $setting_args ) {
			$this->assertContains( $setting_id, $metabox_content );
			$this->assertContains( $setting_args['value'], $metabox_content );
		}

		$data = array(
			'blogdescription' => array(
				'value' => 'Just Another Customize Snapshot Test',
			),
		);

		$post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
		) );

		add_filter( 'customize_snapshot_value_preview', array( $this, 'filter_customize_snapshot_value_preview' ), 10, 2 );
		ob_start();
		$post_type->render_data_metabox( get_post( $post_id ) );
		$metabox_content = ob_get_clean();
		remove_filter( 'customize_snapshot_value_preview', array( $this, 'filter_customize_snapshot_value_preview' ), 10 );

		$this->assertContains( 'UUID:', $metabox_content );
		$this->assertNotContains( 'customize_snapshot_uuid', $metabox_content );
		$this->assertContains( 'class="snapshot-fork', $metabox_content );
		$this->assertContains( '<ul id="snapshot-settings">', $metabox_content );
		foreach ( $data as $setting_id => $setting_args ) {
			$this->assertContains( $setting_id, $metabox_content );
			$this->assertContains( 'FILTERED:' . $setting_id, $metabox_content );
		}

		// Try switching theme.
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'draft',
			'theme' => 'bogus',
		) );
		ob_start();
		$post_type->render_data_metabox( get_post( $post_id ) );
		$metabox_content = ob_get_clean();
		$this->assertContains( 'changeset was made when a different theme was active', $metabox_content );

		$data = array(
			'knoa8sdhpasidg0apbdpahcas' => array(
				'value' => 'a09sad0as9hdgw22dutacs',
				'merged_changeset_uuids' => array( self::UUID ),
			),
			'foo' => array(
				'value' => '',
				'publish_error' => 'invalid_value',
			),
			'bar' => array(
				'value' => false,
				'publish_error' => 'unrecognized_setting',
			),
			'baz' => array(
				'value' => array( 'foo_key' => 'bar_value' ),
				'publish_error' => 'unexpected_value',
			),
			'qux' => array(
				'value' => null,
				'publish_error' => 'null_value',
			),
		);
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'draft',
		) );
		ob_start();
		$post_type->render_data_metabox( get_post( $post_id ) );
		$metabox_content = ob_get_clean();
		$this->assertContains( 'class="error-message"', $metabox_content );
		$this->assertContains( 'unexpected_value', $metabox_content );
		$this->assertContains( 'Publish error', $metabox_content );
		$this->assertContains( 'Missing value', $metabox_content );
		$this->assertContains( 'Unrecognized setting', $metabox_content );
		$this->assertContains( 'Invalid value', $metabox_content );
		$this->assertContains( '(Empty string)', $metabox_content );
		$this->assertContains( '<pre', $metabox_content );
	}

	/**
	 * Test render_forked_metabox.
	 *
	 * @see Post_Type::render_forked_metabox()
	 */
	function test_render_forked_metabox() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();
		$parent_post_id = $this->factory()->post->create( array(
			'post_status' => 'draft',
			'post_type' => $this->post_type_slug,
		) );
		$post_id = $this->factory()->post->create( array(
			'post_status' => 'draft',
			'post_type' => $this->post_type_slug,
			'post_parent' => $parent_post_id,
		) );
		$this->factory()->post->create( array(
			'post_status' => 'draft',
			'post_type' => $this->post_type_slug,
			'post_parent' => $post_id,
		) );
		ob_start();
		$post_type->render_forked_metabox( get_post( $post_id ) );
		$metabox_content = ob_get_clean();

		$this->assertContains( 'id="snapshot-fork-list"', $metabox_content );
		$this->assertContains( '<ul', $metabox_content );
		$this->assertContains( '<li', $metabox_content );
		$this->assertContains( '<h2', $metabox_content );
	}

	/**
	 * Filter customize_snapshot_value_preview.
	 *
	 * @param string $preview HTML preview.
	 * @param array  $context Context.
	 * @return string Filtered preview.
	 */
	function filter_customize_snapshot_value_preview( $preview, $context ) {
		$this->assertInternalType( 'string', $preview );
		$this->assertInternalType( 'array', $context );
		$this->assertArrayHasKey( 'value', $context );
		$this->assertArrayHasKey( 'setting_params', $context );
		$this->assertArrayHasKey( 'setting_id', $context );
		$this->assertArrayHasKey( 'post', $context );
		$preview = sprintf( 'FILTERED:%s', $context['setting_id'] );
		return $preview;
	}

	/**
	 * Find a snapshot post by UUID.
	 *
	 * @see Post_Type::find_post()
	 */
	public function test_find_post() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();
		$data = array(
			'foo' => array(
				'value' => 'bar',
			),
		);

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
		) );
		$this->assertEquals( $post_id, $post_type->find_post( self::UUID ) );

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'draft',
		) );
		$this->assertEquals( $post_id, $post_type->find_post( self::UUID ) );

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'pending',
		) );
		$this->assertEquals( $post_id, $post_type->find_post( self::UUID ) );

		$this->assertNull( $post_type->find_post( '0734b3f9-92bb-42a5-85ee-20e90cf09b52' ) );
	}

	/**
	 * Test getting the snapshot array out of the post_content.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::get_post_content()
	 * @expectedException \PHPUnit_Framework_Error_Warning
	 */
	public function test_get_post_content() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();

		// Bad post type.
		$page_post_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );
		$page = get_post( $page_post_id );
		$this->assertNull( $post_type->get_post_content( $page ) );

		// Regular post.
		$data = array(
			'foo' => array(
				'value' => 'bar',
				'publish_error' => 'unrecognized_setting',
			),
		);
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
		) );
		$snapshot_post = get_post( $post_id );
		unset( $data['foo']['publish_error'] );
		$this->assertEquals( $data, $post_type->get_post_content( $snapshot_post ) );

		// Revision.
		$revision_post_id = $this->factory()->post->create( array(
			'post_type' => 'revision',
			'post_parent' => $post_id,
			'post_content' => wp_json_encode( array(
				'foo' => array(
					'value' => 'baz',
				),
			) ),
		) );
		$revision_post = get_post( $revision_post_id );
		$content = $post_type->get_post_content( $revision_post );
		$this->assertEquals( 'baz', $content['foo']['value'] );

		// Bad post data.
		$bad_post_id = $this->factory()->post->create( array(
			'post_type' => $this->post_type_slug,
			'post_content' => 'BADJSON',
		) );
		$bad_post = get_post( $bad_post_id );
		$content = $post_type->get_post_content( $bad_post );
		$this->assertEquals( array(), $content );
	}

	/**
	 * Test persisting the data in the snapshot post content.
	 *
	 * @see Post_Type::save()
	 */
	public function test_save() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();

		// Error: missing_valid_uuid.
		$r = $post_type->save( array( 'id' => 'nouuid' ) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_valid_uuid', $r->get_error_code() );

		$r = $post_type->save( array( 'uuid' => 'baduuid' ) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_valid_uuid', $r->get_error_code() );

		$r = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => 'bad',
		) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_data', $r->get_error_code() );

		// Error: bad_setting_params.
		$r = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array( 'foo' => 'bar' ),
		) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'bad_setting_params', $r->get_error_code() );

		// Error: missing_value_param.
		$r = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array(
				'foo' => array( 'bar' => 'quux' ),
			),
		) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'missing_value_param', $r->get_error_code() );

		$data = array(
			'foo' => array(
				'value' => 'bar',
				'publish_error' => 'unrecognized_setting',
			),
		);

		// Error: bad_status.
		$r = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'noooo',
		) );
		$this->assertInstanceOf( 'WP_Error', $r );
		$this->assertEquals( 'bad_status', $r->get_error_code() );

		// Success without data.
		$r = $post_type->save( array( 'uuid' => self::UUID ) );
		$this->assertInternalType( 'int', $r );
		$this->assertEquals( array(), $post_type->get_post_content( get_post( $r ) ) );
		wp_delete_post( $r, true );

		// Success with data.
		$r = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
			'theme' => get_stylesheet(),
		) );
		$this->assertInternalType( 'int', $r );
		$expected = $data;
		unset( $expected['foo']['publish_error'] );
		$this->assertEquals( $expected, $post_type->get_post_content( get_post( $r ) ) );

		$this->assertEquals( get_stylesheet(), get_post_meta( $r, '_snapshot_theme', true ) );
		$this->assertEquals( $this->plugin->version, get_post_meta( $r, '_snapshot_version', true ) );

		// Success with author supplied.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
			'author' => $user_id,
		) );
		$this->assertEquals( $user_id, get_post( $post_id )->post_author );

		// Success with future date.
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => $data,
			'status' => 'publish',
			'date_gmt' => gmdate( 'Y-m-d H:i:s', time() + 24 * 3600 ),
		) );
		$this->assertEquals( 'future', get_post_status( $post_id ) );
	}

	/**
	 * Test that changes to options can be saved by publishing the changeset.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::start_pretending_customize_save_ajax_action()
	 * @covers \CustomizeSnapshots\Post_Type::finish_pretending_customize_save_ajax_action()
	 */
	function test_pretending_customize_save_ajax_action() {
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			$this->markTestSkipped( 'Only relevant to WordPress 4.7 and greater.' );
		}

		$_REQUEST['action'] = 'editpost';
		set_current_screen( 'edit' );
		$this->assertTrue( is_admin() );
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();

		$old_sidebars_widgets = get_option( 'sidebars_widgets' );
		$new_sidebars_widgets = $old_sidebars_widgets;
		$new_sidebar_1 = array_reverse( $new_sidebars_widgets['sidebar-1'] );

		$post_id = $this->factory()->post->create( array(
			'post_type' => 'customize_changeset',
			'post_status' => 'draft',
			'post_name' => wp_generate_uuid4(),
			'post_content' => wp_json_encode( array(
				'sidebars_widgets[sidebar-1]' => array(
					'value' => $new_sidebar_1,
				),
			) ),
		) );

		// Save the updated sidebar widgets into the options table.
		wp_publish_post( $post_id );

		// Make sure previewing filters are removed.
		remove_all_filters( 'option_sidebars_widgets' );
		remove_all_filters( 'default_option_sidebars_widgets' );

		// Ensure that the value has actually been written to the DB.
		$updated_sidebars_widgets = get_option( 'sidebars_widgets' );
		$this->assertEquals( $new_sidebar_1, $updated_sidebars_widgets['sidebar-1'] );
	}

	/**
	 * Test granting customize capability.
	 *
	 * @see Post_Type::filter_user_has_cap()
	 */
	function test_filter_user_has_cap() {
		remove_all_filters( 'user_has_cap' );

		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type->init();

		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array( 'foo' => array( 'value' => 'bar' ) ),
		) );

		$this->assertFalse( current_user_can( 'edit_post', $post_id ) );
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * Tests disable_revision_ui_for_published_posts.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::disable_revision_ui_for_published_posts()
	 */
	public function test_disable_revision_ui_for_published_posts() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array(),
		) );

		ob_start();
		$post_type->disable_revision_ui_for_published_posts( get_post( $post_id ) );
		$output = ob_get_clean();
		$this->assertEmpty( $output );

		$post_type->save( array(
			'uuid' => self::UUID,
			'status' => 'publish',
		) );
		ob_start();
		$GLOBALS['post'] = get_post( $post_id ); // WPCS: global override ok.
		$post_type->disable_revision_ui_for_published_posts( get_post( $post_id ) );
		$output = ob_get_clean();
		$this->assertNotEmpty( $output );
		$this->assertContains( 'restore-revision.button', $output );
	}

	/**
	 * Tests hide_disabled_publishing_actions.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::hide_disabled_publishing_actions()
	 */
	public function test_hide_disabled_publishing_actions() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_id = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array(),
		) );

		ob_start();
		$post_type->hide_disabled_publishing_actions( get_post( $post_id ) );
		$output = ob_get_clean();
		$this->assertEmpty( $output );

		$post_type->save( array(
			'uuid' => self::UUID,
			'status' => 'publish',
		) );
		ob_start();
		$post_type->hide_disabled_publishing_actions( get_post( $post_id ) );
		$output = ob_get_clean();
		$this->assertNotEmpty( $output );
		$this->assertContains( 'misc-pub-post-status', $output );
	}

	/**
	 * Tests add_snapshot_bulk_actions
	 *
	 * @covers CustomizeSnapshots\Post_Type::add_snapshot_bulk_actions()
	 */
	public function test_add_snapshot_bulk_actions() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$data = $post_type->add_snapshot_bulk_actions( array() );
		$this->assertArrayHasKey( 'merge_snapshot', $data );
	}

	/**
	 * Test handle_snapshot_bulk_actions
	 *
	 * @see Post_Type::handle_snapshot_merge()
	 */
	public function test_handle_snapshot_merge() {
		$ids = $this->factory()->post->create_many( 2 );
		$posts = array_map( 'get_post', $ids );
		$post_type_obj = $this
			->getMockBuilder( 'CustomizeSnapshots\Post_Type' )
			->setConstructorArgs( array( $this->plugin->customize_snapshot_manager ) )
			->setMethods( array( 'merge_snapshots' ) )
			->getMock();
		$post_type_obj
			->expects( $this->once() )
			->method( 'merge_snapshots' )
			->with( $posts )
			->will( $this->returnValue( null ) );
		$post_type_obj->handle_snapshot_merge( '', 'merge_snapshot', $ids );

		$input = 'http://example.com';
		$url = $post_type_obj->handle_snapshot_merge( $input, 'fishy_Action', array( 1, 2 ) );
		$this->assertEquals( 'http://example.com', $url );

		$url = $post_type_obj->handle_snapshot_merge( $input, 'merge_snapshot', array( 1 ) );
		$this->assertContains( 'merge-error=1', $url );
	}

	/**
	 * Test merge_snapshots
	 *
	 * @see Post_Type::merge_snapshots()
	 */
	public function test_merge_snapshots() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$date1 = gmdate( 'Y-m-d H:i:s' );
		$value_1 = array(
			'foo' => array(
				'value' => 'bar',
			),
			'qux' => array(
				'value' => 'same',
			),
			'quux' => array(
				'value' => 'foo val',
				'merged_changeset_uuids' => array(
					'a-uuid',
				),
			),
			'corge' => array(
				'value' => 'foo val 4',
				'merge_conflict' => array(
					array(
						'uuid' => 'c-uuid',
						'value' => 'foo val 3',
					),
					array(
						'uuid' => 'd-uuid',
						'value' => 'foo val 4',
					),
				),
			),
		);
		$post_id_1 = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'status' => 'draft',
			'data' => $value_1,
			'date_gmt' => $date1,
		) );
		$value_2 = array(
			'foo' => array(
				'value' => 'baz',
			),
			'baz' => array(
				'value' => 'zab',
			),
			'qux' => array(
				'value' => 'same',
			),
			'quux' => array(
				'value' => 'foo val',
				'merged_changeset_uuids' => array(
					'a-uuid',
					'b-uuid',
				),
			),
			'corge' => array(
				'value' => 'foo val 5',
				'merge_conflict' => array(
					array(
						'uuid' => 'e-uuid',
						'value' => 'foo val 5',
					),
				),
			),
		);
		$date2 = gmdate( 'Y-m-d H:i:s', ( time() + 60 ) );
		$post_id_2 = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'status' => 'draft',
			'data' => $value_2,
			'date_gmt' => $date2,
		) );
		$post_1 = get_post( $post_id_1 );
		$post_2 = get_post( $post_id_2 );

		$merged_post_id = $post_type->merge_snapshots( array(
			$post_1,
			$post_2,
		) );
		$post_1_uuid = $post_1->post_name;
		$post_2_uuid = $post_2->post_name;

		$expected = array(
			'foo' => array(
				'value' => 'baz',
				'merge_conflict' => array(
					array(
						'uuid' => get_post( $post_id_1 )->post_name,
						'value' => 'bar',
					),
					array(
						'uuid' => $post_2->post_name,
						'value' => 'baz',
					),
				),
				'selected_uuid' => $post_2_uuid,
			),
			'qux' => array(
				'value' => 'same',
				'merged_changeset_uuids' => array(
					$post_1_uuid,
					$post_2_uuid,
				),
			),
			'quux' => array(
				'value' => 'foo val',
				'merged_changeset_uuids' => array(
					'a-uuid',
					'b-uuid',
				),
			),
			'corge' => array(
				'value' => 'foo val 5',
				'merge_conflict' => array(
					array(
						'uuid' => 'c-uuid',
						'value' => 'foo val 3',
					),
					array(
						'uuid' => 'd-uuid',
						'value' => 'foo val 4',
					),
					array(
						'uuid' => 'e-uuid',
						'value' => 'foo val 5',
					),
				),
				'selected_uuid' => 'e-uuid',
			),
			'baz' => array(
				'value' => 'zab',
				'merged_changeset_uuids' => array(
					$post_2_uuid,
				),
			),
		);
		$merged_post = get_post( $merged_post_id );
		$this->assertSame( $expected, $post_type->get_post_content( $merged_post ) );

		$date3 = gmdate( 'Y-m-d H:i:s', ( time() + 120 ) );
		$value_3 = array(
			'baz' => array(
				'value' => 'z',
			),
		);
		$post_3 = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'status' => 'draft',
			'data' => $value_3,
			'date_gmt' => $date3,
		) );
		$post_3 = get_post( $post_3 );
		$merge_result_post = get_post( $post_type->merge_snapshots( array( $post_1, $post_2, $post_3 ) ) );
		$baz_confict_expected = array(
			'value'          => 'z',
			'merge_conflict' => array(
				array(
					'uuid'  => $post_2_uuid,
					'value' => 'zab',
				),
				array(
					'uuid'  => $post_3->post_name,
					'value' => 'z',
				),
			),
			'selected_uuid' => $post_3->post_name,
		);
		$result_content = $post_type->get_post_content( $merge_result_post );
		$this->assertArrayHasKey( 'baz', $result_content );
		$this->assertSame( $baz_confict_expected, $result_content['baz'] );
	}

	/**
	 * Test admin_show_merge_error
	 *
	 * @covers CustomizeSnapshots\Post_Type::admin_show_merge_error()
	 */
	public function test_admin_show_merge_error() {
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		ob_start();
		$post_type_obj->admin_show_merge_error();
		$notice_content = ob_get_clean();
		$this->assertEmpty( $notice_content );
		ob_start();
		$_POST['merge-error'] = $_REQUEST['merge-error'] = $_GET['merge-error'] = 1;
		$post_type_obj->admin_show_merge_error();
		$notice_content = ob_get_clean();
		$this->assertContains( 'notice-error', $notice_content );
		$_POST['merge-error'] = $_REQUEST['merge-error'] = $_GET['merge-error'] = 5;
		ob_start();
		$post_type_obj->admin_show_merge_error();
		$notice_content = ob_get_clean();
		$this->assertEmpty( $notice_content );
	}

	/**
	 * Test filter_out_settings_if_removed_in_metabox.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::filter_out_settings_if_removed_in_metabox()
	 */
	public function test_filter_out_settings_if_removed_in_metabox() {
		global $post;
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type_obj->init();
		$admin_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );
		$post_id = $post_type_obj->save( array(
			'uuid' => self::UUID,
			'data' => array(
				'foo' => array(
					'value' => 'foo_value',
				),
				'bar' => array(
					'value' => 'bar_value',
				),
			),
			'status' => 'draft',
		) );
		$post = get_post( $post_id ); // WPCS: override ok.
		$nonce_key = $this->post_type_slug;
		$key_for_settings = $this->post_type_slug . '_remove_settings';
		$_REQUEST[ $nonce_key ] = $_POST[ $nonce_key ] = wp_create_nonce( $this->post_type_slug . '_settings' );
		$_REQUEST[ $key_for_settings ] = $_POST[ $key_for_settings ] = array( 'foo' );
		$content = $post_type_obj->filter_out_settings_if_removed_in_metabox( wp_slash( $post->post_content ) );
		$data = json_decode( wp_unslash( $content ), true );
		$this->assertArrayNotHasKey( 'foo', $data );
	}

	/**
	 * Test resolve_conflict_markup.
	 *
	 * @covers CustomizeSnapshots\Post_Type::resolve_conflict_markup()
	 */
	public function test_resolve_conflict_markup() {
		$post_type_obj = new Post_Type( $this->plugin->customize_snapshot_manager );
		ob_start();
		$post_id = $post_type_obj->save( array(
			'uuid' => self::UUID,
			'data' => $this->snapshot_merge_sample_data,
			'status' => 'draft',
		) );
		$p = get_post( $post_id );
		$post_type_obj->resolve_conflict_markup( 'foo', $this->snapshot_merge_sample_data['foo'], $this->snapshot_merge_sample_data, $p );
		$resolve_conflict_markup = ob_get_clean();
		$this->assertContains( 'input', $resolve_conflict_markup );
		$this->assertContains( 'baz', $resolve_conflict_markup );
		$this->assertContains( 'bar', $resolve_conflict_markup );
		$this->assertContains( 'name="' . Post_Type::SLUG . '_resolve_conflict_uuid[0]"', $resolve_conflict_markup );
		$this->assertContains( '<details', $resolve_conflict_markup );
		$this->assertContains( '<pre', $resolve_conflict_markup );
	}

	/**
	 * Test filter_selected_conflict_setting.
	 *
	 * @covers CustomizeSnapshots\Post_Type::filter_selected_conflict_setting()
	 */
	public function test_filter_selected_conflict_setting() {
		global $post;
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$post_type_obj->init();
		wp_set_current_user( $admin_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$post_id = $post_type_obj->save( array(
			'uuid' => self::UUID,
			'data' => $this->snapshot_merge_sample_data,
			'status' => 'draft',
		) );
		$post = get_post( $post_id );
		$nonce_key = $this->post_type_slug;
		$resolve_setting_key = $this->post_type_slug . '_resolve_conflict_uuid';
		$_REQUEST[ $nonce_key ] = $_POST[ $nonce_key ] = wp_create_nonce( $this->post_type_slug . '_settings' );
		$_REQUEST[ $resolve_setting_key ] = $_POST[ $resolve_setting_key ] = array(
			wp_json_encode( array(
				'setting_id' => 'foo',
				'uuid' => 'abc',
			) ),
		);
		$content = $post_type_obj->filter_selected_conflict_setting( wp_slash( $post->post_content ) );
		$data = json_decode( wp_unslash( $content ), true );
		$this->assertSame( array(
			'baz',
		), $data['foo']['value'] );
		$this->assertEquals( 'abc', $data['foo']['selected_uuid'] );
	}

	/**
	 * Test get_snapshot_merged_uuid.
	 *
	 * @covers CustomizeSnapshots\Post_Type::_get_merged_changesets()
	 */
	public function test_get_snapshot_merged_uuid() {
		$post_type_obj = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$uuid = $post_type_obj->_get_merged_changesets( array(
			'foo' => array( 'merged_changeset_uuids' => array( 'uuid-1' ) ),
			'bar' => array(
				'merge_conflict' => array(
					array( 'uuid' => 'uuid-2' ),
				),
			),
		) );
		$this->assertSame( array( 'uuid-1', 'uuid-2' ), $uuid );
	}

	/**
	 * Test remap_customize_meta_cap
	 *
	 * @covers \CustomizeSnapshots\Post_Type::remap_customize_meta_cap()
	 */
	public function test_remap_customize_meta_cap() {
		$this->markTestIncomplete();
	}

	/**
	 * Tests display_post_states.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::display_post_states()
	 */
	public function test_display_post_states() {
		$post_type_obj = new Post_Type( $this->plugin->customize_snapshot_manager );

		$post_id = $post_type_obj->save( array(
			'uuid' => self::UUID,
			'data' => array( 'foo' => array( 'value' => 'bar' ) ),
		) );
		$states = $post_type_obj->display_post_states( array(), get_post( $post_id ) );
		$this->assertArrayNotHasKey( 'snapshot_error', $states );

		update_post_meta( $post_id, 'snapshot_error_on_publish', true );
		$states = $post_type_obj->display_post_states( array(), get_post( $post_id ) );
		$this->assertArrayHasKey( 'snapshot_error', $states );

		$fork_post = get_post( $post_id );
		$fork_post->post_parent = 100;
		$states = $post_type_obj->display_post_states( array(), $fork_post );
		$this->assertArrayHasKey( 'forked', $states );
	}

	/**
	 * Tests show_publish_error_admin_notice.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::show_publish_error_admin_notice()
	 */
	public function test_show_publish_error_admin_notice() {
		global $current_screen, $post;
		wp_set_current_user( $this->factory()->user->create( array(
			'role' => 'administrator',
		) ) );
		$post_type_obj = new Post_Type( $this->plugin->customize_snapshot_manager );
		$post_type_obj->init();
		$post_id = $post_type_obj->save( array(
			'uuid' => self::UUID,
			'data' => array(),
		) );

		ob_start();
		$post_type_obj->show_publish_error_admin_notice();
		$this->assertEmpty( ob_get_clean() );

		$current_screen = \WP_Screen::get( 'customize_snapshot' ); // WPCS: Override ok.
		$current_screen->id = 'customize_snapshot';
		$current_screen->base = 'edit';
		ob_start();
		$post_type_obj->show_publish_error_admin_notice();
		$this->assertEmpty( ob_get_clean() );

		$current_screen->base = 'post';
		ob_start();
		$post_type_obj->show_publish_error_admin_notice();
		$this->assertEmpty( ob_get_clean() );

		$_REQUEST['snapshot_error_on_publish'] = '1';
		wp_update_post( array(
			'ID' => $post_id,
			'post_status' => 'pending',
		) );
		$post = get_post( $post_id ); // WPCS: override ok.
		ob_start();
		$post_type_obj->show_publish_error_admin_notice();

		$this->markTestIncomplete();
		$this->assertContains( 'notice-error', ob_get_clean() ); // @todo Test failing.
	}

	/**
	 * Test get_conflicts_setting
	 *
	 * @covers \CustomizeSnapshots\Post_Type::get_conflicted_settings()
	 */
	public function test_get_conflicts_setting() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		$tomorrow = date( 'Y-m-d H:i:s', time() + 86400 );

		$post_id1 = $post_type->save( array(
			'uuid' => self::UUID,
			'data' => array( 'foo' => array( 'value' => 'bar' ) ),
			'status' => 'future',
			'date_gmt' => $tomorrow,
		) );
		$post1 = get_post( $post_id1 );

		$post_id2 = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'data' => array( 'foo' => array( 'value' => 'baz' ) ),
			'status' => 'future',
			'date_gmt' => $tomorrow,
		) );
		$post2 = get_post( $post_id2 );

		$conflict = $post_type->get_conflicted_settings( get_post( $post_id2 ) );
		$match_first = array(
			'id' => (string) $post_id1,
			'value' => 'bar',
			'name' => $post1->post_name === $post1->post_title ? '' : $post1->post_title,
			'uuid' => $post1->post_name,
			'edit_link' => get_edit_post_link( $post_id1, 'raw' ),
			'setting_param' => array(
				'value' => 'bar',
			),
		);
		$this->assertSame( array( 'foo' => array( $match_first ) ), $conflict );
		$conflict = $post_type->get_conflicted_settings( null, array( 'foo' ) );
		$match = array(
			'foo' => array(
				$match_first,
				array(
					'id' => (string) $post_id2,
					'value' => 'baz',
					'name' => $post2->post_name === $post2->post_title ? '' : $post2->post_title,
					'uuid' => $post2->post_name,
					'edit_link' => get_edit_post_link( $post_id2, 'raw' ),
					'setting_param' => array(
						'value' => 'baz',
					),
				),
			),
		);
		$this->assertSame( $match, $conflict );
	}

	/**
	 * Test snapshot_admin_script_template.
	 */
	function test_snapshot_admin_script_template() {
		$post_type = $this->get_new_post_type_instance( $this->plugin->customize_snapshot_manager );
		ob_start();
		global $post;
		$id = $post_type->save( array(
			'uuid' => self::UUID,
			'status' => 'draft',
		) );
		$post = get_post( $id );
		$post_type->snapshot_admin_script_template();
		$contains = ob_get_clean();
		$this->assertContains( 'id="tmpl-snapshot-fork-item"', $contains );
	}
}

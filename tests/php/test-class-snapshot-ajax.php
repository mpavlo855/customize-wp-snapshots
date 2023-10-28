<?php
/**
 * Test Test_Snapshot_Ajax.
 *
 * @package CustomizeSnapshots
 */

namespace CustomizeSnapshots;

/**
 * Class Test_Snapshot_Ajax
 */
class Test_Snapshot_Ajax extends \WP_Ajax_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Setup.
	 */
	public function setUp() {
		$this->plugin = get_plugin_instance();
		parent::setUp();
	}

	/**
	 * Helper to keep it DRY
	 *
	 * @param string $action Action.
	 */
	protected function make_ajax_call( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		}
	}

	/**
	 * Set input vars.
	 *
	 * @param array  $vars   Input vars.
	 * @param string $method Request method.
	 */
	public function set_input_vars( array $vars = array(), $method = 'POST' ) {
		$_GET = $_POST = $_REQUEST = wp_slash( $vars );
		$_SERVER['REQUEST_METHOD'] = $method;
	}

	/**
	 * Test ajax handle conflict snapshots request
	 *
	 * @see Customize_Snapshot_Manager::handle_conflicts_snapshot_request()
	 */
	public function test_ajax_handle_conflicts_snapshot_request() {
		unset( $GLOBALS['wp_customize'] );
		$tomorrow = date( 'Y-m-d H:i:s', time() + 86400 );
		remove_all_actions( 'wp_ajax_customize_snapshot_conflict_check' );
		$this->set_current_user( 'administrator' );
		$uuid = wp_generate_uuid4();
		$this->set_input_vars( array(
			'action'                             => 'customize_snapshot_conflict_check',
			'nonce'                              => wp_create_nonce( Post_Type::SLUG . '_conflict' ),
			Post_Type::CUSTOMIZE_UUID_PARAM_NAME => $uuid,
			'setting_ids'                        => array( 'foo' ),
		) );

		$plugin = new Plugin();
		$plugin->init();
		$post_type = $this->plugin->customize_snapshot_manager->post_type;
		$post_type->save( array(
			'uuid'     => $uuid,
			'data'     => array( 'foo' => array( 'value' => 'bar' ) ),
			'status'   => 'future',
			'date_gmt' => $tomorrow,
		) );
		$post_id       = $post_type->save( array(
			'uuid'     => wp_generate_uuid4(),
			'data'     => array( 'foo' => array( 'value' => 'baz' ) ),
			'status'   => 'future',
			'date_gmt' => $tomorrow,
		) );
		$snapshot_post = get_post( $post_id );
		$this->make_ajax_call( 'customize_snapshot_conflict_check' );
		$response = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $response['data']['foo'][0] );
		unset( $response['data']['foo'][0] );
		$this->assertSame( array(
			'success' => true,
			'data'    => array(
				'foo' => array(
					1 => array(
						'id'        => (string) $snapshot_post->ID,
						'value'     => $post_type->get_printable_setting_value( 'baz', 'foo' ),
						'name'      => $snapshot_post->post_title === $snapshot_post->post_name ? '' : $snapshot_post->post_title,
						'uuid'      => $snapshot_post->post_name,
						'edit_link' => get_edit_post_link( $snapshot_post, 'raw' ),
					),
				),
			),
		), $response );
	}

	/**
	 * Set current user.
	 *
	 * @param string $role Role.
	 * @return int User Id.
	 */
	public function set_current_user( $role ) {
		$user_id = $this->factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Test snapshot fork ajax request.
	 *
	 * @covers \CustomizeSnapshots\Post_Type::handle_snapshot_fork()
	 */
	function test_handle_snapshot_fork() {
		unset( $GLOBALS['wp_customize'] );
		$this->set_current_user( 'administrator' );
		$post_type = new Post_Type( $this->plugin->customize_snapshot_manager );
		$data = array(
			'foo' => array(
				'value' => 'bar',
			),
		);
		$post_id = $post_type->save( array(
			'uuid' => wp_generate_uuid4(),
			'data' => $data,
			'status' => 'draft',
		) );
		$post_vars = array(
			'action' => 'snapshot_fork',
			'nonce' => wp_create_nonce( 'snapshot-fork' ),
			'post_id' => $post_id,
		);
		$post_vars_slash = wp_slash( $post_vars );
		$_GET = $post_vars_slash;
		$_POST = $post_vars_slash;
		$_REQUEST = $post_vars_slash;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->make_ajax_call( 'snapshot_fork' );
		$response = json_decode( $this->_last_response, true );
		$this->assertArrayHasKey( 'data', $response );
		$response = $response['data'];
		$this->assertEquals( $post_id, $response['post_parent'] );
		$this->assertEquals( $data, $post_type->get_post_content( get_post( $response['ID'] ) ) );
		$post = get_post( $post_id, ARRAY_A );
		$fork_post = get_post( $response['ID'], ARRAY_A );
		$key = array(
			'ID',
			'post_title',
			'post_parent',
			'post_name',
			'guid',
			'ancestors',
			'tags_input',
			'post_category',
			'post_date',
			'post_date_gmt',
			'post_modified_gmt',
			'post_modified',
		);
		foreach ( $key as $item ) {
			unset( $fork_post[ $item ], $post[ $item ] );
		}
		$this->assertSame( $fork_post, $post );
		$this->assertEquals( get_post_meta( $post_id ), get_post_meta( $response['ID'] ) );
	}

}

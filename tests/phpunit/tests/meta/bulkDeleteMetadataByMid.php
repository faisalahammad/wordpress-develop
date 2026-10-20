<?php

/**
 * @group meta
 *
 * @covers ::bulk_delete_metadata_by_mid
 */
class Tests_Meta_BulkDeleteMetadataByMid extends WP_UnitTestCase {

	/**
	 * @ticket 37702
	 */
	public function test_should_delete_given_meta_ids_only() {
		$mid1 = add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'post', 12345, 'baz', 'qux' );
		$mid3 = add_metadata( 'post', 54321, 'foo', 'bar' );
		$keep = add_metadata( 'post', 12345, 'keep', 'me' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2, $mid3 ) ) );

		$this->assertFalse( get_metadata_by_mid( 'post', $mid1 ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid2 ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid3 ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $keep ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_delete_user_meta() {
		$mid1 = add_metadata( 'user', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'user', 12345, 'baz', 'qux' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'user', array( $mid1, $mid2 ) ) );

		$this->assertFalse( get_metadata_by_mid( 'user', $mid1 ) );
		$this->assertFalse( get_metadata_by_mid( 'user', $mid2 ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @dataProvider data_should_return_false_for_invalid_input
	 *
	 * @param string $meta_type Meta type.
	 * @param mixed  $meta_ids  Meta IDs.
	 */
	public function test_should_return_false_for_invalid_input( $meta_type, $meta_ids ) {
		$this->assertFalse( bulk_delete_metadata_by_mid( $meta_type, $meta_ids ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_return_false_for_invalid_input() {
		return array(
			'empty meta type'      => array( '', array( 1 ) ),
			'unknown meta type'    => array( 'foo', array( 1 ) ),
			'not an array'         => array( 'post', 1 ),
			'empty array'          => array( 'post', array() ),
			'invalid ids'          => array( 'post', array( 0, -1, 1.5, 'abc', null ) ),
			'non-existent meta id' => array( 'post', array( PHP_INT_MAX ) ),
		);
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_skip_invalid_ids_and_delete_valid_ones() {
		$mid = add_metadata( 'post', 12345, 'foo', 'bar' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( 0, 'abc', (string) $mid, $mid ) ) );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_use_one_select_and_one_delete_query() {
		global $wpdb;

		$mids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$mids[] = add_metadata( 'post', 12345, "foo_$i", 'bar' );
		}

		$num_queries = $wpdb->num_queries;
		bulk_delete_metadata_by_mid( 'post', $mids );

		$this->assertSame( 2, $wpdb->num_queries - $num_queries );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_clear_object_meta_cache() {
		add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid  = add_metadata( 'post', 54321, 'foo', 'bar' );
		$mids = array( $mid, add_metadata( 'post', 12345, 'baz', 'qux' ) );

		// Prime the caches.
		get_metadata( 'post', 12345 );
		get_metadata( 'post', 54321 );

		bulk_delete_metadata_by_mid( 'post', $mids );

		$this->assertSame( array( 'foo' => array( 'bar' ) ), get_metadata( 'post', 12345 ) );
		$this->assertSame( array(), get_metadata( 'post', 54321 ) );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_fire_actions_for_each_entry() {
		$mid1 = add_metadata( 'post', 12345, 'foo', array( 'bar' ) );
		$mid2 = add_metadata( 'post', 54321, 'baz', 'qux' );

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_post_meta', array( $delete, 'action' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $deleted, 'action' ), 10, 4 );

		bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2 ) );

		$expected = array(
			array( array( $mid1 ), 12345, 'foo', array( 'bar' ) ),
			array( array( $mid2 ), 54321, 'baz', 'qux' ),
		);

		$this->assertSame( $expected, $delete->get_args(), 'delete_post_meta args do not match.' );
		$this->assertSame( $expected, $deleted->get_args(), 'deleted_post_meta args do not match.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_fire_old_style_actions_for_each_entry() {
		$mid1 = add_metadata( 'comment', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'comment', 12345, 'baz', 'qux' );

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_commentmeta', array( $delete, 'action' ) );
		add_action( 'deleted_commentmeta', array( $deleted, 'action' ) );

		bulk_delete_metadata_by_mid( 'comment', array( $mid1, $mid2 ) );

		$expected = array( array( $mid1 ), array( $mid2 ) );

		$this->assertSame( $expected, $delete->get_args(), 'delete_commentmeta args do not match.' );
		$this->assertSame( $expected, $deleted->get_args(), 'deleted_commentmeta args do not match.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_short_circuit_filter_should_run_for_each_id() {
		$mid1 = add_metadata( 'post', 12345, 'foo', 'bar' );
		$mid2 = add_metadata( 'post', 12345, 'baz', 'qux' );

		$filter = new MockAction();
		add_filter( 'delete_post_metadata_by_mid', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'delete_post_metadata_by_mid',
			static function ( $check, $meta_id ) use ( $mid1 ) {
				return $mid1 === $meta_id ? true : $check;
			},
			10,
			2
		);

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid1, $mid2 ) ) );

		$this->assertSame( array( array( null, $mid1 ), array( null, $mid2 ) ), $filter->get_args(), 'Filter args do not match.' );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid1 ), 'Short-circuited meta should not be deleted.' );
		$this->assertFalse( get_metadata_by_mid( 'post', $mid2 ), 'Other meta should be deleted.' );
	}

	/**
	 * @ticket 37702
	 */
	public function test_should_return_true_when_all_ids_are_short_circuited() {
		$mid = add_metadata( 'post', 12345, 'foo', 'bar' );

		add_filter( 'delete_post_metadata_by_mid', '__return_true' );

		$this->assertTrue( bulk_delete_metadata_by_mid( 'post', array( $mid ) ) );
		$this->assertNotFalse( get_metadata_by_mid( 'post', $mid ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_post
	 */
	public function test_wp_delete_post_should_delete_all_post_meta() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, 'foo', 'bar' );
		add_post_meta( $post_id, 'baz', 'qux' );

		wp_delete_post( $post_id, true );

		$this->assertSame( array(), get_post_meta( $post_id ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_comment
	 */
	public function test_wp_delete_comment_should_delete_all_comment_meta() {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, 'foo', 'bar' );
		add_comment_meta( $comment_id, 'baz', 'qux' );

		wp_delete_comment( $comment_id, true );

		$this->assertSame( array(), get_comment_meta( $comment_id ) );
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_post
	 * @covers ::wp_delete_attachment
	 * @covers ::wp_delete_comment
	 * @covers ::wp_delete_user
	 * @covers ::wpmu_delete_user
	 *
	 * @dataProvider data_object_types_deleted_with_their_meta
	 *
	 * @param string $object_type Object type, where attachments use post meta.
	 */
	public function test_deleting_object_should_fire_meta_actions_with_array_of_meta_ids_in_batches( $object_type ) {
		list( $object_id, $meta_ids ) = $this->create_object_with_meta( $object_type );

		$meta_type = ( 'attachment' === $object_type ) ? 'post' : $object_type;

		$events  = array();
		$args    = array();
		$remains = array();

		add_action(
			"delete_{$meta_type}_meta",
			static function ( $ids, $id, $key ) use ( &$events, &$args, &$remains, $meta_type ) {
				if ( 0 !== strpos( $key, 'synthetic_' ) ) {
					return;
				}
				$events[]               = "delete:$key";
				$args['delete'][ $key ] = array( $ids, $id );
				$remains[ $key ]        = array_keys( get_metadata( $meta_type, $id ) );
			},
			10,
			3
		);
		add_action(
			"deleted_{$meta_type}_meta",
			static function ( $ids, $id, $key ) use ( &$events, &$args ) {
				if ( 0 !== strpos( $key, 'synthetic_' ) ) {
					return;
				}
				$events[]                = "deleted:$key";
				$args['deleted'][ $key ] = array( $ids, $id );
			},
			10,
			3
		);

		$this->delete_object( $object_type, $object_id );

		$this->assertSame(
			array( 'delete:synthetic_a', 'delete:synthetic_b', 'deleted:synthetic_a', 'deleted:synthetic_b' ),
			$events,
			'All before actions should fire before any after action.'
		);

		foreach ( array( 'delete', 'deleted' ) as $when ) {
			$this->assertSame( array( array( $meta_ids['synthetic_a'] ), $object_id ), $args[ $when ]['synthetic_a'], "{$when}_{$meta_type}_meta args for synthetic_a do not match." );
			$this->assertSame( array( array( $meta_ids['synthetic_b'] ), $object_id ), $args[ $when ]['synthetic_b'], "{$when}_{$meta_type}_meta args for synthetic_b do not match." );
		}

		foreach ( $remains as $key => $present ) {
			$this->assertContains( 'synthetic_a', $present, "Before action for $key should still see synthetic_a." );
			$this->assertContains( 'synthetic_b', $present, "Before action for $key should still see synthetic_b." );
		}

		$this->assertSame( array(), array_intersect_key( get_metadata( $meta_type, $object_id ), array_flip( array( 'synthetic_a', 'synthetic_b' ) ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_object_types_deleted_with_their_meta() {
		return array(
			'post'       => array( 'post' ),
			'attachment' => array( 'attachment' ),
			'comment'    => array( 'comment' ),
			'user'       => array( 'user' ),
		);
	}

	/**
	 * @ticket 37702
	 *
	 * @covers ::wp_delete_site
	 *
	 * @group ms-required
	 */
	public function test_deleting_site_should_fire_blog_meta_actions_with_array_of_meta_ids_in_batches() {
		if ( ! is_site_meta_supported() ) {
			$this->markTestSkipped( 'Site meta is not supported.' );
		}

		$site_id = self::factory()->blog->create();
		$mid1    = add_site_meta( $site_id, 'synthetic_a', '1' );
		$mid2    = add_site_meta( $site_id, 'synthetic_b', '2' );

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_blog_meta', array( $delete, 'action' ), 10, 4 );
		add_action( 'deleted_blog_meta', array( $deleted, 'action' ), 10, 4 );

		wp_delete_site( $site_id );

		$expected = array(
			array( array( $mid1 ), $site_id, 'synthetic_a', '1' ),
			array( array( $mid2 ), $site_id, 'synthetic_b', '2' ),
		);

		$this->assertSame( $expected, $delete->get_args(), 'delete_blog_meta args do not match.' );
		$this->assertSame( $expected, $deleted->get_args(), 'deleted_blog_meta args do not match.' );
	}

	/**
	 * Creates an object of the given meta type with two synthetic meta rows.
	 *
	 * @param string $meta_type Object type, where attachments use post meta.
	 * @return array Object ID and a map of meta key to meta ID.
	 */
	private function create_object_with_meta( $meta_type ) {
		$object_type = $meta_type;

		switch ( $object_type ) {
			case 'comment':
				$object_id = self::factory()->comment->create();
				break;
			case 'user':
				$object_id = self::factory()->user->create();
				break;
			case 'attachment':
				$object_id = self::factory()->attachment->create_object(
					array(
						'file'           => 'synthetic.jpg',
						'post_mime_type' => 'image/jpeg',
					)
				);
				break;
			default:
				$object_id = self::factory()->post->create();
		}

		$meta_type = ( 'attachment' === $meta_type ) ? 'post' : $meta_type;

		return array(
			$object_id,
			array(
				'synthetic_a' => add_metadata( $meta_type, $object_id, 'synthetic_a', '1' ),
				'synthetic_b' => add_metadata( $meta_type, $object_id, 'synthetic_b', '2' ),
			),
		);
	}

	/**
	 * Permanently deletes an object of the given meta type.
	 *
	 * @param string $meta_type Object type.
	 * @param int    $object_id Object ID.
	 */
	private function delete_object( $meta_type, $object_id ) {
		switch ( $meta_type ) {
			case 'comment':
				wp_delete_comment( $object_id, true );
				break;
			case 'user':
				if ( is_multisite() ) {
					wpmu_delete_user( $object_id );
				} else {
					wp_delete_user( $object_id );
				}
				break;
			case 'attachment':
				wp_delete_attachment( $object_id, true );
				break;
			default:
				wp_delete_post( $object_id, true );
		}
	}
}

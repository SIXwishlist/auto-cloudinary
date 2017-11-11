<?php

/**
 * Plugin-related tests.
 */
class JB_Test_Cloudinary_Plugin extends WP_UnitTestCase {

	private static $_upload_dir    = '';
	private static $_image_id      = 0;
	private static $_attached_file = null;

	/**
	 * Setup.
	 */
	static function setUpBeforeClass() {
		self::$_upload_dir    = wp_upload_dir();
		self::$_image_id      = self::upload_image();
		self::$_attached_file = get_attached_file( self::$_image_id );

		update_option( 'cloudinary_content_images', '1' );
		JB\Cloudinary\bootstrap();
	}

	/**
	 * Tear down.
	 */
	static function tearDownAfterClass() {
		wp_delete_attachment( self::$_image_id, true );
	}

	/**
	 * Upload an image.
	 *
	 * @return int|WP_Error
	 */
	static function upload_image() {
		$wp_upload_dir = self::$_upload_dir;

		$file_name = $wp_upload_dir['path'] . DIRECTORY_SEPARATOR . 'image-' . rand_str( 6 ) . '.jpg';
		$file_type = wp_check_filetype( basename( $file_name ), null );
		copy( JB_CLOUDINARY_PATH . '/tests/data/image.jpg', $file_name );

		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $file_name ),
			'post_mime_type' => $file_type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$image_id = wp_insert_attachment( $attachment, $file_name );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$attach_data = wp_generate_attachment_metadata( $image_id, $file_name );
		wp_update_attachment_metadata( $image_id, $attach_data );

		return $image_id;
	}

	/**
	 * @covers \JB\Cloudinary\Core::setup()
	 */
	function test_setup() {
		$cloudinary = JB\Cloudinary\Core::get_instance();
		$this->assertFalse( $cloudinary->_setup, 'Cloudinary already setup.' );

		update_option( 'cloudinary_cloud_name', 'test-cloud' );
		update_option( 'cloudinary_auto_mapping_folder', 'test-auto-folder' );
		$cloudinary->setup();

		$this->assertTrue( $cloudinary->_setup, 'Cloudinary not setup.' );
	}

	/**
	 * @covers \JB\Cloudinary\Core::get_domain()
	 */
	function test_domain() {
		$cloudinary = JB\Cloudinary\Core::get_instance();

		$this->assertEquals( $cloudinary->get_domain(), 'https://res.cloudinary.com', 'Cloudinary not setup.' );

		$cloudinary->_urls = array();
		add_filter( 'cloudinary_urls', function( $urls ) {
			return array( 'https://res-1.cloudinary.com', 'https://res-2.cloudinary.com', 'https://res-3.cloudinary.com' );
		} );

		$this->assertEquals( $cloudinary->get_domain(), 'https://res-1.cloudinary.com', 'Incorrect URL.' );
		$this->assertEquals( $cloudinary->get_domain(), 'https://res-2.cloudinary.com', 'Incorrect URL.' );
		$this->assertEquals( $cloudinary->get_domain(), 'https://res-3.cloudinary.com', 'Incorrect URL.' );
		$this->assertEquals( $cloudinary->get_domain(), 'https://res-1.cloudinary.com', 'Incorrect URL.' );
	}

	/**
	 * @covers cloudinary_url()
	 * @covers \JB\Cloudinary\Core::get_url()
	 */
	function test_get_url() {
		cloudinary_ignore_start();
		$file               = self::$_attached_file;
		$file_info          = pathinfo( $file );
		$test_file_name     = 'test-file-name';
		$wp_upload_dir      = self::$_upload_dir;
		$image_path         = $wp_upload_dir['subdir'] . '/' . $file_info['basename'];
		$image_path_2       = $wp_upload_dir['subdir'] . '/' . $file_info['filename'] . '/' . $test_file_name . '.' . $file_info['extension'];
		$original_image_url = wp_get_attachment_url( self::$_image_id );
		$options            = array(
			'transform' => array(
				'width'   => 300,
				'height'  => 200,
				'crop'    => 'fill',
				'quality' => '80',
				'gravity' => 'face',
			),
		);
		$options_2          = array(
			'file_name' => $test_file_name,
		);
		$options_3          = array_merge( $options, $options_2 );
		cloudinary_ignore_end();

		$this->assertEquals( cloudinary_url( self::$_image_id ), 'https://res-2.cloudinary.com/test-cloud/test-auto-folder' . $image_path, 'Incorrect URL.' );
		$this->assertEquals( cloudinary_url( $original_image_url ), 'https://res-3.cloudinary.com/test-cloud/test-auto-folder' . $image_path, 'Incorrect URL.' );

		$this->assertEquals( cloudinary_url( self::$_image_id, $options ), 'https://res-1.cloudinary.com/test-cloud/w_300,h_200,c_fill,q_80,g_face/test-auto-folder' . $image_path, 'Incorrect URL.' );
		$this->assertEquals( cloudinary_url( $original_image_url, $options ), 'https://res-2.cloudinary.com/test-cloud/w_300,h_200,c_fill,q_80,g_face/test-auto-folder' . $image_path, 'Incorrect URL.' );

		$this->assertEquals( cloudinary_url( self::$_image_id, $options_2 ), 'https://res-3.cloudinary.com/test-cloud/images/test-auto-folder' . $image_path_2, 'Incorrect URL.' );
		$this->assertEquals( cloudinary_url( $original_image_url, $options_2 ), 'https://res-1.cloudinary.com/test-cloud/images/test-auto-folder' . $image_path_2, 'Incorrect URL.' );

		$this->assertEquals( cloudinary_url( self::$_image_id, $options_3 ), 'https://res-2.cloudinary.com/test-cloud/images/w_300,h_200,c_fill,q_80,g_face/test-auto-folder' . $image_path_2, 'Incorrect URL.' );
		$this->assertEquals( cloudinary_url( $original_image_url, $options_3 ), 'https://res-3.cloudinary.com/test-cloud/images/w_300,h_200,c_fill,q_80,g_face/test-auto-folder' . $image_path_2, 'Incorrect URL.' );
	}

	/**
	 * @covers cloudinary_update_content_images()
	 */
	function test_update_content_images() {
		cloudinary_ignore_start();
		$content = 'Test content. ' . get_image_tag( self::$_image_id, '', '', 'none', 'full' );
		cloudinary_ignore_end();
		$updated_content = str_replace( self::$_upload_dir['baseurl'], 'https://res-1.cloudinary.com/test-cloud/test-auto-folder', $content );
		$this->assertEquals( cloudinary_update_content_images( $content ), $updated_content, 'Content image incorrect.' );
	}

	/**
	 * @covers \JB\Cloudinary\filter_wp_calculate_image_srcset()
	 */
	function test_image_srcset() {
		$file          = self::$_attached_file;
		$file_info     = pathinfo( $file );
		$wp_upload_dir = self::$_upload_dir;
		$image_path    = $wp_upload_dir['subdir'] . '/' . $file_info['basename'];
		$test_srcset   = array(
			'https://res-3.cloudinary.com/test-cloud/w_1920/test-auto-folder' . $image_path . ' 1920w',
			'https://res-1.cloudinary.com/test-cloud/w_300/test-auto-folder' . $image_path . ' 300w',
			'https://res-2.cloudinary.com/test-cloud/w_768/test-auto-folder' . $image_path . ' 768w',
			'https://res-3.cloudinary.com/test-cloud/w_1024/test-auto-folder' . $image_path . ' 1024w',
		);
		$srcset        = wp_get_attachment_image_srcset( self::$_image_id, 'full' );

		$this->assertEquals( $srcset, implode( ', ', $test_srcset ) );
	}

}

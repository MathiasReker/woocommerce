<?php
/**
 * TransformerService tests.
 *
 * @package WooCommerce\Admin\Tests\RemoteInboxNotifications
 */

use Automattic\WooCommerce\Admin\RemoteInboxNotifications\Transformers\ArrayKeys;
use Automattic\WooCommerce\Admin\RemoteInboxNotifications\TransformerService;

/**
 * class WC_Admin_Tests_RemoteInboxNotifications_TransformerService
 */
class WC_Admin_Tests_RemoteInboxNotifications_TransformerService extends WC_Unit_Test_Case {
	/**
	 * Test it creates a transformer with snake case 'use' value
	 */
	public function test_it_creates_a_transformer_with_snake_case_use_value() {
		$array_keys = TransformerService::create_transformer( 'array_keys' );
		$this->assertInstanceOf( ArrayKeys::class, $array_keys );
	}

	/**
	 * Test it returns null when a transformer is not found.
	 */
	public function test_it_returns_null_when_transformer_is_not_found() {
		$transformer = TransformerService::create_transformer( 'i_do_not_exist' );
		$this->assertNull( $transformer );
	}

	/**
	 * @expectedException InvalidArgumentException
	 * @expectedExceptionMessage Missing required config value: use
	 */
	public function test_it_throw_exception_when_transformer_config_is_missing_use() {
		TransformerService::apply( [ 'value' ], [ new stdClass() ], null );
	}

	/**
	 * @expectedException InvalidArgumentException
	 * @expectedExceptionMessage Unable to find a transformer by name: i_do_not_exist
	 */
	public function test_it_throws_exception_when_transformer_is_not_found() {
		$transformer = $this->transformer_config( 'i_do_not_exist' );
		TransformerService::apply( [ 'value' ], [ $transformer ], null );
	}

	/**
	 * Given two transformers
	 * When the second transformer returns null
	 * Then the value transformed by the first transformer should be returned.
	 */
	public function test_it_returns_previously_transformed_value_when_transformer_returns_null() {
		$dot_notation = $this->transformer_config( 'dot_notation', [ 'path' => 'industries' ] );
		$array_search = $this->transformer_config( 'array_search', [ 'value' => 'i_do_not_exist' ] );
		$items        = [
			'industries' => [ 'item1', 'item2' ],
		];
		$result       = TransformerService::apply( $items, [ $dot_notation, $array_search ], null );
		$this->assertEquals( $result, $items['industries'] );
	}

	/**
	 * Given a nested array
	 * When it uses DotNotation to select 'teams'
	 * When it uses ArrayColumn to select 'members' in 'teams'
	 * When it uses ArrayFlatten to flatten 'members'
	 * When it uses ArraySearch to select 'mothra-member'
	 * Then 'mothra-member' should be returned.
	 */
	public function test_it_returns_transformed_value() {
		// Given.
		$items = [
			'teams' => [
				[
					'name'    => 'mothra',
					'members' => [ 'mothra-member' ],
				],
				[
					'name'    => 'gezora',
					'members' => [ 'gezora-member' ],
				],
				[
					'name'    => 'ghidorah',
					'members' => [ 'ghidorah-member' ],
				],
			],
		];

		// When.
		$dot_notation  = $this->transformer_config( 'dot_notation', [ 'path' => 'teams' ] );
		$array_column  = $this->transformer_config( 'array_column', [ 'key' => 'members' ] );
		$array_flatten = $this->transformer_config( 'array_flatten' );
		$array_search  = $this->transformer_config( 'array_search', [ 'value' => 'mothra-member' ] );

		$result = TransformerService::apply( $items, [ $dot_notation, $array_column, $array_flatten, $array_search ], null );

		// Then.
		$this->assertEquals( 'mothra-member', $result );
	}

	/**
	 * Creates transformer config object
	 *
	 * @param string $name name of the transformer in snake_case.
	 * @param array  $arguments transformer arguments.
	 *
	 * @return stdClass
	 */
	private function transformer_config( $name, array $arguments = [] ) {
		$transformer            = new stdClass();
		$transformer->use       = $name;
		$transformer->arguments = (object) $arguments;
		return $transformer;
	}
}

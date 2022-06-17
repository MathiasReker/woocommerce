<?php
register_woocommerce_admin_test_helper_rest_route(
	'/tools/get-cron-list/v1',
	'tools_get_cron_list',
	[
		'methods' => 'GET',
	]
);
register_woocommerce_admin_test_helper_rest_route(
	'/tools/trigger-selected-cron/v1',
	'trigger_selected_cron',
	[
		'methods' => 'POST',
		'args'                => [
			'hook'     => [
				'description'       => 'Name of the cron that will be triggered.',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'signature' => [
				'description'       => 'Signature of the cron to trigger.',
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		],
	]
);

function tools_get_cron_list() {
	$crons  = _get_cron_array();
	$events = [];

	if ( empty( $crons ) ) {
		return [];
	}

	foreach ( $crons as $cron ) {
		foreach ( $cron as $hook => $data ) {
			foreach ( $data as $signature => $element ) {
				$events[ $hook ] = (object) [
					'hook'      => $hook,
					'signature' => $signature,
				];
			}
		}
	}
	return new WP_REST_Response( $events, 200 );
}

function trigger_selected_cron( $request ) {
	$hook      = $request->get_param( 'hook' );
	$signature = $request->get_param( 'signature' );

	if ( ! isset( $hook ) || ! isset( $signature ) ) {
		return;
	}

	$crons = _get_cron_array();
	foreach ( $crons as $cron ) {
		if ( isset( $cron[ $hook ][ $signature ] ) ) {
			$args = $cron[ $hook ][ $signature ]['args'];
			delete_transient( 'doing_cron' );
			$scheduled = schedule_event( $hook, $args );

			if ( false === $scheduled ) {
				return $scheduled;
			}

			add_filter( 'cron_request', function( array $cron_request ) {
				$cron_request['url'] = add_query_arg( 'run-cron', 1, $cron_request['url'] );
				return $cron_request;
			} );

			spawn_cron();
			sleep( 1 );
			return true;
		}
	}
	return false;
}

function schedule_event( $hook, $args = [] ) {
	$event = (object) [
		'hook'      => $hook,
		'timestamp' => 1,
		'schedule'  => false,
		'args'      => $args,
	];
	$crons = (array) _get_cron_array();
	$key   = md5( serialize( $event->args ) );

	$crons[ $event->timestamp ][ $event->hook ][ $key ] = [
		'schedule' => $event->schedule,
		'args'     => $event->args,
	];
	uksort( $crons, 'strnatcasecmp' );
	return _set_cron_array( $crons );
}

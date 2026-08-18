<?php
/**
 * Persistent WP-CLI command server for e2e Woo fixtures.
 *
 * Commands travel through files in this mapped scripts directory, so the host
 * starts wp-env once and never learns a Docker-generated container name.
 */

defined( 'ABSPATH' ) || exit( 1 );

$directory = __DIR__ . '/.wp-cli-batch';
wp_mkdir_p( $directory );
$request  = $directory . '/request.json';
$response = $directory . '/response.json';
$ready    = $directory . '/ready';
file_put_contents( $ready, 'ready' );
$last_id = '';

while ( true ) {
	if ( ! is_file( $request ) ) {
		usleep( 10000 );
		continue;
	}

	$payload = json_decode( (string) file_get_contents( $request ), true );
	if ( ! is_array( $payload ) || ! isset( $payload['id'], $payload['command'] ) || $payload['id'] === $last_id ) {
		usleep( 10000 );
		continue;
	}

	$last_id = (string) $payload['id'];
	unlink( $request );
	file_put_contents( $directory . '/last-command.txt', (string) $payload['command'] );
	try {
		$result = WP_CLI::runcommand( (string) $payload['command'], [ 'return' => 'all', 'exit_on_error' => false ] );
		$raw_output = is_array( $result )
			? ( $result['stdout'] ?? '' )
			: ( is_object( $result ) ? ( $result->stdout ?? '' ) : $result );
		$output     = is_scalar( $raw_output ) || null === $raw_output
			? (string) $raw_output
			: (string) wp_json_encode( $raw_output );
		$code   = is_array( $result )
			? (int) ( $result['return_code'] ?? 0 )
			: ( is_object( $result ) ? (int) ( $result->return_code ?? 0 ) : 0 );
	} catch ( Throwable $exception ) {
		$output = $exception->getMessage();
		$code   = 1;
	}
	$temporary_response = $response . '.' . $last_id;
	file_put_contents( $temporary_response, wp_json_encode( [ 'id' => $last_id, 'stdout' => $output, 'code' => $code ] ) );
	rename( $temporary_response, $response );
}

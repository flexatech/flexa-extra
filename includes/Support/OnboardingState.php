<?php

declare(strict_types=1);

namespace Flexa\Extra\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the `flexa_extra_onboarding` option: the first-run
 * quick-start guide's progress. A typed schema, defaults, a coercing read, a
 * sanitizer that drops unknown keys and invalid enum values, and a
 * merge-over-stored write.
 *
 * The guide is deliberately light: it only records a status and stamps three
 * timestamps server-side. Nothing that can drift (whether option sets exist,
 * what they contain) is persisted here; the UI derives that from live data.
 *
 * Timestamps are server-authoritative: the client POSTs a status and the write
 * stamps started/completed/dismissed once, never trusting a client clock.
 */
final class OnboardingState {
	public const OPTION_KEY = 'flexa_extra_onboarding';

	/** Bumped when the option shape changes so a future build can migrate it. */
	private const VERSION = 1;

	/** @var list<string> */
	private const STATUSES = [ 'pending', 'in_progress', 'completed', 'dismissed' ];

	/**
	 * The full default state payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'version'      => self::VERSION,
			'status'       => 'pending',
			'started_at'   => null,
			'completed_at' => null,
			'dismissed_at' => null,
		];
	}

	/**
	 * Stored state merged over defaults, every value coerced back to its declared
	 * type so a hand-edited or corrupt option can never hand a consumer the wrong
	 * shape.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return self::coerce( $stored );
	}

	/**
	 * Read a single top-level value with its default fallback.
	 *
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * True once the user has finished or dismissed the guide. Used to suppress the
	 * activation redirect and the plugins-screen notice.
	 */
	public static function is_finished(): bool {
		return in_array( self::all()['status'], [ 'completed', 'dismissed' ], true );
	}

	/**
	 * Apply a partial client payload over the stored state and persist. Only the
	 * status is writable; timestamps are stamped here, once, on the first
	 * transition into each terminal state.
	 *
	 * @param array<string,mixed> $partial
	 * @return array<string,mixed> The full coerced state after the write.
	 */
	public static function update( array $partial ): array {
		$next  = self::all();
		$clean = self::sanitize( $partial );

		if ( isset( $clean['status'] ) ) {
			$next['status'] = $clean['status'];
		}

		// Server-authoritative timestamps: each stamped once on first transition.
		if ( 'in_progress' === $next['status'] && null === $next['started_at'] ) {
			$next['started_at'] = time();
		}
		if ( 'completed' === $next['status'] && null === $next['completed_at'] ) {
			$next['completed_at'] = time();
		}
		if ( 'dismissed' === $next['status'] && null === $next['dismissed_at'] ) {
			$next['dismissed_at'] = time();
		}

		$next['version'] = self::VERSION;

		update_option( self::OPTION_KEY, $next );

		return self::coerce( $next );
	}

	/**
	 * Sanitize an incoming payload against the schema. Only `status` is honoured;
	 * unknown keys and invalid enum values are dropped so arbitrary client input
	 * is never persisted, and timestamps stay server-owned.
	 *
	 * @param array<string,mixed> $incoming
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $incoming ): array {
		$clean = [];

		if ( array_key_exists( 'status', $incoming ) ) {
			$status = is_scalar( $incoming['status'] ) ? (string) $incoming['status'] : '';
			if ( in_array( $status, self::STATUSES, true ) ) {
				$clean['status'] = $status;
			}
		}

		return $clean;
	}

	/**
	 * @param array<string,mixed> $stored
	 * @return array<string,mixed>
	 */
	private static function coerce( array $stored ): array {
		$out = self::defaults();

		if ( isset( $stored['status'] ) && in_array( $stored['status'], self::STATUSES, true ) ) {
			$out['status'] = $stored['status'];
		}
		foreach ( [ 'started_at', 'completed_at', 'dismissed_at' ] as $ts ) {
			if ( isset( $stored[ $ts ] ) && is_numeric( $stored[ $ts ] ) ) {
				$out[ $ts ] = (int) $stored[ $ts ];
			}
		}

		$out['version'] = self::VERSION;

		return $out;
	}
}

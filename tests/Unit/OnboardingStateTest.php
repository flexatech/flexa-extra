<?php

declare(strict_types=1);

namespace Flexa\Extra\Tests\Unit;

use Flexa\Extra\Support\OnboardingState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( OnboardingState::class )]
final class OnboardingStateTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['fx_options'] = array();
	}

	public function test_defaults_when_unset(): void {
		$state = OnboardingState::all();

		$this->assertSame( 'pending', $state['status'] );
		$this->assertNull( $state['started_at'] );
		$this->assertNull( $state['completed_at'] );
		$this->assertNull( $state['dismissed_at'] );
		$this->assertFalse( OnboardingState::is_finished() );
	}

	public function test_update_stamps_started_at_once(): void {
		$first = OnboardingState::update( array( 'status' => 'in_progress' ) );
		$this->assertSame( 'in_progress', $first['status'] );
		$this->assertIsInt( $first['started_at'] );

		$stamp = $first['started_at'];

		// A later status change must not re-stamp started_at.
		$second = OnboardingState::update( array( 'status' => 'completed' ) );
		$this->assertSame( $stamp, $second['started_at'] );
		$this->assertIsInt( $second['completed_at'] );
		$this->assertTrue( OnboardingState::is_finished() );
	}

	public function test_dismiss_is_finished(): void {
		OnboardingState::update( array( 'status' => 'dismissed' ) );

		$this->assertSame( 'dismissed', OnboardingState::get( 'status' ) );
		$this->assertIsInt( OnboardingState::get( 'dismissed_at' ) );
		$this->assertTrue( OnboardingState::is_finished() );
	}

	public function test_sanitize_drops_invalid_status_and_unknown_keys(): void {
		$clean = OnboardingState::sanitize(
			array(
				'status'       => 'bogus',
				'completed_at' => 999,   // server-owned, never client-writable
				'evil'         => true,
			)
		);

		$this->assertSame( array(), $clean );
	}

	public function test_update_ignores_invalid_status(): void {
		$state = OnboardingState::update( array( 'status' => 'not-a-status' ) );

		// Falls back to the stored/default status, never persists the bad value.
		$this->assertSame( 'pending', $state['status'] );
	}

	public function test_coerce_repairs_corrupt_option(): void {
		$GLOBALS['fx_options'][ OnboardingState::OPTION_KEY ] = array(
			'status'     => array( 'not', 'a', 'string' ),
			'started_at' => 'oops',
		);

		$state = OnboardingState::all();

		$this->assertSame( 'pending', $state['status'] );
		$this->assertNull( $state['started_at'] );
	}
}
